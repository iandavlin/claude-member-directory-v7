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
 *
 * Section config format (sections/*.json):
 *
 *   field_groups is no longer required. The plugin derives content field
 *   groups at runtime by parsing acf_group.fields directly, grouping by
 *   tab markers and skipping system fields. Plugin-specific field metadata
 *   (pmp_default, filterable, required) is stored as custom properties on
 *   each field object inside acf_group.fields — ACF ignores unknown
 *   properties, so this is safe.
 *
 *   Configs that still carry a field_groups key are accepted without error;
 *   the key is simply ignored in favour of the derived data.
 */

namespace MemberDirectory;

defined( 'ABSPATH' ) || exit;

class SectionRegistry {

	/** WordPress option key where the parsed section array is stored. */
	const OPTION_KEY = 'member_directory_sections';

	/**
	 * Required top-level keys in every section config file.
	 * A file that is missing any of these is skipped with a warning.
	 *
	 * Note: field_groups is intentionally absent. Content field groups are
	 * derived at runtime from acf_group.fields. Configs that still include
	 * field_groups are accepted; the key is ignored.
	 */
	const REQUIRED_KEYS = [
		'key',
		'label',
		'order',
		'can_be_primary',
		'pmp_default',
		'acf_group',
	];

	/**
	 * Field types that are never rendered as content.
	 * Used by is_system_field() to filter acf_group.fields at runtime.
	 */
	const SKIP_TYPES = [ 'button_group' ];

	/**
	 * Field name values that identify non-content ACF fields.
	 * Used by is_system_field() to filter acf_group.fields at runtime.
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
	 * Used by is_system_field() to filter acf_group.fields at runtime.
	 */
	const SKIP_KEY_PATTERNS = [ '_enabled', '_privacy_mode', '_privacy_level' ];

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
	 * Return content field groups derived from a section's acf_group.fields.
	 *
	 * Parses acf_group.fields in order, opening a new group each time a tab
	 * field is encountered. System fields (enabled toggle, privacy_mode button,
	 * etc.) are excluded. Each returned field object carries the plugin-specific
	 * properties (pmp_default, filterable, required, taxonomy) read directly
	 * from the field definition — falling back to the section's pmp_default
	 * when a field does not declare its own.
	 *
	 * Fields that appear before the first tab marker are collected under a
	 * synthetic "General" group.
	 *
	 * @param array $section  A section array from get_sections() or get_section().
	 * @return array<array{tab: string, fields: array}>
	 */
	public static function get_field_groups( array $section ): array {
		$acf_fields = $section['acf_group']['fields'] ?? [];

		if ( ! empty( $acf_fields ) ) {
			return self::derive_field_groups( $acf_fields, $section['pmp_default'] ?? 'member' );
		}

		return [];
	}

	/**
	 * Flatten a section's content fields into a single array.
	 *
	 * Useful for any code that needs all fields regardless of tab grouping —
	 * e.g. PMP resolution loops, field counts, or FieldRenderer iteration.
	 *
	 * @param array $section  A section array from get_sections() or get_section().
	 * @return array  Flat array of field objects.
	 */
	public static function get_all_fields( array $section ): array {
		$fields = [];

		foreach ( self::get_field_groups( $section ) as $group ) {
			if ( ! empty( $group['fields'] ) && is_array( $group['fields'] ) ) {
				array_push( $fields, ...$group['fields'] );
			}
		}

		return $fields;
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

		$valid     = [];
		$loaded    = [];
		$skipped   = [];
		$seen_keys = []; // Tracks field keys across sections for collision detection.

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
	 * Public so AdminSync can resolve the same path for file uploads.
	 */
	public static function sections_dir(): string {
		// Walk up from includes/ to the plugin root, then into sections/.
		return dirname( __DIR__ ) . '/sections/';
	}

	/**
	 * Validate a single section config for upload.
	 *
	 * Combines the required-keys check and the integrity check into one
	 * public call suitable for use outside this class (e.g. the upload
	 * handler in AdminSync).
	 *
	 * For the cross-section collision check, the currently-loaded sections
	 * are used as the baseline — the section being uploaded is excluded so
	 * a valid overwrite does not trigger a false positive.
	 *
	 * For existing sections, a diff check is also run: if the incoming config
	 * removes any content field keys that exist in the current stored config,
	 * the upload is blocked to prevent orphaning live member data.
	 *
	 * @param  array  $data  Decoded section config.
	 * @return string|null   First error message found, or null if clean.
	 */
	public static function validate_for_upload( array $data ): ?string {
		$missing = self::missing_required_keys( $data );
		if ( ! empty( $missing ) ) {
			return 'Missing required keys: ' . implode( ', ', $missing ) . '.';
		}

		$uploading_key = $data['key'];

		// --- Diff check: block removal of content field keys -----------------
		// Removing a field key from an existing section orphans any member data
		// stored under that key in wp_postmeta. Block the upload and name the
		// missing keys so the admin can make an explicit decision.
		$current = self::get_section( $uploading_key );
		if ( $current !== null ) {
			$current_keys  = self::content_field_keys( $current );
			$incoming_keys = self::content_field_keys( $data );
			$removed       = array_diff( $current_keys, $incoming_keys );

			if ( ! empty( $removed ) ) {
				return 'Upload would remove field key(s) that may have live member data: '
					. implode( ', ', array_values( $removed ) )
					. '. Retain or explicitly rename these keys to avoid data loss.';
			}
		}

		// --- Collision check: build seen_keys from all other live sections ----
		$seen_keys = [];

		foreach ( self::get_sections() as $existing ) {
			if ( $existing['key'] === $uploading_key ) {
				continue; // Exclude the section being overwritten.
			}
			foreach ( $existing['acf_group']['fields'] ?? [] as $field ) {
				if ( ! empty( $field['key'] ) ) {
					$seen_keys[ $field['key'] ] = $existing['key'];
				}
			}
		}

		return self::validate_section_integrity( $data, $seen_keys );
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
	 * Validate the internal integrity of a section config.
	 *
	 * Runs three checks after the basic required-keys check passes:
	 *
	 * 1. System field presence — the two PMP system fields
	 *    (field_md_{key}_enabled and field_md_{key}_privacy_mode) must
	 *    exist in acf_group.fields. These are hardcoded in PHP and cannot
	 *    drift without breaking AJAX handlers and PMP resolution.
	 *
	 * 2. Field name convention — every non-tab, non-system field in
	 *    acf_group.fields must have a name starting with
	 *    member_directory_{section_key}_. Catches cases where a raw ACF
	 *    export name was used instead of the namespaced form that
	 *    get_field() / update_field() expect.
	 *
	 * 3. Cross-section key collision — a field key already registered by
	 *    a previously validated section cannot appear in this one.
	 *    ACF treats field keys as globally unique; duplicates cause
	 *    unpredictable field resolution.
	 *
	 * @param  array                $data       Decoded section config.
	 * @param  array<string,string> &$seen_keys  Accumulator of field key →
	 *                                           section_key pairs built up
	 *                                           across the sync loop.
	 * @return string|null  Error message on the first failure, null if clean.
	 */
	private static function validate_section_integrity( array $data, array &$seen_keys ): ?string {
		$section_key = $data['key'];
		$acf_fields  = $data['acf_group']['fields'] ?? [];

		// Collect all ACF field keys.
		$acf_field_keys = [];
		foreach ( $acf_fields as $field ) {
			if ( ! empty( $field['key'] ) ) {
				$acf_field_keys[] = $field['key'];
			}
		}

		// --- Check 1: system field presence -----------------------------------
		$required_system = [
			'field_md_' . $section_key . '_enabled',
			'field_md_' . $section_key . '_privacy_mode',
		];

		foreach ( $required_system as $sys_key ) {
			if ( ! in_array( $sys_key, $acf_field_keys, true ) ) {
				return "Missing required system field '{$sys_key}' in acf_group.fields.";
			}
		}

		// --- Check 2: field name convention -----------------------------------
		$prefix = 'member_directory_' . $section_key . '_';

		foreach ( $acf_fields as $field ) {
			// Tabs have an empty name by convention — skip them.
			if ( empty( $field['name'] ) || ( $field['type'] ?? '' ) === 'tab' ) {
				continue;
			}
			if ( ! str_starts_with( $field['name'], $prefix ) ) {
				return "Field '{$field['key']}' has name '{$field['name']}'"
					. " which does not start with '{$prefix}'.";
			}
		}

		// --- Check 3: cross-section key collision -----------------------------
		foreach ( $acf_field_keys as $k ) {
			if ( isset( $seen_keys[ $k ] ) ) {
				return "Field key '{$k}' is already registered by section '{$seen_keys[$k]}'.";
			}
		}

		// Register this section's keys so subsequent sections can check against them.
		foreach ( $acf_field_keys as $k ) {
			$seen_keys[ $k ] = $section_key;
		}

		return null;
	}

	/**
	 * Derive tab-grouped content field arrays from a flat acf_group.fields list.
	 *
	 * Tab fields open new groups; system fields are excluded; all remaining
	 * fields are emitted with a normalised set of plugin properties.
	 *
	 * @param  array  $acf_fields         The fields array from acf_group.
	 * @param  string $section_pmp_default Fallback pmp_default when a field
	 *                                     does not declare its own.
	 * @return array<array{tab: string, fields: array}>
	 */
	private static function derive_field_groups( array $acf_fields, string $section_pmp_default ): array {
		$groups         = [];
		$current_tab    = 'General';
		$current_fields = [];

		foreach ( $acf_fields as $field ) {
			// Tab fields open a new group — flush the current one first.
			if ( ( $field['type'] ?? '' ) === 'tab' ) {
				if ( ! empty( $current_fields ) ) {
					$groups[]       = [ 'tab' => $current_tab, 'fields' => $current_fields ];
					$current_fields = [];
				}
				$current_tab = $field['label'] ?? 'General';
				continue;
			}

			// Skip system/non-content fields.
			if ( self::is_system_field( $field ) ) {
				continue;
			}

			$current_fields[] = [
				'key'         => $field['key']         ?? '',
				'label'       => $field['label']       ?? '',
				'type'        => $field['type']        ?? 'text',
				'pmp_default' => $field['pmp_default'] ?? $section_pmp_default,
				'filterable'  => (bool) ( $field['filterable']  ?? false ),
				'taxonomy'    => $field['taxonomy']    ?? null,
				'required'    => (bool) ( $field['required']    ?? false ),
			];
		}

		// Flush the last group.
		if ( ! empty( $current_fields ) ) {
			$groups[] = [ 'tab' => $current_tab, 'fields' => $current_fields ];
		}

		return $groups;
	}

	/**
	 * Return true if a field should be excluded from content field lists.
	 *
	 * Excludes:
	 *   - Tab markers (used for grouping, not rendering)
	 *   - button_group fields (used for the privacy_mode selector)
	 *   - Any field whose key contains a system key pattern (_enabled,
	 *     _privacy_mode, _privacy_level)
	 *   - Any field whose name is in the SKIP_NAMES list
	 *
	 * @param  array $field  A single field definition from acf_group.fields.
	 * @return bool
	 */
	private static function is_system_field( array $field ): bool {
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

	/**
	 * Return the content field keys from a section config.
	 *
	 * Used by validate_for_upload() to diff the incoming config against
	 * the currently stored config and detect removed field keys.
	 *
	 * Only non-system fields are included — system field keys (enabled,
	 * privacy_mode) are intentionally excluded because the Section Manager
	 * always regenerates them and they carry no member content data.
	 *
	 * @param  array $section  A section config array.
	 * @return string[]        Field key strings.
	 */
	private static function content_field_keys( array $section ): array {
		$keys = [];

		foreach ( $section['acf_group']['fields'] ?? [] as $field ) {
			if ( ! empty( $field['key'] ) && ! self::is_system_field( $field ) ) {
				$keys[] = $field['key'];
			}
		}

		return $keys;
	}
}
