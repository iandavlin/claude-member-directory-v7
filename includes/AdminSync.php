<?php
/**
 * Admin Sync Page.
 *
 * Adds a Settings sub-menu page that lets an administrator trigger
 * SectionRegistry::sync() — reading all JSON files from sections/ and
 * writing the results to the WordPress database.
 *
 * Also provides a JSON uploader that validates a section config before
 * saving it to sections/, backs up any existing file it would overwrite,
 * and auto-runs sync so the section is live immediately.
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

	/** Admin page slug registered with WordPress. */
	const PAGE_SLUG = 'member-directory-sync';

	/** Maximum allowed upload size in bytes (256 KB). */
	const MAX_UPLOAD_BYTES = 262144;

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
			<h1>Member Directory &mdash; Section Sync</h1>
			<p>Click <strong>Run Sync</strong> to read all JSON files from the
			<code>sections/</code> folder and register them with the plugin.</p>
			<p>You must run a sync after adding or editing any section JSON file.
			Changes to JSON files have no effect until sync is run.</p>

			<?php self::maybe_handle_submission(); ?>

			<form method="post" action="">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
				<?php submit_button( 'Run Sync', 'primary', 'submit', false ); ?>
			</form>

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
				<p>
					<label>
						<input type="checkbox" name="allow_key_removal" value="1">
						Allow field key removal &mdash; <span style="color:#b94a00;">check this only if you intentionally deleted fields. Member data stored under removed keys will become inaccessible.</span>
					</label>
				</p>
			</form>

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

	/**
	 * If this is a valid sync form POST, run the sync and display results.
	 * Does nothing on a GET request or when the upload form was submitted.
	 */
	private static function maybe_handle_submission(): void {
		if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
			return;
		}

		// If the sync nonce field is absent this is not a sync submission
		// (e.g. the upload form was submitted instead). Bail silently.
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.' ) );
		}

		// Capability check (belt-and-suspenders: also enforced by WP before render() runs).
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run this action.' ) );
		}

		$result = SectionRegistry::sync();

		self::render_results( $result );
	}

	/**
	 * If this is a valid upload form POST, validate, back up, save, and sync.
	 * Does nothing on a GET request or when the sync form was submitted.
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

		// PHP upload error.
		$upload_error = isset( $file['error'] ) ? (int) $file['error'] : -1;
		if ( $upload_error !== UPLOAD_ERR_OK ) {
			self::render_upload_result( false, "Upload failed (PHP error code {$upload_error})." );
			return;
		}

		// File size.
		if ( (int) $file['size'] > self::MAX_UPLOAD_BYTES ) {
			self::render_upload_result( false, 'File exceeds the 256 KB size limit.' );
			return;
		}

		// Read temp file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw = file_get_contents( $file['tmp_name'] );
		if ( $raw === false ) {
			self::render_upload_result( false, 'Could not read the uploaded file.' );
			return;
		}

		// JSON decode.
		$data = json_decode( $raw, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			self::render_upload_result( false, 'Invalid JSON: ' . json_last_error_msg() . '.' );
			return;
		}

		// Structural + integrity validation (reuses the same checks as sync()).
		$allow_key_removal = isset( $_POST['allow_key_removal'] ) && $_POST['allow_key_removal'] === '1';
		$error             = SectionRegistry::validate_for_upload( $data, $allow_key_removal );
		if ( $error !== null ) {
			self::render_upload_result( false, $error );
			return;
		}

		$section_key  = $data['key'];
		$sections_dir = SectionRegistry::sections_dir();
		$target_file  = $sections_dir . $section_key . '.json';
		$backup_note  = '';

		// Back up any existing file before overwriting.
		if ( file_exists( $target_file ) ) {
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
			$backup_note  = 'Previous config backed up to <code>sections/backups/'
				. esc_html( $backup_name ) . '</code>.';

			if ( $backup_count > 3 ) {
				$backup_note .= ' You now have ' . esc_html( (string) $backup_count )
					. " backups for '" . esc_html( $section_key )
					. "' &mdash; consider deleting the oldest to stay within the 3-file limit.";
			}
		}

		// Write the new file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( file_put_contents( $target_file, $raw ) === false ) {
			self::render_upload_result(
				false,
				'Could not write to <code>sections/' . esc_html( $section_key ) . '.json</code>. Check server file permissions.'
			);
			return;
		}

		// Auto-sync so the section is live immediately.
		$sync_result   = SectionRegistry::sync();
		$skipped_count = count( $sync_result['skipped'] ?? [] );
		$detail        = $backup_note;

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

		// Show full sync results if anything else was skipped.
		if ( $skipped_count > 0 ) {
			self::render_results( $sync_result );
		}
	}

	/**
	 * Render a single upload operation result.
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
	 * Loaded files are shown in green.
	 * Skipped files are shown in red with their skip reason.
	 *
	 * @param array{loaded: string[], skipped: array<string, string>} $result
	 *   The array returned by SectionRegistry::sync().
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
				echo '<li style="color:#1a7a1a;">'
					. '&#10003; '
					. esc_html( $filename )
					. '</li>';
			}
			echo '</ul>';
		}

		if ( ! empty( $skipped ) ) {
			echo '<p><strong>Skipped (errors):</strong></p>';
			echo '<ul>';
			foreach ( $skipped as $filename => $reason ) {
				echo '<li style="color:#b94a00;">'
					. '&#10007; '
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
