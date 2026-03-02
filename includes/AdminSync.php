<?php
/**
 * Admin Sync Page.
 *
 * Adds a Settings sub-menu page that lets an administrator trigger
 * SectionRegistry::sync() — reading all JSON files from sections/ and
 * writing the results to the WordPress database.
 *
 * Also provides:
 *   - A section editor: inline JSON textarea per section with up/down
 *     reordering. Save validates, backs up, writes the file, and syncs.
 *   - A JSON uploader for adding new sections from a file.
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

	/** Nonce action used to validate the upload form submission. */
	const UPLOAD_NONCE_ACTION = 'member_directory_upload';

	/** Nonce field name in the upload form. */
	const UPLOAD_NONCE_FIELD = 'member_directory_upload_nonce';

	/** Nonce action used to validate section edit form submissions. */
	const EDIT_NONCE_ACTION = 'member_directory_edit_section';

	/** Nonce field name in section edit forms. */
	const EDIT_NONCE_FIELD = 'member_directory_edit_nonce';

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

	/** Admin page slug registered with WordPress. */
	const PAGE_SLUG = 'member-directory-sync';

	/** Maximum allowed upload size in bytes (256 KB). */
	const MAX_UPLOAD_BYTES = 262144;

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
			<p>Run Sync any time a <code>sections/</code> JSON file changes.
			ACF field group changes do <em>not</em> require a sync &mdash; they take effect immediately on the next page load.</p>

			<?php self::maybe_handle_submission(); ?>

			<form method="post" action="">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
				<?php submit_button( 'Run Sync', 'primary', 'submit', false ); ?>
			</form>

			<hr>
			<h2>Section Editor</h2>
			<p>Rename, reorder, toggle primary eligibility, or delete sections. Each save validates, backs up, and syncs immediately.</p>

			<?php
			self::maybe_handle_section_edit();
			self::maybe_handle_reorder();
			self::maybe_handle_section_delete();
			self::maybe_handle_toggle_primary();
			self::maybe_handle_rename();
			self::render_section_editor();
			?>

			<hr>
			<h2>Upload Section Config</h2>
			<p>Upload a section config JSON directly from your browser. The file is
			validated before anything is saved — invalid configs are rejected with a
			specific error message. If the section already exists, the current file is
			backed up to <code>sections/backups/</code> automatically before overwriting.
			Valid uploads are saved and synced immediately.</p>

			<?php self::maybe_handle_upload(); ?>

			<form method="post" action="" enctype="multipart/form-data">
				<?php wp_nonce_field( self::UPLOAD_NONCE_ACTION, self::UPLOAD_NONCE_FIELD ); ?>
				<p>
					<input type="file" name="section_config_file" accept=".json" style="margin-right:8px;">
					<?php submit_button( 'Upload &amp; Sync', 'secondary', 'upload_submit', false ); ?>
				</p>
			</form>

			<hr>
			<h2>Section Headers</h2>
			<p>Headers are rendered automatically &mdash; no PHP changes needed. To give a section a header, open its field group in <strong>Custom Fields &rarr; Field Groups</strong> and add a <strong>Tab</strong> field whose label contains the word <code>header</code> (e.g. <em>Profile Header</em>, <em>Business Header</em>). Fields placed <em>inside</em> that tab are mapped to header slots by type:</p>

			<table class="widefat striped" style="margin-bottom:16px;">
				<thead>
					<tr>
						<th style="width:140px;">Field type</th>
						<th>Header slot</th>
						<th>Notes</th>
					</tr>
				</thead>
				<tbody>
					<tr><td><code>text</code></td>    <td>Name / title (h1)</td>         <td>First <code>text</code> field only</td></tr>
					<tr><td><code>image</code></td>   <td>Avatar (72&times;72 circle)</td><td>First <code>image</code> field only</td></tr>
					<tr><td><code>taxonomy</code></td><td>Category badge pills</td>       <td>All <code>taxonomy</code> fields, all terms</td></tr>
					<tr><td><code>url</code></td>     <td>Social icon link</td>           <td>Field name must end with a platform suffix (see below)</td></tr>
				</tbody>
			</table>

			<p><strong>Social platform name suffixes</strong> &mdash; name your <code>url</code> field so it ends with one of these (e.g. <code>member_directory_profile_linkedin</code>):</p>

			<table class="widefat striped" style="margin-bottom:16px;max-width:380px;">
				<thead>
					<tr><th>Suffix</th><th>Icon shown</th></tr>
				</thead>
				<tbody>
					<tr><td><code>_website</code></td>  <td>Globe (generic link)</td></tr>
					<tr><td><code>_linkedin</code></td> <td>LinkedIn</td></tr>
					<tr><td><code>_instagram</code></td><td>Instagram</td></tr>
					<tr><td><code>_twitter</code></td>  <td>Twitter&nbsp;/&nbsp;X</td></tr>
					<tr><td><code>_facebook</code></td> <td>Facebook</td></tr>
					<tr><td><code>_youtube</code></td>  <td>YouTube</td></tr>
					<tr><td><code>_tiktok</code></td>   <td>TikTok</td></tr>
					<tr><td><code>_vimeo</code></td>    <td>Vimeo</td></tr>
				</tbody>
			</table>

			<p>Save the field group in ACF &mdash; the header appears on the next page load. No sync required.</p>

			<hr>
			<h2>Claude Skills</h2>
			<p>Download a skill file and attach it to a Claude conversation. Claude will follow the skill instructions automatically.</p>

			<h3>Section Manager <span style="font-weight:normal;font-size:13px;color:#666;">&mdash; add, change, delete, rename, reorder, revert</span></h3>
			<p>The primary tool for all section work. Handles every operation and automatically backs up the current config before any change (rolling 3-backup archive per section, stored in <code>sections/backups/</code>).</p>

			<table class="widefat striped" style="margin-bottom:12px;">
				<thead>
					<tr>
						<th>To do this&hellip;</th>
						<th>Say exactly this (or something close)</th>
					</tr>
				</thead>
				<tbody>
					<tr><td>Add a new section</td>       <td><code>Add a new section</code> &nbsp;/&nbsp; <code>Create a [name] section</code></td></tr>
					<tr><td>Update an existing section</td><td><code>Change [section name]</code> &nbsp;/&nbsp; <code>Update [section name]</code></td></tr>
					<tr><td>Remove a section</td>        <td><code>Delete [section name]</code> &nbsp;/&nbsp; <code>Remove the [section name] section</code></td></tr>
					<tr><td>Rename a section</td>        <td><code>Rename [section name] to [new name]</code></td></tr>
					<tr><td>Change section order</td>    <td><code>Reorder sections</code> &nbsp;/&nbsp; <code>Move [section] before [other]</code></td></tr>
					<tr><td>Restore from backup</td>     <td><code>Revert [section name] to [filename]</code> &nbsp;/&nbsp; <code>Restore [section name] from backup</code></td></tr>
				</tbody>
			</table>

			<p>
				<a class="button button-primary" href="<?php echo esc_url( plugin_dir_url( dirname( __FILE__ ) ) . 'tools/section-manager.md' ); ?>" download>
					Download Section Manager
				</a>
			</p>

			<h3>Helper Tool</h3>

			<h4>ACF Group Preparer</h4>
			<p>Injects the two required PMP system fields (<em>Enable Section</em> and <em>Visibility</em>) into a raw ACF field group export so it can be re-imported into ACF via Tools &rarr; Import. Optional &mdash; Section Manager adds these fields automatically if you skip this step.</p>
			<p>
				<a class="button button-secondary" href="<?php echo esc_url( plugin_dir_url( dirname( __FILE__ ) ) . 'tools/acf-pmp-prep.md' ); ?>" download>
					Download ACF Group Preparer
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
	 * If this is a valid section edit form POST, validate, back up, save, and sync.
	 * The result is stored in $last_edited_key so render_section_editor() can
	 * auto-open the affected section's <details> element.
	 */
	private static function maybe_handle_section_edit(): void {
		if ( ! isset( $_POST[ self::EDIT_NONCE_FIELD ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::EDIT_NONCE_FIELD ] ) ), self::EDIT_NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run this action.' ) );
		}

		$section_key = sanitize_key( $_POST['edit_section_key'] ?? '' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$raw         = wp_unslash( $_POST['section_json'] ?? '' );

		self::$last_edited_key = $section_key;

		if ( empty( $raw ) ) {
			self::render_upload_result( false, 'No JSON content received.' );
			return;
		}

		$data = json_decode( $raw, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			self::render_upload_result( false, 'Invalid JSON: ' . json_last_error_msg() . '.' );
			return;
		}

		$error = SectionRegistry::validate_for_upload( $data );
		if ( $error !== null ) {
			self::render_upload_result( false, $error );
			return;
		}

		// Compute removed keys before writing (advisory warning only).
		$removed_keys = SectionRegistry::removed_content_keys( $data );

		$backup_note = self::backup_section_file( $section_key );
		$pretty_raw  = (string) json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		$sections_dir = SectionRegistry::sections_dir();
		$target_file  = $sections_dir . $section_key . '.json';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( file_put_contents( $target_file, $pretty_raw ) === false ) {
			self::render_upload_result(
				false,
				'Could not write to <code>sections/' . esc_html( $section_key ) . '.json</code>. Check server file permissions.'
			);
			return;
		}

		$sync_result   = SectionRegistry::sync();
		$skipped_count = count( $sync_result['skipped'] ?? [] );
		$detail        = $backup_note;

		if ( ! empty( $removed_keys ) ) {
			$detail .= ( $detail ? ' ' : '' )
				. '<strong style="color:#b94a00;">Warning:</strong> the following field key(s) were removed &mdash; member data stored under them is now inaccessible: '
				. '<code>' . esc_html( implode( '</code>, <code>', $removed_keys ) ) . '</code>.';
		}

		if ( $skipped_count > 0 ) {
			$detail .= ( $detail ? ' ' : '' )
				. 'Note: ' . esc_html( (string) $skipped_count )
				. ' other section(s) failed validation during sync &mdash; see results below.';
		}

		self::render_upload_result(
			true,
			'Section <strong>' . esc_html( $section_key ) . '</strong> saved and synced successfully.',
			$detail
		);

		if ( $skipped_count > 0 ) {
			self::render_results( $sync_result );
		}
	}

	/**
	 * If this is a valid reorder POST, swap the order values of two adjacent
	 * sections, write both files, and re-sync.
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

		// Reorder directly in the DB option — no JSON file writes needed.
		// The DB option is the authoritative order source; JSON files carry no order.
		$stored = get_option( SectionRegistry::OPTION_KEY, [] );
		$keys   = array_keys( $stored );

		$pos = array_search( $key, $keys, true );

		if ( $pos === false ) {
			return;
		}

		$swap_pos = ( $dir === 'up' ) ? $pos - 1 : $pos + 1;

		if ( $swap_pos < 0 || $swap_pos >= count( $keys ) ) {
			// Already at the boundary — nothing to do.
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

		// Sync refreshes the in-memory cache so render_section_editor() reflects
		// the new order within this same request.
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
	 * If this is a valid upload form POST, validate, back up, save, and sync.
	 * Does nothing on a GET request or when another form was submitted.
	 */
	private static function maybe_handle_upload(): void {
		if ( ! isset( $_FILES['section_config_file'] ) ) {
			return;
		}

		// Nonce verification.
		if ( ! isset( $_POST[ self::UPLOAD_NONCE_FIELD ] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::UPLOAD_NONCE_FIELD ] ) ), self::UPLOAD_NONCE_ACTION )
		) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run this action.' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$file = $_FILES['section_config_file'];

		$upload_error = isset( $file['error'] ) ? (int) $file['error'] : -1;
		if ( $upload_error !== UPLOAD_ERR_OK ) {
			self::render_upload_result( false, "Upload failed (PHP error code {$upload_error})." );
			return;
		}

		if ( (int) $file['size'] > self::MAX_UPLOAD_BYTES ) {
			self::render_upload_result( false, 'File exceeds the 256 KB size limit.' );
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw = file_get_contents( $file['tmp_name'] );
		if ( $raw === false ) {
			self::render_upload_result( false, 'Could not read the uploaded file.' );
			return;
		}

		$data = json_decode( $raw, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			self::render_upload_result( false, 'Invalid JSON: ' . json_last_error_msg() . '.' );
			return;
		}

		// Auto-convert raw ACF exports into section configs.
		$coerce_note = '';
		$data        = self::coerce_acf_export( $data, $coerce_note );

		$error = SectionRegistry::validate_for_upload( $data );
		if ( $error !== null ) {
			self::render_upload_result( false, $error, $coerce_note );
			return;
		}

		$section_key  = $data['key'];
		$sections_dir = SectionRegistry::sections_dir();
		$target_file  = $sections_dir . $section_key . '.json';

		// Compute removed keys before writing (advisory warning only).
		$removed_keys = SectionRegistry::removed_content_keys( $data );
		$backup_note  = self::backup_section_file( $section_key );

		// Always write the (possibly coerced) pretty-printed version.
		$write_raw = (string) json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( file_put_contents( $target_file, $write_raw ) === false ) {
			self::render_upload_result(
				false,
				'Could not write to <code>sections/' . esc_html( $section_key ) . '.json</code>. Check server file permissions.'
			);
			return;
		}

		$sync_result   = SectionRegistry::sync();
		$skipped_count = count( $sync_result['skipped'] ?? [] );
		$detail        = $coerce_note;

		if ( $backup_note ) {
			$detail .= ( $detail ? ' ' : '' ) . $backup_note;
		}

		if ( ! empty( $removed_keys ) ) {
			$detail .= ( $detail ? ' ' : '' )
				. '<strong style="color:#b94a00;">Warning:</strong> the following field key(s) were removed &mdash; member data stored under them is now inaccessible: '
				. '<code>' . esc_html( implode( '</code>, <code>', $removed_keys ) ) . '</code>.';
		}

		if ( $skipped_count > 0 ) {
			$detail .= ( $detail ? ' ' : '' )
				. 'Note: ' . esc_html( (string) $skipped_count )
				. ' other section(s) failed validation during sync &mdash; see results below.';
		}

		self::render_upload_result(
			true,
			'Section <strong>' . esc_html( $section_key ) . '</strong> uploaded and synced successfully.',
			$detail
		);

		if ( $skipped_count > 0 ) {
			self::render_results( $sync_result );
		}
	}

	/**
	 * If this is a valid can_be_primary toggle POST, update the JSON file and re-sync.
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

		if ( empty( $section_key ) ) {
			self::render_upload_result( false, 'No section key provided.' );
			return;
		}

		$sections_dir = SectionRegistry::sections_dir();
		$target_file  = $sections_dir . $section_key . '.json';

		if ( ! file_exists( $target_file ) ) {
			self::render_upload_result( false, 'Section file <code>sections/' . esc_html( $section_key ) . '.json</code> not found.' );
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw  = file_get_contents( $target_file );
		$data = json_decode( $raw !== false ? $raw : '', true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			self::render_upload_result( false, 'Could not parse section JSON.' );
			return;
		}

		$data['can_be_primary'] = $can_be_primary;
		$pretty = (string) json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( file_put_contents( $target_file, $pretty ) === false ) {
			self::render_upload_result( false, 'Could not write <code>sections/' . esc_html( $section_key ) . '.json</code>.' );
			return;
		}

		SectionRegistry::sync();
		self::$last_edited_key = $section_key;

		$state = $can_be_primary ? 'enabled' : 'disabled';
		self::render_upload_result( true, '"Can be primary" ' . $state . ' for <strong>' . esc_html( $section_key ) . '</strong>.' );
	}

	/**
	 * If this is a valid rename POST, update the label in the JSON file and re-sync.
	 * Only the display label is changed — the section key and acf_group_key remain
	 * untouched. The ACF group title lives in acf-json/ and is managed via ACF admin.
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

		$sections_dir = SectionRegistry::sections_dir();
		$target_file  = $sections_dir . $section_key . '.json';

		if ( ! file_exists( $target_file ) ) {
			self::render_upload_result( false, 'Section file <code>sections/' . esc_html( $section_key ) . '.json</code> not found.' );
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw  = file_get_contents( $target_file );
		$data = json_decode( $raw !== false ? $raw : '', true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			self::render_upload_result( false, 'Could not parse section JSON.' );
			return;
		}

		$old_label = $data['label'] ?? $section_key;

		$data['label'] = $new_label;

		$pretty = (string) json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( file_put_contents( $target_file, $pretty ) === false ) {
			self::render_upload_result( false, 'Could not write <code>sections/' . esc_html( $section_key ) . '.json</code>.' );
			return;
		}

		SectionRegistry::sync();
		self::$last_edited_key = $section_key;

		self::render_upload_result(
			true,
			'Section renamed from <strong>' . esc_html( $old_label ) . '</strong> to <strong>' . esc_html( $new_label ) . '</strong>.'
		);
	}

	// -----------------------------------------------------------------------
	// Import helpers
	// -----------------------------------------------------------------------

	/**
	 * Convert a raw ACF export into a lean section config pointer, or return
	 * the input unchanged if it already looks like a section config.
	 *
	 * ACF exports are top-level JSON arrays: [ { "key": "group_...", ... } ].
	 * This method detects that shape and extracts only the metadata needed for
	 * a lean section config (key, label, acf_group_key). The field group itself
	 * belongs in acf-json/ and is managed by ACF — it is NOT embedded here.
	 *
	 * If the export contains multiple groups only the first is used.
	 *
	 * @param  mixed  $raw   Decoded JSON value (array or other).
	 * @param  string &$note Human-readable description of changes made.
	 * @return array         Section config ready for validate_for_upload().
	 */
	private static function coerce_acf_export( mixed $raw, string &$note ): array {
		$note = '';

		// Already a lean section config (or old-format with acf_group) — pass through.
		if ( is_array( $raw ) && ( array_key_exists( 'acf_group_key', $raw ) || array_key_exists( 'acf_group', $raw ) ) ) {
			return $raw;
		}

		// Must be a numerically-indexed array (ACF export format).
		if ( ! is_array( $raw ) || ! array_is_list( $raw ) || empty( $raw[0] ) ) {
			return is_array( $raw ) ? $raw : [];
		}

		$group       = $raw[0];
		$group_key   = $group['key']   ?? '';
		$title       = $group['title'] ?? 'Section';
		$section_key = str_replace( '-', '_', sanitize_key( $title ) );

		$config = [
			'key'          => $section_key,
			'label'        => $title,
			'acf_group_key' => $group_key,
		];

		$parts = [ 'Auto-converted from ACF export to lean section pointer.' ];

		if ( count( $raw ) > 1 ) {
			$parts[] = 'Export contained ' . count( $raw ) . ' groups &mdash; only the first (<em>' . esc_html( $title ) . '</em>) was used.';
		}

		$parts[] = 'The field group (<code>' . esc_html( $group_key ) . '</code>) must be present in <code>acf-json/</code> for fields to load at runtime.';
		$parts[] = 'Review the section key (<code>' . esc_html( $section_key ) . '</code>) in the Section Editor before going live.';

		$note = implode( ' ', $parts );

		return $config;
	}

	// -----------------------------------------------------------------------
	// Render helpers
	// -----------------------------------------------------------------------

	/**
	 * Render the section editor: a sorted list of <details> elements, one per
	 * section, each containing a JSON textarea and save/reorder controls.
	 */
	private static function render_section_editor(): void {
		$sections = SectionRegistry::get_sections();

		if ( empty( $sections ) ) {
			echo '<p>No sections are currently synced. Upload a section config or run Sync to load sections from the <code>sections/</code> folder.</p>';
			return;
		}

		$count = count( $sections );

		foreach ( $sections as $i => $section ) {
			$key            = $section['key']   ?? '';
			$label          = $section['label'] ?? $key;
			$can_be_primary = ! empty( $section['can_be_primary'] );
			$is_first       = ( $i === 0 );
			$is_last        = ( $i === $count - 1 );
			$open_attr      = ( $key === self::$last_edited_key ) ? ' open' : '';
			$pretty_json    = (string) json_encode( $section, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

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

			// Can be primary toggle — auto-submits on change; stops summary click propagation.
			echo '<form method="post" action="" style="margin-left:auto;display:flex;align-items:center;gap:5px;" onclick="event.stopPropagation();">';
			wp_nonce_field( self::TOGGLE_PRIMARY_NONCE_ACTION, self::TOGGLE_PRIMARY_NONCE_FIELD );
			echo '<input type="hidden" name="toggle_section_key" value="' . esc_attr( $key ) . '">';
			echo '<label style="display:flex;align-items:center;gap:4px;font-size:12px;color:#555;cursor:pointer;user-select:none;" onclick="event.stopPropagation();">';
			echo '<input type="checkbox" name="can_be_primary" value="1"'
				. checked( $can_be_primary, true, false )
				. ' onchange="this.form.submit();" onclick="event.stopPropagation();">';
			echo 'Can be primary';
			echo '</label>';
			echo '</form>';

			echo '</summary>';

			// --- Edit form --------------------------------------------------
			echo '<div style="padding:14px;">';

			// Rename form — updates label and acf_group.title only; key is unchanged.
			echo '<div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #eee;">';
			echo '<label style="font-weight:600;white-space:nowrap;font-size:13px;">Section Label:</label>';
			echo '<form method="post" action="" style="display:flex;gap:6px;flex:1;" onclick="event.stopPropagation();">';
			wp_nonce_field( self::RENAME_NONCE_ACTION, self::RENAME_NONCE_FIELD );
			echo '<input type="hidden" name="rename_section_key" value="' . esc_attr( $key ) . '">';
			echo '<input type="text" name="new_label" value="' . esc_attr( $label ) . '" style="flex:1;max-width:320px;">';
			submit_button( 'Rename', 'small', 'rename_submit_' . esc_attr( $key ), false );
			echo '</form>';
			echo '</div>';

			echo '<form method="post" action="">';
			wp_nonce_field( self::EDIT_NONCE_ACTION, self::EDIT_NONCE_FIELD );
			echo '<input type="hidden" name="edit_section_key" value="' . esc_attr( $key ) . '">';

			echo '<textarea name="section_json" rows="28" style="width:100%;font-family:monospace;font-size:12px;white-space:pre;">'
				. esc_textarea( $pretty_json )
				. '</textarea>';

			submit_button( 'Save &amp; Sync', 'secondary', 'edit_submit_' . esc_attr( $key ), false );

			echo '</form>';

			// --- Delete form ------------------------------------------------
			echo '<div style="margin-top:12px;padding-top:12px;border-top:1px solid #eee;">';
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
}
