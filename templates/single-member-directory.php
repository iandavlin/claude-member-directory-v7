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

?>
<div class="memdir-profile">
	<div class="memdir-profile__main">

		<div class="memdir-sticky" data-primary-section="<?php echo esc_attr( $primary_section ); ?>">
			<div class="memdir-header-wrap" data-header="profile"<?php echo $primary_section !== 'profile' ? ' style="display:none"' : ''; ?>>
				<?php include plugin_dir_path( __FILE__ ) . 'parts/header-profile.php'; ?>
			</div>
			<div class="memdir-header-wrap" data-header="business"<?php echo $primary_section !== 'business' ? ' style="display:none"' : ''; ?>>
				<?php include plugin_dir_path( __FILE__ ) . 'parts/header-business.php'; ?>
			</div>
			<?php include plugin_dir_path( __FILE__ ) . 'parts/pill-nav.php'; ?>
		</div>

		<div class="memdir-sections">
			<?php foreach ( $sections as $section ) : ?>
				<?php
				$section_key     = $section['key'] ?? '';
				$section_enabled = get_field( 'member_directory_' . $section_key . '_enabled', $post_id );

				if ( $section_enabled === false ) {
					continue;
				}

				$partial = $is_edit
					? plugin_dir_path( __FILE__ ) . 'parts/section-edit.php'
					: plugin_dir_path( __FILE__ ) . 'parts/section-view.php';

				include $partial;
				?>
			<?php endforeach; ?>
		</div>

	</div>

	<?php if ( $is_privileged ) include plugin_dir_path( __FILE__ ) . 'parts/right-panel.php'; ?>

</div>

<?php get_footer();
