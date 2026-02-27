<?php
/**
 * Template: Single Member Profile
 *
 * Renders the full read-only profile page for one member-directory post.
 * TemplateLoader routes all member-directory single-post requests here
 * instead of the active theme template.
 *
 * Sections are driven by SectionRegistry. Each enabled section is rendered
 * by templates/parts/section-view.php, which handles per-field PMP checks
 * and delegates HTML output to FieldRenderer.
 *
 * Planned additions (not yet included):
 *   - Sticky header (templates/parts/header.php)
 *   - Left sidebar   (templates/parts/sidebar.php)
 *   - Right panel    (templates/parts/right-panel.php) — View As + Global PMP
 */

use MemberDirectory\PmpResolver;
use MemberDirectory\SectionRegistry;

defined( 'ABSPATH' ) || exit;

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
// Collect enabled sections.
//
// SectionRegistry::get_sections() returns all registered sections sorted by
// their 'order' value. We filter to those the author has not disabled via the
// section-level enabled toggle stored as an ACF field on the post.
// ---------------------------------------------------------------------------

$sections = SectionRegistry::get_sections();

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

		// Include the section view partial.
		// $section, $post_id, and $viewer are available to the partial via
		// PHP's normal include scope — no extract() needed.
		// We use a direct filesystem include (not get_template_part) so the
		// plugin's own partial is always used regardless of theme overrides.
		$partial = plugin_dir_path( __FILE__ ) . 'parts/section-view.php';

		if ( file_exists( $partial ) ) {
			include $partial;
		}
		?>
	<?php endforeach; ?>

</div>

<?php get_footer();
