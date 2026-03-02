<?php
/**
 * Section Registry.
 *
 * Bootloader and metadata store for member directory sections.
 *
 * Two distinct modes of operation:
 *
 * 1. RUNTIME (every page load)
 *    SectionRegistry::load_from_db() reads the member_directory_sections option
 *    and populates the in-memory section cache. ACF field groups are loaded
 *    natively by ACF from the plugin's acf-json/ folder (registered via
 *    acf/settings/load_json in Plugin.php) — no acf_add_local_field_group()
 *    call is needed for current-format sections.
 *
 *    Backward compat: sections still stored in the old format (with a full
 *    acf_group definition rather than a pointer key) are registered locally
 *    via acf_add_local_field_group() so they continue to work during migration.
 *
 * 2. SYNC (admin-triggered only)
 *    SectionRegistry::sync() reads all JSON files from the sections/ folder,
 *    validates them, sorts them, and saves the result to the
 *    member_directory_sections option. The next page load then picks up the
 *    new data automatically via load_from_db().
 *
 * Public API (all static):
 *   SectionRegistry::get_sections()        — all sections as an ordered array
 *   SectionRegistry::get_section( $key )   — one section by key, or null
 *   SectionRegistry::sync()                — read filesystem → save to option
 *   SectionRegistry::load_from_db()        — read option → populate cache
 *   SectionRegistry::validate_for_upload() — validate a config before saving
 *   SectionRegistry::removed_content_keys() — always empty (ACF owns fields now)
 */

namespace MemberDirectory;

defined( 'ABSPATH' ) || exit;

class SectionRegistry {

	/** WordPress option key where the parsed section array is stored. */
	const OPTION_KEY = 'member_directory_sections';

	/**
	 * Required top-level keys in every lean section config file.
	 *
	 * acf_group_key — the ACF field group key this section points to
	 *                 (e.g. "group_md_02_profile"). ACF loads and owns that
	 *                 group via the acf-json/ folder; the section config is
	 *                 metadata only.
	 * order         — optional; sections without it sort to the end.
	 * can_be_primary — optional; absent means false.
	 */
	const REQUIRED_KEYS = [
		'key',
		'label',
		'acf_group_key',
	];

	/**
	 * Field types that are never rendered as content.
	 * Used by is_system_field() when filtering acf_get_fields() output.
	 */
	const SKIP_TYPES = [ 'button_group' ];

	/**
	 * Field name values that identify non-content ACF fields.
	 * Used by is_system_field() when filtering acf_get_fields() output.
	 */
	const SKIP_NAMES = [
		'post_title',
		'display_name',
		'first_name',
		'last_name',
		'allow_comments',
	];

	/**
	 * Substrings that, if present in a field key, mark it as a system field.
	 * Used by is_system_field() when filtering acf_get_fields() output.
	 */
	const SKIP_KEY_PATTERNS = [ '_enabled', '_privacy_mode', '_privacy_level', '_pmp_' ];

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
	 * @return array{
	 *     loaded: string[],
	 *     skipped: array<string, string>
	 * }
	 */
	public static function sync(): array {
		$sections_dir = self::sections_dir();
		$json_files   = glob( $sections_dir . '*.json' );

		if ( $json_files === false ) {
			$json_files = [];
		}

		$valid     = [];
		$loaded    = [];
		$skipped   = [];
		$seen_keys = [];

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

			$integrity_error = self::validate_section_integrity( $data, $seen_keys );

			if ( $integrity_error !== null ) {
				$skipped[ $filename ] = $integrity_error;
				continue;
			}

			$key           = $data['key'];
			$valid[ $key ] = $data;
			$loaded[]      = $filename;
		}

		// Preserve existing DB order; append newly discovered sections at the end.
		$existing_db = get_option( self::OPTION_KEY, [] );
		$ordered     = [];

		foreach ( array_keys( $existing_db ) as $existing_key ) {
			if ( isset( $valid[ $existing_key ] ) ) {
				$ordered[ $existing_key ] = $valid[ $existing_key ];
				unset( $valid[ $existing_key ] );
			}
		}

		foreach ( $valid as $new_key => $new_section ) {
			$ordered[ $new_key ] = $new_section;
		}

		update_option( self::OPTION_KEY, $ordered, false );

		self::$sections = $ordered;

		return [
			'loaded'  => $loaded,
			'skipped' => $skipped,
		];
	}

	/**
	 * RUNTIME — read section metadata from the database.
	 *
	 * Current-format sections (lean pointer format with acf_group_key) rely on
	 * ACF's native JSON sync to load their field groups — no registration call
	 * is needed here.
	 *
	 * Backward compat: old-format sections that still carry a full acf_group
	 * definition are registered locally so they continue to work while the server
	 * is being migrated to the lean format.
	 */
	public static function load_from_db(): void {
		$stored = get_option( self::OPTION_KEY, [] );

		if ( ! is_array( $stored ) || empty( $stored ) ) {
			return;
		}

		self::$sections = $stored;

		// Backward compat: register old-format sections that carry a full
		// acf_group definition. Remove this block once all servers have been
		// migrated (i.e. all sections in the DB use acf_group_key only).
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
	 * Public so AdminSync can resolve the same path for file uploads.
	 */
	public static function sections_dir(): string {
		return dirname( __DIR__ ) . '/sections/';
	}

	/**
	 * Validate a single section config for upload.
	 *
	 * @param  array  $data  Decoded section config.
	 * @return string|null   First error message found, or null if clean.
	 */
	public static function validate_for_upload( array $data ): ?string {
		$missing = self::missing_required_keys( $data );
		if ( ! empty( $missing ) ) {
			return 'Missing required keys: ' . implode( ', ', $missing ) . '.';
		}

		$seen_keys = [];
		return self::validate_section_integrity( $data, $seen_keys );
	}

	/**
	 * Always returns an empty array.
	 *
	 * Field management is ACF's responsibility now — field keys live in
	 * acf-json/ and are not part of the lean section config. No diff is
	 * possible or necessary at upload time.
	 *
	 * @param  array    $incoming  Unused.
	 * @return string[]            Always empty.
	 */
	public static function removed_content_keys( array $incoming ): array {
		return [];
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

	/**
	 * Validate the internal integrity of a lean section config.
	 *
	 * Checks that acf_group_key is a non-empty string. Full field-level
	 * validation (system field presence, naming conventions, key collisions)
	 * is no longer performed here — ACF owns the field group and enforces
	 * its own constraints.
	 *
	 * @param  array                $data       Decoded section config.
	 * @param  array<string,string> &$seen_keys  Unused; kept for call-site compat.
	 * @return string|null  Error message on failure, null if clean.
	 */
	private static function validate_section_integrity( array $data, array &$seen_keys ): ?string {
		$acf_group_key = $data['acf_group_key'] ?? '';

		if ( empty( $acf_group_key ) ) {
			return 'acf_group_key must be a non-empty string (e.g. "group_md_02_profile").';
		}

		return null;
	}

	/**
	 * Return true if a field should be excluded from content field lists.
	 *
	 * Used by section-view.php to filter acf_get_fields() output down to
	 * renderable content fields only.
	 *
	 * Excludes:
	 *   - Tab markers (structural dividers, not rendered)
	 *   - button_group fields (PMP selectors)
	 *   - Any field whose key contains a system key pattern
	 *   - Any field whose name is in the SKIP_NAMES list
	 *
	 * @param  array $field  A single field definition from acf_get_fields().
	 * @return bool
	 */
	public static function is_system_field( array $field ): bool {
		$type = $field['type'] ?? '';
		$key  = $field['key']  ?? '';
		$name = $field['name'] ?? '';

		if ( $type === 'tab' ) {
			return true;
		}

		if ( in_array( $type, self::SKIP_TYPES, true ) ) {
			return true;
		}

		foreach ( self::SKIP_KEY_PATTERNS as $pattern ) {
			if ( str_contains( $key, $pattern ) ) {
				return true;
			}
		}

		if ( in_array( $name, self::SKIP_NAMES, true ) ) {
			return true;
		}

		return false;
	}
}
