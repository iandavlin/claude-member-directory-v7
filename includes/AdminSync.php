<?php
/**
 * Admin Sync Page.
 *
 * Adds a Settings sub-menu page that lets an administrator trigger
 * SectionRegistry::sync() — reading all JSON files from sections/ and
 * writing the results to the WordPress database.
 *
 * This is the only place in the plugin that reads from the filesystem.
 * All other page loads read section data from the database via
 * SectionRegistry::load_from_db().
 *
 * Usage (from Plugin.php):
 *   AdminSync::init();
 */

namespace MemberDirectory;

defined( 'ABSPATH' ) || exit;

class AdminSync {

	/** Nonce action used to validate the sync form submission. */
	const NONCE_ACTION = 'member_directory_sync';

	/** Nonce field name in the form. */
	const NONCE_FIELD = 'member_directory_sync_nonce';

	/** Admin page slug registered with WordPress. */
	const PAGE_SLUG = 'member-directory-sync';

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
	 *   - GET  — displays the form
	 *   - POST — processes the sync, then displays results followed by the form
	 */
	public static function render(): void {
		// Only administrators reach this page, but verify explicitly.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.' ) );
		}

		?>
		<div class="wrap">
			<h1>Member Directory — Section Sync</h1>
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
	 * If this is a valid POST submission, run the sync and display results.
	 * Does nothing on a plain GET request.
	 */
	private static function maybe_handle_submission(): void {
		if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
			return;
		}

		// Nonce verification.
		if ( ! isset( $_POST[ self::NONCE_FIELD ] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION )
		) {
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

		$total   = count( $loaded );
		$skipped_count = count( $skipped );
		echo '<p>'
			. esc_html( $total ) . ' section' . ( $total === 1 ? '' : 's' ) . ' loaded, '
			. esc_html( $skipped_count ) . ' skipped.'
			. '</p>';

		echo '<hr>';
	}
}
