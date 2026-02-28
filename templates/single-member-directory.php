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
// For a CPT single page this should always resolve to one post, but we
// follow the standard WP loop pattern for correctness.
// ---------------------------------------------------------------------------

if ( ! have_posts() ) {
	get_footer();
	return;
}

the_post();

$post_id = get_the_ID();

// ---------------------------------------------------------------------------
// Build the viewer context.
//
// resolve_viewer() checks whether the current user is logged in, whether they
// are the post author, and whether they have manage_options (admin).
// ---------------------------------------------------------------------------

$viewer = PmpResolver::resolve_viewer( $post_id );

// ---------------------------------------------------------------------------
// Capture privileged status from the REAL viewer before any spoof.
//
// $viewer may be replaced below by a spoofed context. We capture the genuine
// author/admin flag here so the right panel (which contains the View As toggle
// itself) remains visible even while the author is using ?view_as.
// ---------------------------------------------------------------------------

$is_privileged = $viewer['is_author'] || $viewer['is_admin'];

// ---------------------------------------------------------------------------
// Render the sticky profile header.
//
// Must be included before the View As spoof so $viewer is the real viewer.
// The partial accesses $is_privileged from scope. Default section label is
// "All sections" for the initial page load.
// ---------------------------------------------------------------------------

$active_section_label = 'All sections';
$profile_header       = plugin_dir_path( __FILE__ ) . 'parts/profile-header.php';
if ( file_exists( $profile_header ) ) {
	include $profile_header;
}

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
// Collect enabled sections.
//
// SectionRegistry::get_sections() returns all registered sections sorted by
// their 'order' value. We filter to those the author has not disabled via the
// section-level enabled toggle stored as an ACF field on the post.
// ---------------------------------------------------------------------------

$sections       = SectionRegistry::get_sections();
$active_section = 'all';

// ---------------------------------------------------------------------------
// Render the pill navigation.
//
// Included after $sections is computed (pill-nav needs it for the enabled
// count and per-section state) and after the View As spoof so $viewer is
// in its final state for this request.
// ---------------------------------------------------------------------------

$pill_nav = plugin_dir_path( __FILE__ ) . 'parts/pill-nav.php';
if ( file_exists( $pill_nav ) ) {
	include $pill_nav;
}

?>
<div class="memdir-profile">

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
		// We use a direct filesystem include (not get_template_part) so the
		// plugin's own partial is always used regardless of theme overrides.
		$partial = $is_edit
			? plugin_dir_path( __FILE__ ) . 'parts/section-edit.php'
			: plugin_dir_path( __FILE__ ) . 'parts/section-view.php';

		if ( file_exists( $partial ) ) {
			include $partial;
		}
		?>
	<?php endforeach; ?>

</div>

<?php
// ---------------------------------------------------------------------------
// Right panel — author/admin only.
//
// Always keyed off $is_privileged (set from the real viewer before any spoof)
// so the View As toggle stays visible while the author is previewing other
// viewer types via ?view_as.
// ---------------------------------------------------------------------------

if ( $is_privileged ) :
	$right_panel = plugin_dir_path( __FILE__ ) . 'parts/right-panel.php';
	if ( file_exists( $right_panel ) ) {
		include $right_panel;
	}
endif;
?>

<?php get_footer();
