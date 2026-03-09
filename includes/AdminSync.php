<?php
/**
 * Admin Sync Page.
 *
 * Adds a Settings sub-menu page that lets an administrator trigger
 * SectionRegistry::sync() — reading all JSON files from sections/ and
 * merging the results with the WordPress database.
 *
 * Also provides:
 *   - A section editor: rename, reorder, toggle can_be_primary, toggle always_on, delete.
 *     All mutable metadata lives in the DB only — JSON files are immutable.
 *   - An "Add Section" form for creating new section pointers by typing JSON.
 *
 * Usage (from Plugin.php):
 *   AdminSync::init();
 */

namespace MemberDirectory;

defined( 'ABSPATH' ) || exit;

class AdminSync {

	/** Nonce action used to validate the sync form submission. */
	const NONCE_ACTION = 'member_directory_sync';

	/** Nonce field name in the sync form. */
	const NONCE_FIELD = 'member_directory_sync_nonce';

	/** Nonce action used to validate the Add Section form submission. */
	const ADD_NONCE_ACTION = 'member_directory_add_section';

	/** Nonce field name in the Add Section form. */
	const ADD_NONCE_FIELD = 'member_directory_add_nonce';

	/** Nonce action used to validate section reorder submissions. */
	const REORDER_NONCE_ACTION = 'member_directory_reorder';

	/** Nonce field name in reorder forms. */
	const REORDER_NONCE_FIELD = 'member_directory_reorder_nonce';

	/** Nonce action used to validate section delete submissions. */
	const DELETE_NONCE_ACTION = 'member_directory_delete_section';

	/** Nonce field name in delete forms. */
	const DELETE_NONCE_FIELD = 'member_directory_delete_nonce';

	/** Nonce action used to validate can_be_primary toggle submissions. */
	const TOGGLE_PRIMARY_NONCE_ACTION = 'member_directory_toggle_primary';

	/** Nonce field name in can_be_primary toggle forms. */
	const TOGGLE_PRIMARY_NONCE_FIELD = 'member_directory_toggle_primary_nonce';

	/** Nonce action used to validate section label rename submissions. */
	const RENAME_NONCE_ACTION = 'member_directory_rename_section';

	/** Nonce field name in rename forms. */
	const RENAME_NONCE_FIELD = 'member_directory_rename_nonce';

	/** Nonce action used to validate default avatar upload/remove submissions. */
	const AVATAR_NONCE_ACTION = 'member_directory_default_avatar';

	/** Nonce field name in avatar upload/remove forms. */
	const AVATAR_NONCE_FIELD = 'member_directory_avatar_nonce';

	/** Nonce action used to validate default banner upload/remove submissions. */
	const BANNER_NONCE_ACTION = 'member_directory_default_banner';

	/** Nonce field name in banner upload/remove forms. */
	const BANNER_NONCE_FIELD = 'member_directory_banner_nonce';

	/** Nonce action used to validate directory config submissions. */
	const DIR_CONFIG_NONCE_ACTION = 'member_directory_dir_config';

	/** Nonce field name in directory config forms. */
	const DIR_CONFIG_NONCE_FIELD = 'member_directory_dir_config_nonce';

	/** Admin page slug registered with WordPress. */
	const PAGE_SLUG = 'member-directory-sync';

	/**
	 * Section key of the most recently saved or reordered section this
	 * request. Used to auto-open that section's <details> after a save.
	 */
	private static string $last_edited_key = '';

	/**
	 * Register the admin_menu hook.
	 * Called once from Plugin::init().
	 */
	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'register_page' ] );
	}

	/**
	 * Register the Settings sub-menu page.
	 * Fires on admin_menu.
	 */
	public static function register_page(): void {
		add_options_page(
			'Member Directory Sync',   // Page <title>
			'Member Directory Sync',   // Menu label
			'manage_options',          // Required capability
			self::PAGE_SLUG,           // Menu slug
			[ self::class, 'render' ]  // Callback
		);
	}

	/**
	 * Render the sync page.
	 *
	 * Handles both:
	 *   - GET  — displays the forms
	 *   - POST — processes whichever form was submitted, then displays results
	 */
	public static function render(): void {
		// Only administrators reach this page, but verify explicitly.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.' ) );
		}

		?>
		<div class="wrap">
			<h1>Member Directory</h1>

			<?php self::render_instructions(); ?>

			<p>Run Sync any time a <code>sections/</code> JSON file changes.
			ACF field group changes do <em>not</em> require a sync &mdash; they take effect immediately on the next page load.</p>

			<?php self::maybe_handle_submission(); ?>

			<form method="post" action="">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
				<?php submit_button( 'Run Sync', 'primary', 'submit', false ); ?>
			</form>

			<hr>
			<h2>Section Editor</h2>
			<p>Rename, reorder, toggle primary eligibility, or delete sections. All changes are saved to the database &mdash; JSON files are not modified.</p>

			<?php
			self::maybe_handle_reorder();
			self::maybe_handle_section_delete();
			self::maybe_handle_toggle_primary();
			self::maybe_handle_rename();
			self::maybe_handle_avatar_upload();
			self::maybe_handle_avatar_remove();
			self::maybe_handle_banner_upload();
			self::maybe_handle_banner_remove();
			self::render_section_editor();
			?>

			<hr>
			<h2>Add Section</h2>
			<p>Register a new section pointer. The plugin writes the JSON file to <code>sections/</code> and syncs immediately.</p>

			<?php self::maybe_handle_add_section(); ?>

			<form method="post" action="">
				<?php wp_nonce_field( self::ADD_NONCE_ACTION, self::ADD_NONCE_FIELD ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="add_section_name">Section Name</label></th>
						<td>
							<input type="text" id="add_section_name" name="add_section_name"
								class="regular-text" placeholder="e.g. Business">
							<p class="description">Display label. The section key is derived automatically (lowercased, underscores).</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="add_acf_group_key">ACF Group Key</label></th>
						<td>
							<input type="text" id="add_acf_group_key" name="add_acf_group_key"
								class="regular-text" placeholder="e.g. group_md_05_business">
							<p class="description">The <code>key</code> value from the ACF field group.</p>
						</td>
					</tr>
				</table>
				<p>
					<?php submit_button( 'Add Section', 'secondary', 'add_submit', false ); ?>
				</p>
			</form>

			<hr>
			<h2>Directory Settings</h2>
			<p>Configure the <code>[memdir_directory]</code> shortcode: card grid, search, taxonomy filters, and card display options.</p>

			<?php
			self::maybe_handle_directory_config();
			self::render_directory_settings();
			?>

			<hr>
			<h2>Claude Skill</h2>
			<p>Download the skill file and attach it to a Claude conversation to enrich a bare ACF field group export with the full iPMP apparatus — section system fields, per-field PMP companions, and auto-generated header fields (title, avatar, badge taxonomy, social icons).</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( plugin_dir_url( dirname( __FILE__ ) ) . 'tools/acf-field-prep.md' ); ?>" download>
					Download ACF Field Group Prep
				</a>
			</p>

		</div>
		<?php
	}

	// -----------------------------------------------------------------------
	// POST handlers
	// -----------------------------------------------------------------------

	/**
	 * If this is a valid sync form POST, run the sync and display results.
	 * Does nothing on a GET request or when another form was submitted.
	 */
	private static function maybe_handle_submission(): void {
		if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
			return;
		}

		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run this action.' ) );
		}

		$result = SectionRegistry::sync();

		self::render_results( $result );
	}

	/**
	 * If this is a valid reorder POST, swap the order values of two adjacent
	 * sections in the DB option and refresh the cache.
	 */
	private static function maybe_handle_reorder(): void {
		if ( ! isset( $_POST[ self::REORDER_NONCE_FIELD ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::REORDER_NONCE_FIELD ] ) ), self::REORDER_NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run this action.' ) );
		}

		$key = sanitize_key( $_POST['reorder_key'] ?? '' );
		$dir = ( ( $_POST['reorder_direction'] ?? '' ) === 'up' ) ? 'up' : 'down';

		$stored = get_option( SectionRegistry::OPTION_KEY, [] );
		$keys   = array_keys( $stored );

		$pos = array_search( $key, $keys, true );

		if ( $pos === false ) {
			return;
		}

		$swap_pos = ( $dir === 'up' ) ? $pos - 1 : $pos + 1;

		if ( $swap_pos < 0 || $swap_pos >= count( $keys ) ) {
			return;
		}

		// Swap the two keys in the ordered list.
		[ $keys[ $pos ], $keys[ $swap_pos ] ] = [ $keys[ $swap_pos ], $keys[ $pos ] ];

		// Rebuild the ordered sections array.
		$reordered = [];
		foreach ( $keys as $k ) {
			$reordered[ $k ] = $stored[ $k ];
		}

		update_option( SectionRegistry::OPTION_KEY, $reordered, false );

		// Refresh the in-memory cache so the editor reflects the new order.
		SectionRegistry::sync();

		self::$last_edited_key = $key;
		self::render_upload_result( true, 'Section order updated.' );
	}

	/**
	 * If this is a valid section delete POST, back up the file, delete it, and re-sync.
	 */
	private static function maybe_handle_section_delete(): void {
		if ( ! isset( $_POST[ self::DELETE_NONCE_FIELD ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::DELETE_NONCE_FIELD ] ) ), self::DELETE_NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run this action.' ) );
		}

		$section_key = sanitize_key( $_POST['delete_section_key'] ?? '' );

		if ( empty( $section_key ) ) {
			self::render_upload_result( false, 'No section key provided.' );
			return;
		}

		$sections_dir = SectionRegistry::sections_dir();
		$target_file  = $sections_dir . $section_key . '.json';

		if ( ! file_exists( $target_file ) ) {
			self::render_upload_result(
				false,
				'Section file <code>sections/' . esc_html( $section_key ) . '.json</code> not found.'
			);
			return;
		}

		$backup_note = self::backup_section_file( $section_key );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		if ( ! unlink( $target_file ) ) {
			self::render_upload_result(
				false,
				'Could not delete <code>sections/' . esc_html( $section_key ) . '.json</code>. Check server file permissions.'
			);
			return;
		}

		SectionRegistry::sync();

		self::render_upload_result(
			true,
			'Section <strong>' . esc_html( $section_key ) . '</strong> deleted.',
			$backup_note
		);
	}

	/**
	 * If this is a valid can_be_primary toggle POST, update the DB option directly.
	 * No file I/O — mutable metadata lives in the DB only.
	 */
	private static function maybe_handle_toggle_primary(): void {
		if ( ! isset( $_POST[ self::TOGGLE_PRIMARY_NONCE_FIELD ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::TOGGLE_PRIMARY_NONCE_FIELD ] ) ), self::TOGGLE_PRIMARY_NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run this action.' ) );
		}

		$section_key    = sanitize_key( $_POST['toggle_section_key'] ?? '' );
		$can_be_primary = isset( $_POST['can_be_primary'] ) && $_POST['can_be_primary'] === '1';
		$always_on      = isset( $_POST['always_on'] ) && $_POST['always_on'] === '1';

		if ( empty( $section_key ) ) {
			self::render_upload_result( false, 'No section key provided.' );
			return;
		}

		$stored = get_option( SectionRegistry::OPTION_KEY, [] );

		if ( ! isset( $stored[ $section_key ] ) ) {
			self::render_upload_result( false, 'Section <strong>' . esc_html( $section_key ) . '</strong> not found in database.' );
			return;
		}

		$stored[ $section_key ]['can_be_primary'] = $can_be_primary;
		$stored[ $section_key ]['always_on']      = $always_on;
		update_option( SectionRegistry::OPTION_KEY, $stored, false );

		// Refresh in-memory cache.
		SectionRegistry::load_from_db();

		self::$last_edited_key = $section_key;

		// Build a human-friendly summary of the current flags.
		$flags = [];
		if ( $can_be_primary ) { $flags[] = 'can be primary'; }
		if ( $always_on )      { $flags[] = 'always on'; }
		$summary = $flags ? implode( ', ', $flags ) : 'no flags';
		self::render_upload_result( true, 'Section flags updated for <strong>' . esc_html( $section_key ) . '</strong> (' . $summary . ').' );
	}

	/**
	 * If this is a valid rename POST, update the label in the DB option directly.
	 * No file I/O — mutable metadata lives in the DB only.
	 */
	private static function maybe_handle_rename(): void {
		if ( ! isset( $_POST[ self::RENAME_NONCE_FIELD ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::RENAME_NONCE_FIELD ] ) ), self::RENAME_NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run this action.' ) );
		}

		$section_key = sanitize_key( $_POST['rename_section_key'] ?? '' );
		$new_label   = sanitize_text_field( wp_unslash( $_POST['new_label'] ?? '' ) );

		if ( empty( $section_key ) || empty( $new_label ) ) {
			self::render_upload_result( false, 'Section key and new label are both required.' );
			return;
		}

		$stored = get_option( SectionRegistry::OPTION_KEY, [] );

		if ( ! isset( $stored[ $section_key ] ) ) {
			self::render_upload_result( false, 'Section <strong>' . esc_html( $section_key ) . '</strong> not found in database.' );
			return;
		}

		$old_label = $stored[ $section_key ]['label'] ?? $section_key;

		$stored[ $section_key ]['label'] = $new_label;
		update_option( SectionRegistry::OPTION_KEY, $stored, false );

		// Refresh in-memory cache.
		SectionRegistry::load_from_db();

		self::$last_edited_key = $section_key;

		self::render_upload_result(
			true,
			'Section renamed from <strong>' . esc_html( $old_label ) . '</strong> to <strong>' . esc_html( $new_label ) . '</strong>.'
		);
	}

	/**
	 * Handle default avatar upload for a section.
	 *
	 * One image in, one image out: uploads the file, stores the attachment ID
	 * in section metadata, and deletes the previous attachment if present.
	 */
	private static function maybe_handle_avatar_upload(): void {
		if ( ! isset( $_POST[ self::AVATAR_NONCE_FIELD ] ) || ! isset( $_POST['avatar_upload_key'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::AVATAR_NONCE_FIELD ] ) ), self::AVATAR_NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run this action.' ) );
		}

		$section_key = sanitize_key( $_POST['avatar_upload_key'] ?? '' );

		if ( empty( $section_key ) || empty( $_FILES['default_avatar_file'] ) || empty( $_FILES['default_avatar_file']['name'] ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		// Get old attachment ID before uploading.
		$section = SectionRegistry::get_section( $section_key );
		$old_id  = (int) ( $section['default_avatar'] ?? 0 );

		$attachment_id = media_handle_upload( 'default_avatar_file', 0 );

		if ( is_wp_error( $attachment_id ) ) {
			self::render_upload_result( false, 'Upload failed: ' . esc_html( $attachment_id->get_error_message() ) );
			return;
		}

		SectionRegistry::update_section_meta( $section_key, 'default_avatar', $attachment_id );

		// Delete old attachment (one in, one out).
		if ( $old_id && $old_id !== $attachment_id ) {
			wp_delete_attachment( $old_id, true );
		}

		self::$last_edited_key = $section_key;

		self::render_upload_result( true, 'Default avatar uploaded for <strong>' . esc_html( $section_key ) . '</strong>.' );
	}

	/**
	 * Handle default avatar removal for a section.
	 */
	private static function maybe_handle_avatar_remove(): void {
		if ( ! isset( $_POST[ self::AVATAR_NONCE_FIELD ] ) || ! isset( $_POST['avatar_remove_key'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::AVATAR_NONCE_FIELD ] ) ), self::AVATAR_NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run this action.' ) );
		}

		$section_key = sanitize_key( $_POST['avatar_remove_key'] ?? '' );

		if ( empty( $section_key ) ) {
			return;
		}

		$section = SectionRegistry::get_section( $section_key );
		$old_id  = (int) ( $section['default_avatar'] ?? 0 );

		SectionRegistry::update_section_meta( $section_key, 'default_avatar', null );

		if ( $old_id ) {
			wp_delete_attachment( $old_id, true );
		}

		self::$last_edited_key = $section_key;

		self::render_upload_result( true, 'Default avatar removed for <strong>' . esc_html( $section_key ) . '</strong>.' );
	}

	/**
	 * Handle default banner upload for a section.
	 *
	 * One image in, one image out: uploads the file, stores the attachment ID
	 * in section metadata, and deletes the previous attachment if present.
	 */
	private static function maybe_handle_banner_upload(): void {
		if ( ! isset( $_POST[ self::BANNER_NONCE_FIELD ] ) || ! isset( $_POST['banner_upload_key'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::BANNER_NONCE_FIELD ] ) ), self::BANNER_NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run this action.' ) );
		}

		$section_key = sanitize_key( $_POST['banner_upload_key'] ?? '' );

		if ( empty( $section_key ) || empty( $_FILES['default_banner_file'] ) || empty( $_FILES['default_banner_file']['name'] ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$section = SectionRegistry::get_section( $section_key );
		$old_id  = (int) ( $section['default_banner'] ?? 0 );

		$attachment_id = media_handle_upload( 'default_banner_file', 0 );

		if ( is_wp_error( $attachment_id ) ) {
			self::render_upload_result( false, 'Upload failed: ' . esc_html( $attachment_id->get_error_message() ) );
			return;
		}

		SectionRegistry::update_section_meta( $section_key, 'default_banner', $attachment_id );

		// Delete old attachment (one in, one out).
		if ( $old_id && $old_id !== $attachment_id ) {
			wp_delete_attachment( $old_id, true );
		}

		self::$last_edited_key = $section_key;

		self::render_upload_result( true, 'Default banner uploaded for <strong>' . esc_html( $section_key ) . '</strong>.' );
	}

	/**
	 * Handle default banner removal for a section.
	 */
	private static function maybe_handle_banner_remove(): void {
		if ( ! isset( $_POST[ self::BANNER_NONCE_FIELD ] ) || ! isset( $_POST['banner_remove_key'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::BANNER_NONCE_FIELD ] ) ), self::BANNER_NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run this action.' ) );
		}

		$section_key = sanitize_key( $_POST['banner_remove_key'] ?? '' );

		if ( empty( $section_key ) ) {
			return;
		}

		$section = SectionRegistry::get_section( $section_key );
		$old_id  = (int) ( $section['default_banner'] ?? 0 );

		SectionRegistry::update_section_meta( $section_key, 'default_banner', null );

		if ( $old_id ) {
			wp_delete_attachment( $old_id, true );
		}

		self::$last_edited_key = $section_key;

		self::render_upload_result( true, 'Default banner removed for <strong>' . esc_html( $section_key ) . '</strong>.' );
	}

	/**
	 * If this is a valid Add Section POST, validate, write the JSON file, and sync.
	 */
	private static function maybe_handle_add_section(): void {
		if ( ! isset( $_POST[ self::ADD_NONCE_FIELD ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::ADD_NONCE_FIELD ] ) ), self::ADD_NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run this action.' ) );
		}

		$label         = sanitize_text_field( wp_unslash( $_POST['add_section_name'] ?? '' ) );
		$acf_group_key = sanitize_text_field( wp_unslash( $_POST['add_acf_group_key'] ?? '' ) );

		if ( empty( $label ) ) {
			self::render_upload_result( false, 'Section name is required.' );
			return;
		}

		if ( empty( $acf_group_key ) ) {
			self::render_upload_result( false, 'ACF Group Key is required.' );
			return;
		}

		// Derive key from label: lowercase, underscores.
		$section_key = str_replace( '-', '_', sanitize_key( $label ) );
		$sections_dir  = SectionRegistry::sections_dir();
		$target_file   = $sections_dir . $section_key . '.json';

		// Check if section already exists.
		if ( file_exists( $target_file ) ) {
			self::render_upload_result(
				false,
				'Section <strong>' . esc_html( $section_key ) . '</strong> already exists. Delete it first if you want to recreate it.'
			);
			return;
		}

		// Write the lean JSON file (only acf_group_key — key comes from filename).
		$lean_config = [ 'acf_group_key' => $acf_group_key ];
		$pretty_raw  = (string) json_encode( $lean_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( file_put_contents( $target_file, $pretty_raw . "\n" ) === false ) {
			self::render_upload_result(
				false,
				'Could not write to <code>sections/' . esc_html( $section_key ) . '.json</code>. Check server file permissions.'
			);
			return;
		}

		// Store the label in the DB before syncing so sync preserves it.
		$stored = get_option( SectionRegistry::OPTION_KEY, [] );
		$stored[ $section_key ] = [
			'key'            => $section_key,
			'label'          => $label,
			'can_be_primary' => false,
			'always_on'      => false,
			'acf_group_key'  => $acf_group_key,
		];
		update_option( SectionRegistry::OPTION_KEY, $stored, false );

		$sync_result = SectionRegistry::sync();

		self::render_upload_result(
			true,
			'Section <strong>' . esc_html( $section_key ) . '</strong> added and synced successfully.'
		);
	}

	// -----------------------------------------------------------------------
	// Directory Settings
	// -----------------------------------------------------------------------

	/**
	 * Handle POST for directory config save.
	 */
	private static function maybe_handle_directory_config(): void {
		if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
			return;
		}
		if ( ! isset( $_POST[ self::DIR_CONFIG_NONCE_FIELD ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::DIR_CONFIG_NONCE_FIELD ] ) ), self::DIR_CONFIG_NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed.' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.' ) );
		}

		$config = Directory::get_config();

		// Handle re-detect button.
		if ( ! empty( $_POST['dir_redetect'] ) ) {
			$detected = Directory::detect_taxonomies();

			// Merge with existing filters: preserve enabled/label for known taxonomies.
			$existing_by_tax = [];
			foreach ( $config['filters'] as $f ) {
				$existing_by_tax[ $f['taxonomy'] ] = $f;
			}

			$merged = [];
			foreach ( $detected as $d ) {
				if ( isset( $existing_by_tax[ $d['taxonomy'] ] ) ) {
					// Preserve admin customizations.
					$existing          = $existing_by_tax[ $d['taxonomy'] ];
					$existing['field_key']   = $d['field_key'];
					$existing['section_key'] = $d['section_key'];
					$merged[] = $existing;
				} else {
					$merged[] = $d;
				}
			}

			$config['filters'] = $merged;
			Directory::update_config( $config );

			self::render_upload_result( true, 'Taxonomy filters re-detected. Found ' . count( $merged ) . ' taxonomy field(s).' );
			return;
		}

		// Handle filter reorder.
		if ( ! empty( $_POST['dir_filter_move'] ) && ! empty( $_POST['dir_filter_direction'] ) ) {
			$move_tax  = sanitize_text_field( wp_unslash( $_POST['dir_filter_move'] ) );
			$direction = sanitize_text_field( wp_unslash( $_POST['dir_filter_direction'] ) );
			$filters   = $config['filters'];
			$idx       = null;

			foreach ( $filters as $i => $f ) {
				if ( ( $f['taxonomy'] ?? '' ) === $move_tax ) {
					$idx = $i;
					break;
				}
			}

			if ( $idx !== null ) {
				$swap = ( $direction === 'up' ) ? $idx - 1 : $idx + 1;
				if ( $swap >= 0 && $swap < count( $filters ) ) {
					[ $filters[ $idx ], $filters[ $swap ] ] = [ $filters[ $swap ], $filters[ $idx ] ];
					$config['filters'] = array_values( $filters );
					Directory::update_config( $config );
				}
			}
			return;
		}

		// General settings save.
		$config['directory_page_id']  = max( 0, (int) ( $_POST['dir_directory_page_id'] ?? 0 ) );
		$config['per_page']           = max( 1, (int) ( $_POST['dir_per_page'] ?? 12 ) );
		$config['default_sort']       = sanitize_text_field( wp_unslash( $_POST['dir_default_sort'] ?? 'title' ) );
		$config['sort_order']         = sanitize_text_field( wp_unslash( $_POST['dir_sort_order'] ?? 'ASC' ) );
		$config['search_enabled']     = ! empty( $_POST['dir_search_enabled'] );
		$config['search_placeholder'] = sanitize_text_field( wp_unslash( $_POST['dir_search_placeholder'] ?? 'Search members...' ) );
		$pin_style = sanitize_text_field( wp_unslash( $_POST['dir_map_pin_style'] ?? 'circle' ) );
		$config['map_pin_style'] = in_array( $pin_style, [ 'circle', 'pin', 'avatar' ], true ) ? $pin_style : 'circle';

		// Card display toggles.
		$config['card']['show_avatar']   = ! empty( $_POST['dir_show_avatar'] );
		$config['card']['show_banner']   = ! empty( $_POST['dir_show_banner'] );
		$config['card']['show_badges']   = ! empty( $_POST['dir_show_badges'] );
		$config['card']['show_location'] = ! empty( $_POST['dir_show_location'] );
		$config['card']['show_social']   = ! empty( $_POST['dir_show_social'] );

		// Filter enabled/label updates — only when filter form data is present.
		// The filter checkboxes live in per-row forms inside the Taxonomy Filters
		// panel, not in the General & Card Display form. Without this guard,
		// saving general settings would wipe all filter enabled states.
		if ( isset( $_POST['dir_filter_label'] ) ) {
			$filter_enabled = $_POST['dir_filter_enabled'] ?? [];
			$filter_labels  = $_POST['dir_filter_label']   ?? [];

			foreach ( $config['filters'] as &$f ) {
				$tax = $f['taxonomy'] ?? '';
				$f['enabled'] = ! empty( $filter_enabled[ $tax ] );
				if ( isset( $filter_labels[ $tax ] ) ) {
					$f['label'] = sanitize_text_field( wp_unslash( $filter_labels[ $tax ] ) );
				}
			}
			unset( $f );
		}

		Directory::update_config( $config );

		self::render_upload_result( true, 'Directory settings saved.' );
	}

	/**
	 * Render the Directory Settings UI.
	 */
	private static function render_directory_settings(): void {
		$config = Directory::get_config();
		$card   = $config['card'] ?? [];

		?>
		<details style="margin-bottom:8px;border:1px solid #ddd;border-radius:3px;">
			<summary style="padding:10px 14px;cursor:pointer;background:#f6f7f7;font-weight:600;list-style:none;">
				General &amp; Card Display
			</summary>
			<div style="padding:14px;">
				<form method="post" action="">
					<?php wp_nonce_field( self::DIR_CONFIG_NONCE_ACTION, self::DIR_CONFIG_NONCE_FIELD ); ?>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="dir_directory_page_id">Directory page</label></th>
							<td>
								<?php
								wp_dropdown_pages( [
									'name'              => 'dir_directory_page_id',
									'id'                => 'dir_directory_page_id',
									'selected'          => (int) ( $config['directory_page_id'] ?? 0 ),
									'show_option_none'  => '&mdash; Select page &mdash;',
									'option_none_value' => '0',
								] );
								?>
								<p class="description">Page with the <code>[memdir_directory]</code> shortcode. Taxonomy terms on profiles will link here.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dir_per_page">Cards per page</label></th>
							<td><input type="number" id="dir_per_page" name="dir_per_page" value="<?php echo esc_attr( (string) $config['per_page'] ); ?>" min="1" max="100" style="width:80px;"></td>
						</tr>
						<tr>
							<th scope="row"><label for="dir_default_sort">Default sort</label></th>
							<td>
								<select id="dir_default_sort" name="dir_default_sort">
									<?php foreach ( [ 'title' => 'Title', 'date' => 'Date created', 'modified' => 'Date modified', 'rand' => 'Random' ] as $v => $l ) : ?>
										<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $config['default_sort'], $v ); ?>><?php echo esc_html( $l ); ?></option>
									<?php endforeach; ?>
								</select>
								<select name="dir_sort_order" style="margin-left:4px;">
									<option value="ASC" <?php selected( $config['sort_order'], 'ASC' ); ?>>A-Z / Oldest</option>
									<option value="DESC" <?php selected( $config['sort_order'], 'DESC' ); ?>>Z-A / Newest</option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">Search</th>
							<td>
								<label><input type="checkbox" name="dir_search_enabled" value="1" <?php checked( ! empty( $config['search_enabled'] ) ); ?>> Enable search bar</label>
								<br>
								<input type="text" name="dir_search_placeholder" value="<?php echo esc_attr( $config['search_placeholder'] ); ?>" style="margin-top:4px;width:260px;" placeholder="Search placeholder text">
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dir_map_pin_style">Map pin style</label></th>
							<td>
								<select id="dir_map_pin_style" name="dir_map_pin_style">
									<?php
									$pin_style = $config['map_pin_style'] ?? 'circle';
									foreach ( [ 'circle' => 'Circle marker (sage green)', 'pin' => 'Default pin', 'avatar' => 'Avatar photos (with clustering)' ] as $v => $l ) :
									?>
										<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $pin_style, $v ); ?>><?php echo esc_html( $l ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description">How members appear on the map. "Avatar photos" groups nearby members into clusters at low zoom.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Card display</th>
							<td>
								<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="dir_show_avatar" value="1" <?php checked( ! empty( $card['show_avatar'] ) ); ?>> Show avatar</label>
								<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="dir_show_banner" value="1" <?php checked( ! empty( $card['show_banner'] ) ); ?>> Show banner</label>
								<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="dir_show_badges" value="1" <?php checked( ! empty( $card['show_badges'] ) ); ?>> Show badges</label>
								<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="dir_show_location" value="1" <?php checked( ! empty( $card['show_location'] ) ); ?>> Show location</label>
								<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="dir_show_social" value="1" <?php checked( ! empty( $card['show_social'] ) ); ?>> Show social icons</label>
							</td>
						</tr>
					</table>

					<?php submit_button( 'Save Settings', 'primary', 'dir_save', false ); ?>
				</form>
			</div>
		</details>

		<details style="margin-bottom:8px;border:1px solid #ddd;border-radius:3px;">
			<summary style="padding:10px 14px;cursor:pointer;background:#f6f7f7;font-weight:600;list-style:none;">
				Taxonomy Filters
			</summary>
			<div style="padding:14px;">
				<?php
				$filters = $config['filters'];

				if ( empty( $filters ) ) {
					echo '<p style="color:#666;">No taxonomy filters detected. Click <strong>Re-detect Taxonomies</strong> to scan your ACF field groups.</p>';
				} else {
					echo '<p style="font-size:12px;color:#666;margin-bottom:12px;">Enable, rename, or reorder taxonomy filters. Changes are saved with the general settings above.</p>';

					$filter_count = count( $filters );

					foreach ( $filters as $i => $filter ) {
						$tax     = $filter['taxonomy'] ?? '';
						$label   = $filter['label']    ?? $tax;
						$enabled = ! empty( $filter['enabled'] );
						$is_first = ( $i === 0 );
						$is_last  = ( $i === $filter_count - 1 );

						echo '<div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #eee;">';

						// Reorder arrows.
						echo '<span style="display:flex;flex-direction:column;gap:2px;">';

						if ( ! $is_first ) {
							echo '<form method="post" action="" style="margin:0;">';
							wp_nonce_field( self::DIR_CONFIG_NONCE_ACTION, self::DIR_CONFIG_NONCE_FIELD );
							echo '<input type="hidden" name="dir_filter_move" value="' . esc_attr( $tax ) . '">';
							echo '<input type="hidden" name="dir_filter_direction" value="up">';
							echo '<button type="submit" style="padding:0 4px;line-height:1.2;cursor:pointer;" title="Move up">&#9650;</button>';
							echo '</form>';
						} else {
							echo '<span style="padding:0 4px;opacity:0.2;">&#9650;</span>';
						}

						if ( ! $is_last ) {
							echo '<form method="post" action="" style="margin:0;">';
							wp_nonce_field( self::DIR_CONFIG_NONCE_ACTION, self::DIR_CONFIG_NONCE_FIELD );
							echo '<input type="hidden" name="dir_filter_move" value="' . esc_attr( $tax ) . '">';
							echo '<input type="hidden" name="dir_filter_direction" value="down">';
							echo '<button type="submit" style="padding:0 4px;line-height:1.2;cursor:pointer;" title="Move down">&#9660;</button>';
							echo '</form>';
						} else {
							echo '<span style="padding:0 4px;opacity:0.2;">&#9660;</span>';
						}

						echo '</span>';

						// The enabled/label fields are part of the general settings form above,
						// but since they're in a separate <details>, we render standalone mini-forms.
						echo '<form method="post" action="" style="margin:0;display:flex;align-items:center;gap:8px;flex:1;">';
						wp_nonce_field( self::DIR_CONFIG_NONCE_ACTION, self::DIR_CONFIG_NONCE_FIELD );
						// Pass through all current general settings as hidden fields.
						echo '<input type="hidden" name="dir_directory_page_id" value="' . esc_attr( (string) ( $config['directory_page_id'] ?? 0 ) ) . '">';
						echo '<input type="hidden" name="dir_per_page" value="' . esc_attr( (string) $config['per_page'] ) . '">';
						echo '<input type="hidden" name="dir_default_sort" value="' . esc_attr( $config['default_sort'] ) . '">';
						echo '<input type="hidden" name="dir_sort_order" value="' . esc_attr( $config['sort_order'] ) . '">';
						if ( ! empty( $config['search_enabled'] ) ) {
							echo '<input type="hidden" name="dir_search_enabled" value="1">';
						}
						echo '<input type="hidden" name="dir_search_placeholder" value="' . esc_attr( $config['search_placeholder'] ) . '">';
						// Card display hidden fields.
						foreach ( [ 'show_avatar', 'show_banner', 'show_badges', 'show_location', 'show_social' ] as $ckey ) {
							if ( ! empty( $card[ $ckey ] ) ) {
								echo '<input type="hidden" name="dir_' . esc_attr( $ckey ) . '" value="1">';
							}
						}
						// All other filters' enabled/label as hidden.
						foreach ( $filters as $of ) {
							$otax = $of['taxonomy'] ?? '';
							if ( $otax === $tax ) {
								continue;
							}
							if ( ! empty( $of['enabled'] ) ) {
								echo '<input type="hidden" name="dir_filter_enabled[' . esc_attr( $otax ) . ']" value="1">';
							}
							echo '<input type="hidden" name="dir_filter_label[' . esc_attr( $otax ) . ']" value="' . esc_attr( $of['label'] ?? '' ) . '">';
						}

						echo '<label style="display:flex;align-items:center;gap:4px;cursor:pointer;">';
						echo '<input type="checkbox" name="dir_filter_enabled[' . esc_attr( $tax ) . ']" value="1"'
							. checked( $enabled, true, false )
							. ' onchange="this.form.submit();">';
						echo '</label>';

						echo '<input type="text" name="dir_filter_label[' . esc_attr( $tax ) . ']" value="' . esc_attr( $label ) . '" style="width:160px;font-size:13px;">';
						echo '<code style="font-size:11px;color:#999;">' . esc_html( $tax ) . '</code>';

						echo '<button type="submit" class="button button-small" style="margin-left:auto;">Save</button>';
						echo '</form>';

						echo '</div>';
					}
				}
				?>

				<div style="margin-top:12px;">
					<form method="post" action="" style="display:inline;">
						<?php wp_nonce_field( self::DIR_CONFIG_NONCE_ACTION, self::DIR_CONFIG_NONCE_FIELD ); ?>
						<input type="hidden" name="dir_redetect" value="1">
						<?php submit_button( 'Re-detect Taxonomies', 'secondary', 'dir_redetect_btn', false ); ?>
					</form>
				</div>
			</div>
		</details>
		<?php
	}

	// -----------------------------------------------------------------------
	// Render helpers
	// -----------------------------------------------------------------------

	/**
	 * Render the section editor: a sorted list of <details> elements, one per
	 * section, each with rename, reorder, toggle primary, and delete controls.
	 */
	private static function render_section_editor(): void {
		$sections = SectionRegistry::get_sections();

		if ( empty( $sections ) ) {
			echo '<p>No sections are currently synced. Add a section below or run Sync to load sections from the <code>sections/</code> folder.</p>';
			return;
		}

		$count = count( $sections );

		foreach ( $sections as $i => $section ) {
			$key            = $section['key']   ?? '';
			$label          = $section['label'] ?? $key;
			$acf_group_key  = $section['acf_group_key'] ?? '';
			$can_be_primary = ! empty( $section['can_be_primary'] );
			$is_first       = ( $i === 0 );
			$is_last        = ( $i === $count - 1 );
			$open_attr      = ( $key === self::$last_edited_key ) ? ' open' : '';

			echo '<details' . esc_attr( $open_attr ) . ' style="margin-bottom:8px;border:1px solid #ddd;border-radius:3px;">';

			// --- Summary row ------------------------------------------------
			echo '<summary style="padding:10px 14px;cursor:pointer;display:flex;align-items:center;gap:10px;background:#f6f7f7;list-style:none;">';

			// Up/Down reorder buttons.
			echo '<span style="display:flex;flex-direction:column;gap:2px;">';

			if ( ! $is_first ) {
				echo '<form method="post" action="" style="margin:0;">';
				wp_nonce_field( self::REORDER_NONCE_ACTION, self::REORDER_NONCE_FIELD );
				echo '<input type="hidden" name="reorder_key" value="' . esc_attr( $key ) . '">';
				echo '<input type="hidden" name="reorder_direction" value="up">';
				echo '<button type="submit" style="padding:0 4px;line-height:1.4;cursor:pointer;" title="Move up">&#9650;</button>';
				echo '</form>';
			} else {
				echo '<span style="padding:0 4px;opacity:0.2;">&#9650;</span>';
			}

			if ( ! $is_last ) {
				echo '<form method="post" action="" style="margin:0;">';
				wp_nonce_field( self::REORDER_NONCE_ACTION, self::REORDER_NONCE_FIELD );
				echo '<input type="hidden" name="reorder_key" value="' . esc_attr( $key ) . '">';
				echo '<input type="hidden" name="reorder_direction" value="down">';
				echo '<button type="submit" style="padding:0 4px;line-height:1.4;cursor:pointer;" title="Move down">&#9660;</button>';
				echo '</form>';
			} else {
				echo '<span style="padding:0 4px;opacity:0.2;">&#9660;</span>';
			}

			echo '</span>';

			// Section label + key.
			echo '<strong>' . esc_html( $label ) . '</strong>';
			echo '<code style="font-size:12px;">' . esc_html( $key ) . '</code>';

			// Section flags — auto-submit on change; stops summary click propagation.
			$always_on = ! empty( $section['always_on'] );
			echo '<form method="post" action="" style="margin-left:auto;display:flex;align-items:center;gap:12px;" onclick="event.stopPropagation();">';
			wp_nonce_field( self::TOGGLE_PRIMARY_NONCE_ACTION, self::TOGGLE_PRIMARY_NONCE_FIELD );
			echo '<input type="hidden" name="toggle_section_key" value="' . esc_attr( $key ) . '">';
			echo '<label style="display:flex;align-items:center;gap:4px;font-size:12px;color:#555;cursor:pointer;user-select:none;" onclick="event.stopPropagation();">';
			echo '<input type="checkbox" name="can_be_primary" value="1"'
				. checked( $can_be_primary, true, false )
				. ' onchange="this.form.submit();" onclick="event.stopPropagation();">';
			echo 'Can be primary';
			echo '</label>';
			echo '<label style="display:flex;align-items:center;gap:4px;font-size:12px;color:#555;cursor:pointer;user-select:none;" onclick="event.stopPropagation();">';
			echo '<input type="checkbox" name="always_on" value="1"'
				. checked( $always_on, true, false )
				. ' onchange="this.form.submit();" onclick="event.stopPropagation();">';
			echo 'Always on';
			echo '</label>';
			echo '</form>';

			echo '</summary>';

			// --- Detail content ---------------------------------------------
			echo '<div style="padding:14px;">';

			// Read-only info.
			echo '<div style="margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #eee;">';
			echo '<span style="font-size:12px;color:#666;">ACF Group Key: <code>' . esc_html( $acf_group_key ) . '</code></span>';
			echo '</div>';

			// Default avatar.
			$avatar_id  = (int) ( $section['default_avatar'] ?? 0 );
			$avatar_url = $avatar_id ? wp_get_attachment_image_url( $avatar_id, 'thumbnail' ) : '';

			echo '<div style="margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #eee;">';
			echo '<label style="font-weight:600;white-space:nowrap;font-size:13px;display:block;margin-bottom:6px;">Default Avatar:</label>';

			if ( $avatar_url ) {
				echo '<div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">';
				echo '<img src="' . esc_url( $avatar_url ) . '" style="width:64px;height:64px;border-radius:50%;object-fit:cover;border:1px solid #ddd;">';
				echo '<form method="post" action="" style="display:inline;" onclick="event.stopPropagation();">';
				wp_nonce_field( self::AVATAR_NONCE_ACTION, self::AVATAR_NONCE_FIELD );
				echo '<input type="hidden" name="avatar_remove_key" value="' . esc_attr( $key ) . '">';
				echo '<button type="submit" class="button" style="color:#b94a00;border-color:#b94a00;" '
					. 'onclick="return confirm(\'Remove the default avatar for this section?\')">Remove</button>';
				echo '</form>';
				echo '</div>';
			} else {
				echo '<p style="font-size:12px;color:#999;margin:0 0 6px;">None set.</p>';
			}

			echo '<form method="post" action="" enctype="multipart/form-data" style="display:flex;align-items:center;gap:6px;" onclick="event.stopPropagation();">';
			wp_nonce_field( self::AVATAR_NONCE_ACTION, self::AVATAR_NONCE_FIELD );
			echo '<input type="hidden" name="avatar_upload_key" value="' . esc_attr( $key ) . '">';
			echo '<input type="file" name="default_avatar_file" accept="image/*" style="font-size:12px;">';
			submit_button( 'Upload', 'small', 'avatar_upload_' . esc_attr( $key ), false );
			echo '</form>';
			echo '</div>';

			// Default banner.
			$banner_id  = (int) ( $section['default_banner'] ?? 0 );
			$banner_url = $banner_id ? wp_get_attachment_image_url( $banner_id, 'medium' ) : '';

			echo '<div style="margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #eee;">';
			echo '<label style="font-weight:600;white-space:nowrap;font-size:13px;display:block;margin-bottom:6px;">Default Banner:</label>';

			if ( $banner_url ) {
				echo '<div style="margin-bottom:8px;">';
				echo '<img src="' . esc_url( $banner_url ) . '" style="width:100%;max-width:320px;height:80px;object-fit:cover;border-radius:4px;border:1px solid #ddd;display:block;margin-bottom:8px;">';
				echo '<form method="post" action="" style="display:inline;" onclick="event.stopPropagation();">';
				wp_nonce_field( self::BANNER_NONCE_ACTION, self::BANNER_NONCE_FIELD );
				echo '<input type="hidden" name="banner_remove_key" value="' . esc_attr( $key ) . '">';
				echo '<button type="submit" class="button" style="color:#b94a00;border-color:#b94a00;" '
					. 'onclick="return confirm(\'Remove the default banner for this section?\')">Remove</button>';
				echo '</form>';
				echo '</div>';
			} else {
				echo '<p style="font-size:12px;color:#999;margin:0 0 6px;">None set.</p>';
			}

			echo '<form method="post" action="" enctype="multipart/form-data" style="display:flex;align-items:center;gap:6px;" onclick="event.stopPropagation();">';
			wp_nonce_field( self::BANNER_NONCE_ACTION, self::BANNER_NONCE_FIELD );
			echo '<input type="hidden" name="banner_upload_key" value="' . esc_attr( $key ) . '">';
			echo '<input type="file" name="default_banner_file" accept="image/*" style="font-size:12px;">';
			submit_button( 'Upload', 'small', 'banner_upload_' . esc_attr( $key ), false );
			echo '</form>';
			echo '</div>';

			// Rename form.
			echo '<div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #eee;">';
			echo '<label style="font-weight:600;white-space:nowrap;font-size:13px;">Section Label:</label>';
			echo '<form method="post" action="" style="display:flex;gap:6px;flex:1;" onclick="event.stopPropagation();">';
			wp_nonce_field( self::RENAME_NONCE_ACTION, self::RENAME_NONCE_FIELD );
			echo '<input type="hidden" name="rename_section_key" value="' . esc_attr( $key ) . '">';
			echo '<input type="text" name="new_label" value="' . esc_attr( $label ) . '" style="flex:1;max-width:320px;">';
			submit_button( 'Rename', 'small', 'rename_submit_' . esc_attr( $key ), false );
			echo '</form>';
			echo '</div>';

			// --- Delete form ------------------------------------------------
			echo '<div>';
			echo '<form method="post" action="" style="display:inline;">';
			wp_nonce_field( self::DELETE_NONCE_ACTION, self::DELETE_NONCE_FIELD );
			echo '<input type="hidden" name="delete_section_key" value="' . esc_attr( $key ) . '">';
			echo '<button type="submit" class="button" style="color:#b94a00;border-color:#b94a00;" '
				. 'onclick="return confirm(' . esc_attr( json_encode( 'Delete the ' . $label . ' section? The file will be backed up before deletion.' ) ) . ')">'
				. 'Delete Section</button>';
			echo '</form>';
			echo '</div>';

			echo '</div>';

			echo '</details>';
		}
	}

	/**
	 * Back up the existing section file (if it exists) to sections/backups/.
	 * Returns a human-readable note string describing what was backed up,
	 * including a warning if the backup count exceeds three.
	 *
	 * @param  string $section_key  The section key (also the filename stem).
	 * @return string  Backup note, or empty string if no existing file was found.
	 */
	private static function backup_section_file( string $section_key ): string {
		$sections_dir = SectionRegistry::sections_dir();
		$target_file  = $sections_dir . $section_key . '.json';

		if ( ! file_exists( $target_file ) ) {
			return '';
		}

		$backups_dir = $sections_dir . 'backups/';
		wp_mkdir_p( $backups_dir );

		$date        = gmdate( 'Y-m-d' );
		$backup_name = $section_key . '_' . $date . '.json';
		$backup_path = $backups_dir . $backup_name;
		$suffix      = 2;

		while ( file_exists( $backup_path ) ) {
			$backup_name = $section_key . '_' . $date . '_' . $suffix . '.json';
			$backup_path = $backups_dir . $backup_name;
			$suffix++;
		}

		copy( $target_file, $backup_path );

		$all_backups  = glob( $backups_dir . $section_key . '_*.json' );
		$backup_count = $all_backups ? count( $all_backups ) : 0;
		$note         = 'Previous config backed up to <code>sections/backups/' . esc_html( $backup_name ) . '</code>.';

		if ( $backup_count > 3 ) {
			$note .= ' You now have ' . esc_html( (string) $backup_count )
				. " backups for '" . esc_html( $section_key )
				. "' &mdash; consider deleting the oldest to stay within the 3-file limit.";
		}

		return $note;
	}

	/**
	 * Render a single operation result banner.
	 *
	 * @param bool   $success  True for success (green), false for failure (red).
	 * @param string $message  Primary message — may contain safe HTML.
	 * @param string $detail   Optional secondary line — may contain safe HTML.
	 */
	private static function render_upload_result( bool $success, string $message, string $detail = '' ): void {
		$color = $success ? '#1a7a1a' : '#b94a00';
		$icon  = $success ? '&#10003;' : '&#10007;';

		echo '<div style="margin:12px 0;padding:10px 14px;border-left:4px solid '
			. esc_attr( $color ) . ';background:#f9f9f9;">';
		echo '<p style="margin:0;color:' . esc_attr( $color ) . ';">'
			. $icon . ' ' . wp_kses_post( $message ) . '</p>';
		if ( $detail ) {
			echo '<p style="margin:6px 0 0;font-size:12px;color:#555;">'
				. wp_kses_post( $detail ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Render the sync result summary.
	 *
	 * @param array{loaded: string[], skipped: array<string, string>} $result
	 */
	private static function render_results( array $result ): void {
		$loaded  = $result['loaded']  ?? [];
		$skipped = $result['skipped'] ?? [];

		echo '<hr>';
		echo '<h2>Sync Results</h2>';

		if ( empty( $loaded ) && empty( $skipped ) ) {
			echo '<p>No JSON files found in the <code>sections/</code> folder.</p>';
			echo '<hr>';
			return;
		}

		if ( ! empty( $loaded ) ) {
			echo '<p><strong>Loaded successfully:</strong></p>';
			echo '<ul>';
			foreach ( $loaded as $filename ) {
				echo '<li style="color:#1a7a1a;">&#10003; ' . esc_html( $filename ) . '</li>';
			}
			echo '</ul>';
		}

		if ( ! empty( $skipped ) ) {
			echo '<p><strong>Skipped (errors):</strong></p>';
			echo '<ul>';
			foreach ( $skipped as $filename => $reason ) {
				echo '<li style="color:#b94a00;">&#10007; '
					. esc_html( $filename )
					. ' &mdash; '
					. esc_html( $reason )
					. '</li>';
			}
			echo '</ul>';
		}

		$total         = count( $loaded );
		$skipped_count = count( $skipped );
		echo '<p>'
			. esc_html( $total ) . ' section' . ( $total === 1 ? '' : 's' ) . ' loaded, '
			. esc_html( $skipped_count ) . ' skipped.'
			. '</p>';

		echo '<hr>';
	}

	/* ── Plugin Guide (collapsible instructions) ───────────────────── */

	/**
	 * Render a collapsible "Plugin Guide" panel at the top of the admin page.
	 *
	 * Covers all major plugin features: onboarding, sections, headers,
	 * conditional tabs, PMP, trust network. Starts closed so experienced
	 * admins aren't slowed down.
	 */
	private static function render_instructions(): void {
		?>
		<details style="margin:12px 0 20px; border:1px solid #c3c4c7; border-radius:4px; background:#f6f7f7;">
			<summary style="padding:12px 16px; cursor:pointer; font-size:14px; font-weight:600; color:#1d2327; list-style:none; display:flex; align-items:center; gap:8px;">
				<span class="dashicons dashicons-book" style="color:#2271b1; font-size:18px;"></span>
				Plugin Guide
				<span style="font-weight:400; color:#787c82; font-size:12px; margin-left:4px;">(click to expand)</span>
			</summary>

			<div style="padding:4px 20px 16px; border-top:1px solid #c3c4c7; background:#fff;">

				<h4 style="margin:16px 0 6px; font-size:13px; color:#1d2327;">Quick Start</h4>
				<p style="margin:0 0 4px; font-size:13px; color:#50575e;">
					Place the <code>[memdir_onboarding]</code> shortcode on any page. New members see a form to choose their
					primary section and profile URL &mdash; on submit a member-directory post is created, only
					the primary + &ldquo;always on&rdquo; sections are enabled, and they land on their profile in edit mode.
					Existing members who visit the page are redirected straight to their profile.
					Logged-out visitors are handled by BuddyBoss&rsquo;s login redirect.
				</p>

				<h4 style="margin:16px 0 6px; font-size:13px; color:#1d2327;">How Sections Work</h4>
				<p style="margin:0 0 4px; font-size:13px; color:#50575e;">
					Each section maps to one ACF field group. A small JSON pointer file in <code>sections/</code>
					(containing only the ACF group key) links the two. Mutable metadata &mdash; label, display order,
					flags &mdash; is managed here in the <strong>Section Editor</strong> below and stored in the database.
					ACF field changes take effect immediately on save &mdash; no sync needed.
					Only run <em>Sync</em> when a JSON file is added, removed, or edited.
				</p>

				<h4 style="margin:16px 0 6px; font-size:13px; color:#1d2327;">Section Flags</h4>
				<ul style="margin:0 0 4px; padding-left:20px; font-size:13px; color:#50575e;">
					<li><strong>Can be primary</strong> &mdash; The section appears as an option in the onboarding form
						and can be set as the member&rsquo;s featured/primary section.</li>
					<li><strong>Always on</strong> &mdash; Auto-enabled for every new member regardless of which primary
						they choose. Use this for sections all members should have (e.g.&nbsp;Profile).</li>
				</ul>

				<h4 style="margin:16px 0 6px; font-size:13px; color:#1d2327;">Header Auto-Detection</h4>
				<p style="margin:0 0 4px; font-size:13px; color:#50575e;">
					Name any ACF tab with <strong>&ldquo;header&rdquo;</strong> in the label (e.g.&nbsp;&ldquo;Header&rdquo;,
					&ldquo;Header &mdash; Edit Me&rdquo;). Fields under that tab auto-populate the sticky header:
				</p>
				<ul style="margin:0 0 4px; padding-left:20px; font-size:13px; color:#50575e;">
					<li>First <strong>text</strong> field &rarr; profile title</li>
					<li><strong>Image</strong> fields &rarr; avatar or banner (matched by field name suffix):
						<ul style="margin:4px 0 2px; padding-left:16px;">
							<li><strong>Avatar</strong> (circular): <code>_photo</code>, <code>_avatar</code>,
								<code>_headshot</code>, <code>_portrait</code></li>
							<li><strong>Banner</strong> (full-width): <code>_banner</code>, <code>_cover</code>,
								<code>_header_image</code></li>
							<li>Any image field without a recognized suffix defaults to <strong>avatar</strong></li>
						</ul>
					</li>
					<li><strong>Taxonomy</strong> fields &rarr; category badges</li>
					<li><strong>URL</strong> fields &rarr; social icons (matched by suffix: <code>_website</code>,
						<code>_linkedin</code>, <code>_instagram</code>, <code>_twitter</code>,
						<code>_facebook</code>, <code>_youtube</code>, <code>_tiktok</code>,
						<code>_vimeo</code>, <code>_linktree</code>)</li>
				</ul>
				<p style="margin:4px 0 4px; font-size:12px; color:#787c82;">
					<strong>Tip:</strong> Each section can have its own banner. To add one, create an image field
					under the header tab with a name ending in <code>_banner</code> (e.g.&nbsp;<code>member_directory_profile_banner</code>).
					The banner renders as a full-width background image above the header bar.
				</p>

				<h4 style="margin:16px 0 6px; font-size:13px; color:#1d2327;">Conditional Tabs</h4>
				<p style="margin:0 0 4px; font-size:13px; color:#50575e;">
					Add <code>[if:section_key]</code> to any ACF tab label
					(e.g.&nbsp;<code>Storefront [if:business]</code>).
					The tab and all its fields are hidden when the referenced section is disabled for that member.
					The marker is stripped from the visible label automatically.
				</p>

				<h4 style="margin:16px 0 6px; font-size:13px; color:#1d2327;">Hidden / System Fields</h4>
				<p style="margin:0 0 4px; font-size:13px; color:#50575e;">
					Fields whose ACF key contains any of these substrings are automatically hidden from both the
					edit and view surfaces (they still save and store data normally):
				</p>
				<ul style="margin:0 0 4px; padding-left:20px; font-size:13px; color:#50575e;">
					<li><code>_enabled</code> &mdash; section enabled toggle</li>
					<li><code>_privacy_mode</code>, <code>_privacy_level</code> &mdash; section PMP controls</li>
					<li><code>_pmp_</code> &mdash; per-field PMP companion fields</li>
					<li><code>_display_precision</code> &mdash; location specificity selector</li>
					<li><code>_vis0</code> &mdash; general-purpose &ldquo;hide from front end&rdquo; marker</li>
				</ul>
				<p style="margin:4px 0 4px; font-size:12px; color:#787c82;">
					<strong>Tip:</strong> To hide any ACF field from the front end, add <code>_vis0</code> to
					its field key (e.g.&nbsp;<code>field_md_location_vis0_precision</code>). The field will still
					be editable in wp-admin and its value is available to PHP, but it won&rsquo;t render
					in section edit or view mode.
				</p>

				<h4 style="margin:16px 0 6px; font-size:13px; color:#1d2327;">PMP (Visibility Control)</h4>
				<p style="margin:0 0 4px; font-size:13px; color:#50575e;">
					Every field has a three-level visibility waterfall: <strong>Field &rarr; Section &rarr; Global</strong>.
					Each level can be:
				</p>
				<ul style="margin:0 0 4px; padding-left:20px; font-size:13px; color:#50575e;">
					<li><strong>Public</strong> &mdash; visible to everyone (logged in or out)</li>
					<li><strong>Member</strong> &mdash; visible only to logged-in users</li>
					<li><strong>Private</strong> &mdash; visible only to the profile author and admins</li>
					<li><strong>Inherit</strong> &mdash; defer to the next level up</li>
				</ul>
				<p style="margin:0 0 4px; font-size:13px; color:#50575e;">
					Members control PMP from their profile edit mode. The site-wide default is set in the
					right panel under &ldquo;Global Default.&rdquo;
					Hidden content renders <em>zero HTML</em> &mdash; no placeholders, no &ldquo;private&rdquo; labels.
				</p>

				<h4 style="margin:16px 0 6px; font-size:13px; color:#1d2327;">Trust Network</h4>
				<p style="margin:0 0 4px; font-size:13px; color:#50575e;">
					A non-ACF section for trusted repair partner relationships. Disabled by default &mdash; members opt in via the Trust toggle in their right panel.
					A logged-in member can request trust from another member&rsquo;s profile &mdash; the profile
					author sees pending requests in edit mode and can accept or decline.
					Accepted relationships are publicly visible. Either party can remove the relationship.
					Toggle the section on/off per-profile via the Trust toggle in the right panel.
					The database table is created automatically on plugin activation.
				</p>

				<h4 style="margin:16px 0 6px; font-size:13px; color:#1d2327;">Onboarding Shortcode</h4>
				<p style="margin:0 0 4px; font-size:13px; color:#50575e;">
					<code>[memdir_onboarding]</code> &mdash; place on any WordPress page.
					New members see a lightweight form: choose a primary section + enter a profile URL slug.
					On submit: a member-directory post is created, only the primary and &ldquo;always on&rdquo; sections
					are enabled (everything else off), and the member is redirected to their profile in edit mode.
					Existing members who land on the page are immediately redirected to their profile.
				</p>

			</div>
		</details>
		<?php
	}
}
