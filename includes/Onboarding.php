<?php
/**
 * Onboarding shortcode — [memdir_onboarding]
 *
 * Lightweight form for new members to create their member-directory post.
 * Doubles as a redirect funnel: existing members who land on the page
 * get sent straight to their profile.
 *
 * Behaviour:
 *   1. Logged-out → shortcode returns empty (BuddyBoss handles login redirect).
 *   2. Existing member → wp_safe_redirect to their profile permalink.
 *   3. New member → form: primary section radio + URL slug text input.
 *      On submit: create post, set primary, enable always_on sections,
 *      disable the rest, redirect to profile in edit mode.
 *
 * @since 0.3.0
 */

namespace MemberDirectory;

defined( 'ABSPATH' ) || exit;

class Onboarding {

	/** In-memory form error for the current request. */
	private static ?string $form_error = null;

	/** Submitted slug value (preserved on error for re-display). */
	private static string $submitted_slug = '';

	/** Submitted primary key (preserved on error for re-display). */
	private static string $submitted_primary = '';

	/* ── Bootstrap ───────────────────────────────────────── */

	public static function init(): void {
		add_shortcode( 'memdir_onboarding', [ self::class, 'render_shortcode' ] );
		add_action( 'template_redirect', [ self::class, 'maybe_redirect' ] );
	}

	/* ── Redirect / POST Processing ─────────────────────── */

	/**
	 * Runs at template_redirect — before any HTML output.
	 *
	 * Handles two cases:
	 *   1. POST form submission → process_form() (creates post + redirects).
	 *   2. Existing member → redirect to their profile.
	 */
	public static function maybe_redirect(): void {
		// Only act on pages containing our shortcode.
		$post = get_post();
		if ( ! $post || ! has_shortcode( $post->post_content ?? '', 'memdir_onboarding' ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return; // BuddyBoss handles login redirect.
		}

		// Handle form POST first (takes priority over redirect).
		if ( $_SERVER['REQUEST_METHOD'] === 'POST'
			&& isset( $_POST['memdir_onboard_nonce'] ) ) {
			self::process_form();
			// If process_form() succeeded, it redirected and exited.
			// If it failed, self::$form_error is set; fall through to
			// let render_shortcode() display the error.
			return;
		}

		// Existing member? Redirect to their profile.
		$existing = self::get_user_profile_post( get_current_user_id() );
		if ( $existing ) {
			wp_safe_redirect( get_permalink( $existing ) );
			exit;
		}
	}

	/* ── Shortcode Render ───────────────────────────────── */

	/**
	 * Render the onboarding form.
	 *
	 * All redirect cases are handled by maybe_redirect() before this runs.
	 * This only executes when the user needs to see the form.
	 *
	 * @return string HTML output (shortcodes must return, not echo).
	 */
	public static function render_shortcode( $atts = [] ): string {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		// Double-check: if member already exists, show a link as fallback
		// (shouldn't reach here — maybe_redirect handles it, but just in case).
		$existing = self::get_user_profile_post( get_current_user_id() );
		if ( $existing ) {
			$url = esc_url( get_permalink( $existing ) );
			return '<p>You already have a profile. <a href="' . $url . '">Go to your profile →</a></p>';
		}

		return self::render_form();
	}

	/* ── Form HTML ──────────────────────────────────────── */

	/**
	 * Build the onboarding form HTML.
	 */
	private static function render_form(): string {
		// Gather primary-capable sections.
		$primary_sections = [];
		foreach ( SectionRegistry::get_sections() as $sec ) {
			if ( ! empty( $sec['can_be_primary'] ) ) {
				$primary_sections[] = $sec;
			}
		}

		if ( empty( $primary_sections ) ) {
			return '<p>No primary sections are configured. Please contact the site administrator.</p>';
		}

		$error          = self::$form_error;
		$submitted_slug = self::$submitted_slug;
		$submitted_primary = self::$submitted_primary ?: 'profile';

		ob_start();
		?>
		<style>
			.memdir-onboarding {
				--md-green-sage: #97A97C;
				--md-green-dark: #87986A;
				--md-coral: #FE6B4F;
				--md-text: #1a1a1a;
				--md-text-muted: #6b6b6b;
				--md-white: #ffffff;
				--md-border: #e2e2dc;
				--md-radius: 8px;
				--md-font: 'Jost', sans-serif;
				max-width: 480px;
				margin: 2rem auto;
				padding: 2rem;
				font-family: var(--md-font);
				background: var(--md-white);
				border: 1px solid var(--md-border);
				border-radius: var(--md-radius);
			}
			.memdir-onboarding h2 {
				font-family: var(--md-font);
				font-size: 1.4rem;
				font-weight: 600;
				margin: 0 0 0.5rem;
				color: var(--md-text);
			}
			.memdir-onboarding__intro {
				color: var(--md-text-muted);
				font-size: 0.95rem;
				margin: 0 0 1.5rem;
			}
			.memdir-onboarding__error {
				background: rgba(254, 107, 79, 0.1);
				color: var(--md-coral);
				padding: 10px 14px;
				border-radius: var(--md-radius);
				margin-bottom: 1rem;
				font-size: 0.9rem;
				font-weight: 500;
			}
			.memdir-onboarding__fieldset {
				border: none;
				padding: 0;
				margin: 0 0 1.5rem;
			}
			.memdir-onboarding__fieldset legend {
				font-weight: 600;
				font-size: 0.95rem;
				margin-bottom: 8px;
				color: var(--md-text);
			}
			.memdir-onboarding__fieldset label {
				display: block;
				padding: 6px 0;
				cursor: pointer;
				font-size: 0.95rem;
				color: var(--md-text);
			}
			.memdir-onboarding__fieldset input[type="radio"] {
				margin-right: 8px;
				accent-color: var(--md-green-sage);
			}
			.memdir-onboarding__field {
				margin-bottom: 1.5rem;
			}
			.memdir-onboarding__field > label {
				display: block;
				font-weight: 600;
				font-size: 0.95rem;
				margin-bottom: 6px;
				color: var(--md-text);
			}
			.memdir-onboarding__slug-wrap {
				display: flex;
				align-items: center;
				border: 1px solid var(--md-border);
				border-radius: var(--md-radius);
				overflow: hidden;
			}
			.memdir-onboarding__slug-base {
				padding: 8px 2px 8px 12px;
				color: var(--md-text-muted);
				font-size: 0.85rem;
				white-space: nowrap;
				background: #f5f5f3;
				border-right: 1px solid var(--md-border);
			}
			.memdir-onboarding__slug-wrap input {
				flex: 1;
				padding: 8px 10px;
				border: none;
				outline: none;
				font-family: var(--md-font);
				font-size: 0.95rem;
				min-width: 0;
			}
			.memdir-onboarding__submit {
				display: block;
				width: 100%;
				padding: 12px;
				background: var(--md-green-sage);
				color: var(--md-white);
				border: none;
				border-radius: var(--md-radius);
				font-family: var(--md-font);
				font-size: 1rem;
				font-weight: 600;
				cursor: pointer;
				transition: background 0.15s;
			}
			.memdir-onboarding__submit:hover {
				background: var(--md-green-dark);
			}
		</style>

		<div class="memdir-onboarding">
			<h2>Create Your Member Profile</h2>
			<p class="memdir-onboarding__intro">Choose your primary section and pick a URL for your profile page.</p>

			<?php if ( $error ) : ?>
				<div class="memdir-onboarding__error"><?php echo esc_html( $error ); ?></div>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'memdir_onboard', 'memdir_onboard_nonce' ); ?>

				<fieldset class="memdir-onboarding__fieldset">
					<legend>Primary Section</legend>
					<?php foreach ( $primary_sections as $sec ) : ?>
						<label>
							<input type="radio" name="memdir_primary"
							       value="<?php echo esc_attr( $sec['key'] ); ?>"
							       <?php checked( $sec['key'], $submitted_primary ); ?> />
							<?php echo esc_html( $sec['label'] ); ?>
						</label>
					<?php endforeach; ?>
				</fieldset>

				<div class="memdir-onboarding__field">
					<label for="memdir-slug">Profile URL</label>
					<div class="memdir-onboarding__slug-wrap">
						<span class="memdir-onboarding__slug-base">/member-directory/</span>
						<input type="text" id="memdir-slug" name="memdir_slug"
						       pattern="[a-z0-9\-]+" required maxlength="100"
						       placeholder="jane-doe"
						       value="<?php echo esc_attr( $submitted_slug ); ?>" />
					</div>
				</div>

				<button type="submit" class="memdir-onboarding__submit">Create Profile</button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ── Form Processing ────────────────────────────────── */

	/**
	 * Validate and process the onboarding form.
	 *
	 * On success: creates the post, sets up sections, redirects, exits.
	 * On failure: sets self::$form_error and returns (render_form shows error).
	 */
	private static function process_form(): void {
		// 1. Verify nonce.
		if ( ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['memdir_onboard_nonce'] ?? '' ) ),
			'memdir_onboard'
		) ) {
			self::$form_error = 'Security check failed. Please try again.';
			return;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			self::$form_error = 'You must be logged in.';
			return;
		}

		// 2. Race condition guard — user may already have a profile.
		if ( self::get_user_profile_post( $user_id ) ) {
			// Redirect will be handled by maybe_redirect on next load.
			return;
		}

		// 3. Validate primary section.
		$primary_key = sanitize_text_field( wp_unslash( $_POST['memdir_primary'] ?? '' ) );
		self::$submitted_primary = $primary_key;

		$valid_primary_keys = [];
		foreach ( SectionRegistry::get_sections() as $sec ) {
			if ( ! empty( $sec['can_be_primary'] ) ) {
				$valid_primary_keys[] = $sec['key'];
			}
		}
		if ( ! in_array( $primary_key, $valid_primary_keys, true ) ) {
			self::$form_error = 'Please select a valid primary section.';
			return;
		}

		// 4. Sanitize slug.
		$raw_slug = sanitize_text_field( wp_unslash( $_POST['memdir_slug'] ?? '' ) );
		$slug     = sanitize_title( $raw_slug );
		self::$submitted_slug = $raw_slug;

		if ( empty( $slug ) ) {
			self::$form_error = 'Please enter a valid URL slug.';
			return;
		}

		// 5. Check slug uniqueness.
		if ( ! self::is_slug_available( $slug ) ) {
			self::$form_error = 'That URL is already taken. Please choose a different one.';
			return;
		}

		// 6. Build post title from display name, fallback to slug.
		$user  = get_userdata( $user_id );
		$title = ( $user && $user->display_name ) ? $user->display_name : $slug;

		// 7. Create the post.
		$post_id = wp_insert_post( [
			'post_type'   => 'member-directory',
			'post_status' => 'publish',
			'post_author' => $user_id,
			'post_title'  => $title,
			'post_name'   => $slug,
		], true );

		if ( is_wp_error( $post_id ) ) {
			self::$form_error = 'Could not create your profile. Please try again.';
			return;
		}

		// 8. Set primary section.
		update_field( 'field_md_primary_section', $primary_key, $post_id );

		// 9. Enable/disable sections: always_on + primary = on, rest = off.
		foreach ( SectionRegistry::get_sections() as $sec ) {
			$sec_key       = $sec['key'];
			$is_always_on  = ! empty( $sec['always_on'] );
			$is_primary    = ( $sec_key === $primary_key );
			$should_enable = $is_always_on || $is_primary;

			update_field(
				'member_directory_' . $sec_key . '_enabled',
				$should_enable ? 1 : 0,
				$post_id
			);
		}

		// 10. Set global PMP default to 'member' (Members only).
		update_field( 'field_md_global_pmp', 'member', $post_id );

		// 11. Redirect to profile in edit mode with primary pill active.
		$redirect_url = get_permalink( $post_id );
		if ( $redirect_url ) {
			$redirect_url = add_query_arg( 'active_section', $primary_key, $redirect_url );
			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/* ── Helpers ─────────────────────────────────────────── */

	/**
	 * Get the current user's published member-directory post ID (or 0).
	 */
	private static function get_user_profile_post( int $user_id ): int {
		$posts = get_posts( [
			'post_type'      => 'member-directory',
			'author'         => $user_id,
			'posts_per_page' => 1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		] );
		return ! empty( $posts ) ? (int) $posts[0] : 0;
	}

	/**
	 * Check if a slug is available for a new member-directory post.
	 */
	private static function is_slug_available( string $slug ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			 WHERE post_name = %s AND post_type = %s AND post_status = 'publish'
			 LIMIT 1",
			$slug,
			'member-directory'
		) );

		return ! $exists;
	}
}
