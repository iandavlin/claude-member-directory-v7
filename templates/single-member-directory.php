<?php
/**
 * Template: Single Member Profile
 *
 * Renders the full profile page for one member-directory post.
 * TemplateLoader routes all member-directory single-post requests here
 * instead of the active theme template.
 *
 * The page renders in one of two modes:
 *
 *   EDIT MODE — The post author (or admin) sees an ACF-powered edit form
 *               for each section. acf_form_head() MUST fire before any HTML
 *               output, so AcfFormHelper::maybe_render_form_head() is the
 *               first call in this file, before get_header().
 *
 *   VIEW MODE — All other viewers (and authors using ?view_as) see the
 *               read-only profile rendered by FieldRenderer via
 *               section-view.php.
 *
 * Sections are driven by SectionRegistry. Each enabled section is rendered
 * by either templates/parts/section-edit.php or templates/parts/section-view.php,
 * depending on the mode.
 *
 * Planned additions (not yet included):
 *   - Left sidebar   (templates/parts/sidebar.php)
 *
 * HTML structure:
 *   .memdir-profile
 *     .memdir-profile__inner
 *       .memdir-sticky       ← profile-header + pill-nav (stick together)
 *       .memdir-sections     ← section loop
 *     .memdir-right-panel    ← fixed panel; inside profile for CSS var cascade
 */

use MemberDirectory\AcfFormHelper;
use MemberDirectory\PmpResolver;
use MemberDirectory\SectionRegistry;

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// acf_form_head() — MUST fire before any HTML output.
//
// AcfFormHelper checks whether this is an edit-mode request (author/admin,
// member-directory CPT, no ?view_as param). If so it calls acf_form_head()
// which processes form submissions and enqueues ACF's form assets. If not
// edit-mode, this is a no-op.
// ---------------------------------------------------------------------------

AcfFormHelper::maybe_render_form_head();

get_header();

// ---------------------------------------------------------------------------
// WordPress loop — bail with a footer if no post is found.
// ---------------------------------------------------------------------------

if ( ! have_posts() ) {
	get_footer();
	return;
}

the_post();

$post_id = get_the_ID();

// ---------------------------------------------------------------------------
// Build the viewer context.
// ---------------------------------------------------------------------------

$viewer = PmpResolver::resolve_viewer( $post_id );

// ---------------------------------------------------------------------------
// Capture privileged status from the REAL viewer before any spoof.
//
// $viewer may be replaced below by a spoofed context. We capture the genuine
// author/admin flag here so the right panel (and header badges) remain visible
// even while the author is using ?view_as.
// ---------------------------------------------------------------------------

$is_privileged = $viewer['is_author'] || $viewer['is_admin'];

// ---------------------------------------------------------------------------
// View As override.
//
// When an author or admin appends ?view_as=member or ?view_as=public to the
// URL, replace the real viewer with a spoofed one so they can preview what
// other viewer types would see. The spoof only applies if the current user is
// genuinely an author or admin — a regular visitor cannot spoof themselves.
// ---------------------------------------------------------------------------

if (
	isset( $_GET['view_as'] )
	&& ( $viewer['is_author'] || $viewer['is_admin'] )
) {
	$viewer = PmpResolver::spoof_viewer(
		sanitize_text_field( wp_unslash( $_GET['view_as'] ) )
	);
}

// ---------------------------------------------------------------------------
// Determine the rendering mode.
//
// Edit mode: post author or admin, member-directory CPT, no ?view_as param.
// View mode: everyone else, or author/admin with ?view_as set.
// ---------------------------------------------------------------------------

$is_edit = AcfFormHelper::is_edit_mode( $post_id, $viewer );

// ---------------------------------------------------------------------------
// Collect sections and set initial navigation state.
//
// SectionRegistry::get_sections() returns all registered sections sorted by
// their 'order' value. $active_section and $active_section_label default to
// "all" / "All sections" for the initial page load; JS updates them on
// section-pill clicks without a page reload.
// ---------------------------------------------------------------------------

$sections             = SectionRegistry::get_sections();
$active_section       = 'all';
$active_section_label = 'All sections';

?>
<div class="memdir-profile">
<div class="memdir-profile__inner">

	<?php // ----------------------------------------------------------------
	// STICKY ZONE — header + pills stick together as a unit.
	// ---------------------------------------------------------------- ?>
	<div class="memdir-sticky">

		<?php
		$profile_header = plugin_dir_path( __FILE__ ) . 'parts/profile-header.php';
		if ( file_exists( $profile_header ) ) {
			include $profile_header;
		}
		?>

		<?php
		$pill_nav = plugin_dir_path( __FILE__ ) . 'parts/pill-nav.php';
		if ( file_exists( $pill_nav ) ) {
			include $pill_nav;
		}
		?>

	</div><!-- /.memdir-sticky -->

	<?php // ----------------------------------------------------------------
	// SECTIONS — stacked content cards, one per enabled section.
	// ---------------------------------------------------------------- ?>
	<div class="memdir-sections">

		<?php foreach ( $sections as $section ) : ?>
			<?php
			// Check whether this section has been enabled by the post author.
			// The ACF field name follows the pattern: member_directory_{key}_enabled.
			// Default to enabled (true) when the field has never been saved so that
			// a freshly created profile shows all sections rather than none.
			$section_key     = $section['key'] ?? '';
			$section_enabled = get_field( 'member_directory_' . $section_key . '_enabled', $post_id );

			if ( $section_enabled === false ) {
				continue; // Author has explicitly disabled this section — skip it.
			}

			// Choose the partial based on the rendering mode.
			// $section, $post_id, and $viewer are available to the partial via
			// PHP's normal include scope — no extract() needed.
			$partial = $is_edit
				? plugin_dir_path( __FILE__ ) . 'parts/section-edit.php'
				: plugin_dir_path( __FILE__ ) . 'parts/section-view.php';

			if ( file_exists( $partial ) ) {
				include $partial;
			}
			?>
		<?php endforeach; ?>

	</div><!-- /.memdir-sections -->

</div><!-- /.memdir-profile__inner -->

<?php
// ---------------------------------------------------------------------------
// Right panel — author/admin only.
//
// Placed inside .memdir-profile (so CSS custom properties cascade to it) but
// outside .memdir-profile__inner (it is position:fixed, not in normal flow).
// Keyed off $is_privileged (real viewer, pre-spoof) so the View As toggle
// stays visible while the author is previewing other viewer types.
// ---------------------------------------------------------------------------

if ( $is_privileged ) :
	$right_panel = plugin_dir_path( __FILE__ ) . 'parts/right-panel.php';
	if ( file_exists( $right_panel ) ) {
		include $right_panel;
	}
endif;
?>

</div><!-- /.memdir-profile -->

<?php get_footer();
