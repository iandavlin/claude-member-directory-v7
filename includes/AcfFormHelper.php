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
		// Reserved for future hooks (e.g. enqueue ACF form assets).
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
	 * Outputs the full ACF form (fields + submit button) scoped to the
	 * section's field group. On submission, ACF saves all fields and
	 * redirects back to the profile permalink.
	 *
	 * @param array $section  The section array from SectionRegistry.
	 * @param int   $post_id  The member-directory post ID.
	 */
	public static function render_edit_form( array $section, int $post_id ): void {
		$field_group_key = $section['acf_group']['key'] ?? '';

		if ( empty( $field_group_key ) ) {
			return;
		}

		acf_form( [
			'post_id'            => $post_id,
			'field_groups'       => [ $field_group_key ],
			'return'             => get_permalink( $post_id ),
			'submit_value'       => 'Save',
			'updated_message'    => 'Profile updated.',
			'html_before_fields' => '',
			'html_after_fields'  => '',
		] );
	}
}
