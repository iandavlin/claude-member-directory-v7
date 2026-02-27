<?php
/**
 * Section Registry.
 *
 * The single source of truth for section data at runtime.
 *
 * Two distinct modes of operation:
 *
 * 1. RUNTIME (every page load)
 *    SectionRegistry::load_from_db() reads the member_directory_sections option
 *    and registers each section's ACF field group with ACF via
 *    acf_add_local_field_group(). No filesystem access.
 *
 * 2. SYNC (admin-triggered only)
 *    SectionRegistry::sync() reads all JSON files from the sections/ folder,
 *    validates them, sorts them, and saves the result to the
 *    member_directory_sections option. The next page load then picks up the
 *    new data automatically via load_from_db().
 *
 * Public API (all static):
 *   SectionRegistry::get_sections()       — all sections, sorted by order
 *   SectionRegistry::get_section( $key )  — one section by key, or null
 *   SectionRegistry::sync()               — read filesystem → save to option
 *   SectionRegistry::load_from_db()       — read option → register with ACF
 */

namespace MemberDirectory;

defined( 'ABSPATH' ) || exit;

class SectionRegistry {

	/** WordPress option key where the parsed section array is stored. */
	const OPTION_KEY = 'member_directory_sections';

	/**
	 * Required top-level keys in every section config file.
	 * A file that is missing any of these is skipped with a warning.
	 */
	const REQUIRED_KEYS = [
		'key',
		'label',
		'order',
		'can_be_primary',
		'pmp_default',
		'fields',
		'acf_group',
	];

	/**
	 * In-memory cache of the loaded sections for the current request.
	 * Populated by load_from_db(); consumed by get_sections() / get_section().
	 *
	 * @var array<string, array>  Keyed by section key.
	 */
	private static array $sections = [];

	// -----------------------------------------------------------------------
	// Public API
	// -----------------------------------------------------------------------

	/**
	 * Return all registered sections as an ordered array.
	 *
	 * The array is sorted ascending by the 'order' value set in each JSON
	 * config. If load_from_db() has not yet been called this request, the
	 * array will be empty.
	 *
	 * @return array[]
	 */
	public static function get_sections(): array {
		return array_values( self::$sections );
	}

	/**
	 * Return a single section by its key, or null if not found.
	 *
	 * @param string $key  The section key (e.g. 'profile', 'business').
	 * @return array|null
	 */
	public static function get_section( string $key ): ?array {
		return self::$sections[ $key ] ?? null;
	}

	/**
	 * SYNC — read all JSON files from sections/ and save to the database.
	 *
	 * Called only from the admin sync action, never on a regular page load.
	 *
	 * Steps:
	 *   1. Glob all *.json files in the sections/ directory.
	 *   2. JSON-decode each file.
	 *   3. Validate that all required keys are present.
	 *   4. Collect valid sections into an array, skipping invalid ones.
	 *   5. Sort by 'order' ascending.
	 *   6. Save to the member_directory_sections option.
	 *   7. Reload into the in-memory cache so the current request reflects
	 *      the new data without needing another page load.
	 *
	 * @return array{
	 *     loaded: string[],
	 *     skipped: array<string, string>
	 * }
	 * Returns a summary of which files loaded successfully and which were
	 * skipped, with the reason for each skip. Useful for the admin sync page.
	 */
	public static function sync(): array {
		$sections_dir = self::sections_dir();
		$json_files   = glob( $sections_dir . '*.json' );

		if ( $json_files === false ) {
			$json_files = [];
		}

		$valid   = [];
		$loaded  = [];
		$skipped = [];

		foreach ( $json_files as $file ) {
			$filename = basename( $file );
			$raw      = file_get_contents( $file );

			if ( $raw === false ) {
				$skipped[ $filename ] = 'Could not read file.';
				continue;
			}

			$data = json_decode( $raw, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				$skipped[ $filename ] = 'Invalid JSON: ' . json_last_error_msg();
				continue;
			}

			$missing = self::missing_required_keys( $data );

			if ( ! empty( $missing ) ) {
				$skipped[ $filename ] = 'Missing required keys: ' . implode( ', ', $missing );
				continue;
			}

			$key            = $data['key'];
			$valid[ $key ]  = $data;
			$loaded[]       = $filename;
		}

		// Sort by 'order' ascending.
		uasort( $valid, fn( $a, $b ) => $a['order'] <=> $b['order'] );

		// Persist to the database.
		update_option( self::OPTION_KEY, $valid, false /* not autoloaded */ );

		// Refresh the in-memory cache for the remainder of this request.
		self::$sections = $valid;

		return [
			'loaded'  => $loaded,
			'skipped' => $skipped,
		];
	}

	/**
	 * RUNTIME — read sections from the database and register them with ACF.
	 *
	 * Called on the acf/init hook (priority 5) by Plugin::init_section_registry().
	 * Reads the member_directory_sections option and calls
	 * acf_add_local_field_group() for each section's acf_group definition.
	 *
	 * After this runs, the sections are available via get_sections() /
	 * get_section() for the remainder of the request.
	 */
	public static function load_from_db(): void {
		$stored = get_option( self::OPTION_KEY, [] );

		if ( ! is_array( $stored ) || empty( $stored ) ) {
			return;
		}

		self::$sections = $stored;

		// Register each section's ACF field group so ACF knows about the fields.
		foreach ( self::$sections as $section ) {
			if ( ! empty( $section['acf_group'] ) && is_array( $section['acf_group'] ) ) {
				acf_add_local_field_group( $section['acf_group'] );
			}
		}
	}

	// -----------------------------------------------------------------------
	// Private helpers
	// -----------------------------------------------------------------------

	/**
	 * Absolute path to the plugin's sections/ directory, with trailing slash.
	 */
	private static function sections_dir(): string {
		// Walk up from includes/ to the plugin root, then into sections/.
		return dirname( __DIR__ ) . '/sections/';
	}

	/**
	 * Return any required keys that are absent from a decoded section array.
	 *
	 * @param  mixed $data  The decoded JSON value (may not be an array).
	 * @return string[]     Names of missing keys; empty if all present.
	 */
	private static function missing_required_keys( mixed $data ): array {
		if ( ! is_array( $data ) ) {
			return self::REQUIRED_KEYS;
		}

		$missing = [];

		foreach ( self::REQUIRED_KEYS as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				$missing[] = $key;
			}
		}

		return $missing;
	}
}
