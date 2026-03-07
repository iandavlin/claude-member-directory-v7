<?php
/**
 * Template: Single Member Profile
 *
 * Renders the full profile page for one member-directory post.
 * TemplateLoader routes all member-directory single-post requests here
 * instead of the active theme template.
 *
 * Modes:
 *   EDIT — author/admin sees ACF form per section (no ?view_as).
 *   VIEW — everyone else, or author/admin with ?view_as set.
 *
 * acf_form_head() MUST fire before any HTML output (Architecture Rule 1).
 *
 * HTML structure:
 *   .memdir-profile            ← CSS grid: 1fr | --md-panel-width
 *     .memdir-profile__main    ← left column: sticky + sections
 *       .memdir-sticky         ← profile-header + pill-nav (stick as unit)
 *       .memdir-sections       ← section loop
 *     .memdir-right-panel      ← right column: sticky controls panel
 */

use MemberDirectory\AcfFormHelper;
use MemberDirectory\PmpResolver;
use MemberDirectory\SectionRegistry;

defined( 'ABSPATH' ) || exit;

// Must fire before any HTML — processes ACF form submission + enqueues assets.
AcfFormHelper::maybe_render_form_head();

get_header();

if ( ! have_posts() ) {
	get_footer();
	return;
}

the_post();

$post_id = get_the_ID();

// Resolve viewer. Capture privileged flag from the REAL viewer before any
// ?view_as spoof — right panel and header badges key off this.
$viewer        = PmpResolver::resolve_viewer( $post_id );
$is_privileged = $viewer['is_author'] || $viewer['is_admin'];

// View As override — swap viewer for a spoofed context so author/admin can
// preview what member/public viewers see. Guard: only privileged users can spoof.
if ( isset( $_GET['view_as'] ) && $is_privileged ) {
	$viewer = PmpResolver::spoof_viewer(
		sanitize_text_field( wp_unslash( $_GET['view_as'] ) )
	);
}

// Rendering mode — false when ?view_as is active (spoofed viewer fails check).
$is_edit = AcfFormHelper::is_edit_mode( $post_id, $viewer );

$sections        = SectionRegistry::get_sections();
$primary_section = get_field( 'member_directory_primary_section', $post_id ) ?: 'profile';

// ---------------------------------------------------------------------------
// PERF: Pre-fetch shared data used by multiple partials.
//
// 1. $cached_acf_fields — acf_get_fields() per section group, fetched once
//    and reused by header-section.php, section-view.php, and section-edit.php.
//    Without this, the same group is loaded 2-3x per section per page load.
//
// 2. $all_post_meta — get_post_meta($post_id) fetched once and shared with
//    section-view.php and section-edit.php for batch field-PMP companion
//    lookups. Replaces N individual get_field() calls (one per content field)
//    with a single query. WordPress caches this internally too, but building
//    it here makes the intent explicit and primes the cache early.
//
// 3. $global_pmp — the profile-wide PMP default, read once and passed into
//    every section-view.php render instead of re-reading from ACF per section.
//
// Revert: delete these three variables; each partial will fall back to its
// own ACF/get_post_meta calls (functionally identical, just slower).
// ---------------------------------------------------------------------------
$cached_acf_fields = [];
foreach ( $sections as $_sec ) {
	$_gk = $_sec['acf_group_key'] ?? '';
	if ( $_gk && ! isset( $cached_acf_fields[ $_gk ] ) ) {
		$cached_acf_fields[ $_gk ] = acf_get_fields( $_gk ) ?: [];
	}
}
unset( $_sec, $_gk );

$all_post_meta = get_post_meta( $post_id );
$global_pmp    = get_field( 'member_directory_global_pmp', $post_id ) ?: 'public';

?>
<div class="memdir-profile">
	<div class="memdir-profile__main">

		<div class="memdir-sticky" data-primary-section="<?php echo esc_attr( $primary_section ); ?>">
			<?php
			// Render one header-wrap per section that declares a "header" tab in its ACF group.
			// JS shows the one matching the active pill; all others are hidden.
			//
			// PERF: Uses $cached_acf_fields instead of calling acf_get_fields()
			// again. The cache was built above — one call per group, shared with
			// section-view.php and section-edit.php partials.
			// Revert: replace $cached_acf_fields[$_hdr_group] with acf_get_fields($_hdr_group).
			foreach ( $sections as $section ) {
				$_hdr_group = $section['acf_group_key'] ?? '';
				if ( empty( $_hdr_group ) ) {
					continue;
				}
				$_hdr_fields = $cached_acf_fields[ $_hdr_group ] ?? [];
				if ( empty( $_hdr_fields ) || ! is_array( $_hdr_fields ) ) {
					continue;
				}
				$_has_header_tab = false;
				foreach ( $_hdr_fields as $_hf ) {
					if ( ( $_hf['type'] ?? '' ) === 'tab' && stripos( $_hf['label'] ?? '', 'header' ) !== false ) {
						$_has_header_tab = true;
						break;
					}
				}
				if ( ! $_has_header_tab ) {
					continue;
				}
				$_hdr_key = $section['key'] ?? '';
				?>
				<div class="memdir-header-wrap" data-header="<?php echo esc_attr( $_hdr_key ); ?>"<?php echo $_hdr_key === $primary_section ? '' : ' style="display:none"'; ?>>
					<?php include plugin_dir_path( __FILE__ ) . 'parts/header-section.php'; ?>
				</div>
				<?php
			}
			unset( $_hdr_group, $_hdr_fields, $_has_header_tab, $_hf, $_hdr_key );
			?>
			<?php include plugin_dir_path( __FILE__ ) . 'parts/pill-nav.php'; ?>
		</div>

		<div class="memdir-sections">
			<?php $_section_idx = 0; ?>
			<?php foreach ( $sections as $section ) : ?>
				<?php
				$section_color   = ( $_section_idx % 15 ) + 1;
				$_section_idx++;
				$section_key     = $section['key'] ?? '';
				$section_enabled = get_field( 'member_directory_' . $section_key . '_enabled', $post_id );

				// Primary section must always render — it can never be hidden, even
				// if its enabled flag was saved as false during a period when it was
				// a non-primary section.
				if ( $section_enabled === false && $section_key !== $primary_section ) {
					continue;
				}

				if ( $is_edit ) {
					include plugin_dir_path( __FILE__ ) . 'parts/section-edit.php';
				} else {
					// Buffer the view-mode render. $section_field_count is incremented
					// by section-view.php only when a field actually produces output.
					// If it stays 0 (all empty or all PMP-blocked) the section is
					// silently dropped; JS will hide its pill on load.
					$section_field_count = 0;
					ob_start();
					include plugin_dir_path( __FILE__ ) . 'parts/section-view.php';
					$section_html = ob_get_clean();
					if ( $section_field_count > 0 ) {
						echo $section_html;
					}
				}
				?>
			<?php endforeach; ?>
		</div>

	</div>

	<?php if ( $is_privileged ) include plugin_dir_path( __FILE__ ) . 'parts/right-panel.php'; ?>

</div>

<?php get_footer();
