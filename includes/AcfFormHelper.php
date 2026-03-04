<?php
/**
 * ACF Form Helper — Front-End Edit Mode.
 *
 * Wraps ACF's acf_form_head() and acf_form() to provide edit mode on the
 * single member profile page. The post author (or an admin) sees an editable
 * form instead of the read-only view.
 *
 * ────────────────────────────────────────────────────────────────────
 *  CRITICAL TIMING REQUIREMENT
 * ────────────────────────────────────────────────────────────────────
 *
 * acf_form_head() MUST be called before any HTML output — before
 * get_header(), before wp_head(), before any whitespace. It handles
 * form submission processing and redirect, and enqueues the scripts
 * ACF needs. If called after output has started, the redirect on save
 * will fail with a "headers already sent" error.
 *
 * The template calls AcfFormHelper::maybe_render_form_head() at the
 * very top of single-member-directory.php, before get_header().
 *
 * ────────────────────────────────────────────────────────────────────
 *  EDIT MODE VS VIEW MODE
 * ────────────────────────────────────────────────────────────────────
 *
 * Edit mode activates when all three conditions are met:
 *   1. The post type is member-directory.
 *   2. The current user is the post author or an admin.
 *   3. No ?view_as parameter is present in the URL.
 *
 * When the author appends ?view_as=member or ?view_as=public, they
 * switch to a read-only preview (View As simulation). Edit mode is
 * suppressed and the standard section-view.php partial renders instead.
 *
 * Usage (from Plugin.php):
 *   AcfFormHelper::init();
 *
 * Usage (from templates/single-member-directory.php):
 *   AcfFormHelper::maybe_render_form_head();   // before get_header()
 *   AcfFormHelper::is_edit_mode( $post_id, $viewer );
 *   AcfFormHelper::render_edit_form( $section, $post_id );
 */

namespace MemberDirectory;

defined( 'ABSPATH' ) || exit;

class AcfFormHelper {

	/**
	 * Initialise the helper.
	 * Called once from Plugin::init() during plugins_loaded.
	 *
	 * Currently a no-op placeholder — no hooks are needed at bootstrap
	 * time because all work happens at template-render time via the
	 * static methods below. Kept for consistency with the other classes
	 * (GlobalFields, AdminSync, etc.) and as a hook point for future
	 * enqueue or filter logic.
	 */
	public static function init(): void {
		add_action( 'wp_ajax_md_save_section',                    [ self::class, 'handle_ajax_save' ] );
		add_action( 'wp_ajax_memdir_ajax_save_section_enabled',   [ self::class, 'handle_save_section_enabled' ] );
		add_action( 'wp_ajax_memdir_ajax_save_section_pmp',       [ self::class, 'handle_save_section_pmp' ] );
		add_action( 'wp_ajax_memdir_ajax_save_field_pmp',         [ self::class, 'handle_save_field_pmp' ] );
		add_action( 'wp_ajax_memdir_ajax_upload_avatar',          [ self::class, 'handle_avatar_upload' ] );
		add_action( 'wp_ajax_memdir_search_taxonomy_terms',       [ self::class, 'handle_search_taxonomy_terms' ] );
	}

	// -----------------------------------------------------------------------
	// Template-time API
	// -----------------------------------------------------------------------

	/**
	 * Call acf_form_head() if the current request is an edit-mode profile page.
	 *
	 * MUST be called at the very top of the single template, before
	 * get_header() and before any HTML or whitespace output. This is the
	 * only place in the plugin that calls acf_form_head().
	 *
	 * acf_form_head() does two things:
	 *   1. On a GET request — enqueues ACF's form scripts/styles so
	 *      acf_form() can render later in the page.
	 *   2. On a POST request (form submission) — processes the submitted
	 *      data, saves all ACF fields, and redirects back to the profile.
	 *
	 * The method checks whether the current request is a single
	 * member-directory post where the viewer is the author or admin
	 * (and not in View As mode) before calling acf_form_head(). This
	 * avoids running form-processing logic on pages that do not need it.
	 */
	public static function maybe_render_form_head(): void {
		// We are called before the_post(), so we need to inspect the
		// global query to determine the post type and post ID.
		if ( ! is_singular( 'member-directory' ) ) {
			return;
		}

		$post_id = get_queried_object_id();

		if ( ! $post_id ) {
			return;
		}

		$viewer = PmpResolver::resolve_viewer( $post_id );

		if ( ! self::is_edit_mode( $post_id, $viewer ) ) {
			return;
		}

		acf_form_head();
	}

	/**
	 * Determine whether the profile page should render in edit mode.
	 *
	 * Edit mode is active when:
	 *   1. The post type is member-directory.
	 *   2. The viewer is the post author or an admin.
	 *   3. No ?view_as parameter is set (View As = preview, not edit).
	 *
	 * @param  int   $post_id  The member-directory post ID.
	 * @param  array $viewer   Viewer context from PmpResolver.
	 * @return bool            True = render edit form. False = render view.
	 */
	public static function is_edit_mode( int $post_id, array $viewer ): bool {
		// Only member-directory posts can have edit mode.
		if ( get_post_type( $post_id ) !== 'member-directory' ) {
			return false;
		}

		// Only the post author or an admin can edit.
		if ( empty( $viewer['is_author'] ) && empty( $viewer['is_admin'] ) ) {
			return false;
		}

		// View As mode overrides edit — the author is previewing, not editing.
		if ( isset( $_GET['view_as'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Render the ACF edit form for one section.
	 *
	 * Outputs the ACF form scoped to the content fields listed in the
	 * section's field_groups config. PMP system fields, tab dividers,
	 * and any fields present in the acf_group but not in field_groups
	 * are excluded — PMP controls are handled by the left-column JS.
	 *
	 * On submission, ACF saves the rendered fields and redirects back
	 * to the profile permalink.
	 *
	 * @param array $section  The section array from SectionRegistry.
	 * @param int   $post_id  The member-directory post ID.
	 */
	public static function render_edit_form( array $section, int $post_id ): void {
		$field_group_key = $section['acf_group_key'] ?? '';

		if ( empty( $field_group_key ) ) {
			return;
		}

		// Derive field keys directly from ACF — any field in the registered group
		// (content fields, tab dividers, per-field PMP companions) appears in the
		// form automatically after a sync. Only the two section-level system fields
		// managed by the left-panel sidebar controls are excluded.
		$raw_fields = acf_get_fields( $field_group_key ) ?: [];
		$field_keys = [];

		foreach ( $raw_fields as $f ) {
			if ( ( $f['type'] ?? '' ) === 'tab' ) {
				continue; // Left-panel tab buttons control field visibility — no ACF tab UI.
			}
			if ( preg_match( '/_(enabled|privacy_mode)$/', $f['key'] ?? '' ) ) {
				continue; // Managed by left-panel sidebar controls.
			}
			if ( str_contains( $f['key'] ?? '', '_pmp_' ) ) {
				continue; // Per-field PMP companions — rendered by custom JS controls, not ACF.
			}
			$field_keys[] = $f['key'];
		}

		if ( empty( $field_keys ) ) {
			return;
		}

		acf_form( [
			'post_id'            => $post_id,
			'field_groups'       => [ $field_group_key ],
			'fields'             => $field_keys,
			'return'             => get_permalink( $post_id ),
			'submit_value'       => 'Save',
			'updated_message'    => 'Profile updated.',
			'html_before_fields' => '',
			'html_after_fields'  => '',
		] );
	}

	// -----------------------------------------------------------------------
	// AJAX save
	// -----------------------------------------------------------------------

	/**
	 * AJAX handler: save ACF fields for one section without a full page reload.
	 *
	 * Expects $_POST:
	 *   nonce   — wp_create_nonce( 'md_save_nonce' )
	 *   post_id — int, the member-directory post being edited
	 *   acf     — array, ACF field values keyed by field key (field_md_*)
	 *
	 * Responds with wp_send_json_success / wp_send_json_error.
	 *
	 * Hooked via: add_action( 'wp_ajax_md_save_section', ... )
	 * Only logged-in users can trigger wp_ajax_* — anonymous requests use
	 * wp_ajax_nopriv_* which we deliberately do not register.
	 */
	public static function handle_ajax_save(): void {
		// 1. Verify nonce.
		if ( ! check_ajax_referer( 'md_save_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Security check failed.' ], 403 );
		}

		// 2. Validate post ID.
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( [ 'message' => 'Invalid post ID.' ], 400 );
		}

		// 3. Verify the post exists and is the correct type.
		if ( get_post_type( $post_id ) !== 'member-directory' ) {
			wp_send_json_error( [ 'message' => 'Invalid post.' ], 400 );
		}

		// 4. Permission check — only the post author or an admin may save.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
		}

		// 5. Save ACF field values.
		//    wp_unslash removes the magic-quotes WordPress adds to all $_POST data.
		//    update_field() handles type-specific sanitisation internally.
		$acf_fields = isset( $_POST['acf'] ) && is_array( $_POST['acf'] )
			? wp_unslash( $_POST['acf'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: [];

		foreach ( $acf_fields as $field_key => $value ) {
			$field_key = sanitize_text_field( $field_key );

			// Guard: only process valid ACF field keys (must start with 'field_').
			if ( strpos( $field_key, 'field_' ) !== 0 ) {
				continue;
			}

			update_field( $field_key, $value, $post_id );
		}

		wp_send_json_success( [ 'message' => 'Saved.' ] );
	}

	/**
	 * AJAX handler: save the enabled/disabled state for one section.
	 *
	 * Toggles the section's ACF true_false field (field_md_{key}_enabled).
	 * Storing 0 makes get_field() return PHP false, which the pill-nav.php
	 * partial reads as disabled. Storing 1 restores the enabled default.
	 *
	 * The primary section cannot be disabled — a server-side guard prevents it
	 * even if JS somehow allows the request through.
	 *
	 * Expects $_POST:
	 *   nonce       — wp_create_nonce( 'md_save_nonce' )
	 *   post_id     — int, the member-directory post being edited
	 *   section_key — string, e.g. 'profile' or 'business'
	 *   enabled     — '1' (enable) or '0' (disable)
	 *
	 * Action: wp_ajax_memdir_ajax_save_section_enabled
	 */
	public static function handle_save_section_enabled(): void {
		if ( ! check_ajax_referer( 'md_save_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Security check failed.' ], 403 );
		}

		$post_id     = isset( $_POST['post_id'] )     ? absint( $_POST['post_id'] )                                   : 0;
		$section_key = isset( $_POST['section_key'] ) ? sanitize_text_field( wp_unslash( $_POST['section_key'] ) ) : '';
		$enabled     = isset( $_POST['enabled'] )     ? (bool) absint( $_POST['enabled'] )                          : true;

		if ( ! $post_id || get_post_type( $post_id ) !== 'member-directory' ) {
			wp_send_json_error( [ 'message' => 'Invalid post.' ], 400 );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
		}

		// Validate section_key against known registered sections.
		$valid_keys = array_column( SectionRegistry::get_sections(), 'key' );
		if ( ! in_array( $section_key, $valid_keys, true ) ) {
			wp_send_json_error( [ 'message' => 'Invalid section key.' ], 400 );
		}

		// Primary section cannot be disabled — guard against rogue requests.
		$primary = get_field( 'member_directory_primary_section', $post_id ) ?: 'profile';
		if ( $section_key === $primary ) {
			wp_send_json_error( [ 'message' => 'Primary section cannot be disabled.' ], 400 );
		}

		// Save via the ACF field name so the value is always stored under the
		// meta key that get_field( 'member_directory_{key}_enabled' ) reads.
		// Using the name (not the key) guarantees a round-trip even when the
		// ACF field group hasn't been created for this section yet.
		$field_name = 'member_directory_' . $section_key . '_enabled';
		update_field( $field_name, $enabled ? 1 : 0, $post_id );

		wp_send_json_success( [
			'section_key' => $section_key,
			'enabled'     => $enabled,
		] );
	}

	/**
	 * AJAX handler: save the PMP (visibility) level for one section.
	 *
	 * Writes directly to the 4-state privacy_mode field:
	 *   'inherit'  — section defers to global PMP.
	 *   'public'   — explicit override: everyone sees the section.
	 *   'member'   — explicit override: logged-in users only.
	 *   'private'  — explicit override: author and admin only.
	 *
	 * ACF field written: member_directory_{section_key}_privacy_mode
	 * ACF field key:     field_md_{section_key}_privacy_mode
	 *
	 * Expects $_POST:
	 *   nonce       — wp_create_nonce( 'md_save_nonce' )
	 *   post_id     — int, the member-directory post being edited
	 *   section_key — string, e.g. 'profile' or 'business'
	 *   pmp         — 'inherit' | 'public' | 'member' | 'private'
	 *
	 * Action: wp_ajax_memdir_ajax_save_section_pmp
	 */
	public static function handle_save_section_pmp(): void {
		if ( ! check_ajax_referer( 'md_save_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Security check failed.' ], 403 );
		}

		$post_id     = isset( $_POST['post_id'] )     ? absint( $_POST['post_id'] )                                   : 0;
		$section_key = isset( $_POST['section_key'] ) ? sanitize_text_field( wp_unslash( $_POST['section_key'] ) ) : '';
		$pmp         = isset( $_POST['pmp'] )         ? sanitize_text_field( wp_unslash( $_POST['pmp'] ) )         : '';

		if ( ! $post_id || get_post_type( $post_id ) !== 'member-directory' ) {
			wp_send_json_error( [ 'message' => 'Invalid post.' ], 400 );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
		}

		$valid_keys = array_column( SectionRegistry::get_sections(), 'key' );
		if ( ! in_array( $section_key, $valid_keys, true ) ) {
			wp_send_json_error( [ 'message' => 'Invalid section key.' ], 400 );
		}

		if ( ! in_array( $pmp, [ 'inherit', 'public', 'member', 'private' ], true ) ) {
			wp_send_json_error( [ 'message' => 'Invalid PMP value.' ], 400 );
		}

		// Single 4-state field — save via the field name so the meta key matches
		// what get_field( 'member_directory_{key}_privacy_mode' ) reads.
		$field_name = 'member_directory_' . $section_key . '_privacy_mode';
		update_field( $field_name, $pmp, $post_id );

		wp_send_json_success( [ 'section_key' => $section_key, 'pmp' => $pmp ] );
	}

	/**
	 * AJAX handler: save the PMP (visibility) level for a single field.
	 *
	 * Writes to the per-field 4-state PMP companion field:
	 *   'inherit'  — field defers to section PMP.
	 *   'public'   — explicit override: everyone sees the field.
	 *   'member'   — explicit override: logged-in users only.
	 *   'private'  — explicit override: author and admin only.
	 *
	 * Uses the companion field NAME (not key) for update_field() so the
	 * save works even if the companion field is not formally registered
	 * in an ACF field group — ACF falls back to update_post_meta().
	 *
	 * Expects $_POST:
	 *   nonce          — wp_create_nonce( 'md_save_nonce' )
	 *   post_id        — int, the member-directory post being edited
	 *   companion_name — string, e.g. 'member_directory_field_pmp_business_name'
	 *   pmp            — 'inherit' | 'public' | 'member' | 'private'
	 *
	 * Action: wp_ajax_memdir_ajax_save_field_pmp
	 */
	public static function handle_save_field_pmp(): void {
		if ( ! check_ajax_referer( 'md_save_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Security check failed.' ], 403 );
		}

		$post_id        = isset( $_POST['post_id'] )        ? absint( $_POST['post_id'] )                                          : 0;
		$companion_name = isset( $_POST['companion_name'] ) ? sanitize_text_field( wp_unslash( $_POST['companion_name'] ) ) : '';
		$pmp            = isset( $_POST['pmp'] )            ? sanitize_text_field( wp_unslash( $_POST['pmp'] ) )            : '';

		if ( ! $post_id || get_post_type( $post_id ) !== 'member-directory' ) {
			wp_send_json_error( [ 'message' => 'Invalid post.' ], 400 );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
		}

		if ( ! in_array( $pmp, [ 'inherit', 'public', 'member', 'private' ], true ) ) {
			wp_send_json_error( [ 'message' => 'Invalid PMP value.' ], 400 );
		}

		// Companion name must follow the pattern member_directory_field_pmp_{section}_{suffix}.
		if ( strpos( $companion_name, 'member_directory_field_pmp_' ) !== 0 ) {
			wp_send_json_error( [ 'message' => 'Invalid companion name.' ], 400 );
		}

		// Save via field name — works whether or not the companion field is
		// formally registered in an ACF group.  ACF resolves field names to
		// post meta keys, falling back to update_post_meta() when no field
		// object exists.  get_field( $companion_name ) will read it back.
		update_field( $companion_name, $pmp, $post_id );

		wp_send_json_success( [ 'companion_name' => $companion_name, 'pmp' => $pmp ] );
	}

	/**
	 * AJAX handler: direct avatar upload — one image in, one image out.
	 *
	 * Receives a file upload, creates a WP attachment, updates the ACF image
	 * field, and deletes the previous attachment. Returns the new thumbnail URL
	 * so JS can update the header avatar preview immediately.
	 *
	 * Expects $_POST:
	 *   nonce     — wp_create_nonce( 'md_save_nonce' )
	 *   post_id   — int, the member-directory post being edited
	 *   field_key — string, ACF field key (field_md_…)
	 * Expects $_FILES:
	 *   image     — the uploaded image file
	 *
	 * Action: wp_ajax_memdir_ajax_upload_avatar
	 */
	public static function handle_avatar_upload(): void {
		if ( ! check_ajax_referer( 'md_save_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Security check failed.' ], 403 );
		}

		$post_id   = isset( $_POST['post_id'] )   ? absint( $_POST['post_id'] )                                        : 0;
		$field_key = isset( $_POST['field_key'] ) ? sanitize_text_field( wp_unslash( $_POST['field_key'] ) ) : '';

		if ( ! $post_id || get_post_type( $post_id ) !== 'member-directory' ) {
			wp_send_json_error( [ 'message' => 'Invalid post.' ], 400 );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
		}

		if ( strpos( $field_key, 'field_' ) !== 0 ) {
			wp_send_json_error( [ 'message' => 'Invalid field key.' ], 400 );
		}

		if ( empty( $_FILES['image'] ) ) {
			wp_send_json_error( [ 'message' => 'No file uploaded.' ], 400 );
		}

		// Get old attachment ID before uploading.
		$old_id = (int) ( get_field( $field_key, $post_id, false ) ?: 0 );

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_upload( 'image', $post_id );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( [ 'message' => $attachment_id->get_error_message() ], 500 );
		}

		// Update ACF field with the new attachment ID.
		update_field( $field_key, $attachment_id, $post_id );

		// Delete the old attachment (one in, one out).
		if ( $old_id && $old_id !== $attachment_id ) {
			wp_delete_attachment( $old_id, true );
		}

		$url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );

		wp_send_json_success( [ 'url' => $url, 'id' => $attachment_id ] );
	}

	// -----------------------------------------------------------------------
	// AJAX: Search taxonomy terms
	// -----------------------------------------------------------------------

	/**
	 * Search taxonomy terms by name.
	 *
	 * POST params:
	 *   taxonomy  (string) — taxonomy slug (e.g. mp2t_instruments)
	 *   search    (string) — search query
	 *   _wpnonce  (string) — nonce from memdir_vars.search_nonce
	 *
	 * Returns JSON { results: [ { id, text }, ... ] }
	 */
	public static function handle_search_taxonomy_terms(): void {
		check_ajax_referer( 'memdir_search_terms', '_wpnonce' );

		$taxonomy = sanitize_text_field( wp_unslash( $_POST['taxonomy'] ?? '' ) );
		$search   = sanitize_text_field( wp_unslash( $_POST['search']   ?? '' ) );

		if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			wp_send_json_error( 'Invalid taxonomy' );
		}

		$terms = get_terms( [
			'taxonomy'   => $taxonomy,
			'search'     => $search,
			'hide_empty' => false,
			'number'     => 25,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		if ( is_wp_error( $terms ) ) {
			wp_send_json_error( $terms->get_error_message() );
		}

		$results = [];
		foreach ( $terms as $term ) {
			$results[] = [
				'id'   => $term->term_id,
				'text' => $term->name,
			];
		}

		// Shuffle for variety
		shuffle( $results );

		wp_send_json( [ 'results' => $results ] );
	}
}
