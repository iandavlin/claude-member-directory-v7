<?php
/**
 * Partial: Profile Header.
 *
 * Renders the sticky profile page header. Determines the profile vs business
 * variant from the member's primary section setting, then outputs the eyebrow,
 * title, subline placeholder, and (for author/admin) mode/viewing badges.
 *
 * Expected variables (set by the caller before include):
 *
 *   @var int    $post_id              The member-directory post ID.
 *   @var array  $viewer               Viewer context from PmpResolver::resolve_viewer().
 *                                     May be a spoofed viewer when ?view_as is active.
 *   @var string $active_section_label Label of the currently active section
 *                                     (e.g. "Profile", "Business", "All sections").
 *
 * Note on $is_privileged:
 *   single-member-directory.php captures $is_privileged from the REAL viewer
 *   before any ?view_as spoof. If it is in scope here, we use it so badges
 *   remain visible to the author even while previewing as Member/Public.
 *   If not in scope, we fall back to checking $viewer directly.
 */

use MemberDirectory\AcfFormHelper;

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Determine header variant.
//
// Read the primary section from the post's Global Controls field. A value of
// 'business' selects the Business header; everything else (profile, null,
// empty) falls back to the Profile header. This never changes as the user
// navigates between section pills — it reflects the primary section only.
// ---------------------------------------------------------------------------

$primary_section = get_field( 'member_directory_primary_section', $post_id );
$variant         = ( $primary_section === 'business' ) ? 'business' : 'profile';

// ---------------------------------------------------------------------------
// Resolve the privileged flag.
//
// Prefer the pre-spoof $is_privileged captured by the caller so badges stay
// visible while the author is using View As. Fall back to checking $viewer
// if the caller did not pre-compute it.
// ---------------------------------------------------------------------------

$show_badges = isset( $is_privileged )
	? (bool) $is_privileged
	: ( ! empty( $viewer['is_author'] ) || ! empty( $viewer['is_admin'] ) );

// Edit mode: AcfFormHelper checks post type, viewer author/admin status, and
// absence of ?view_as. Returns false when the viewer is spoofed, which is
// correct — the edit badge should not show in view-as mode.
$is_edit = $show_badges && AcfFormHelper::is_edit_mode( $post_id, $viewer );

// ---------------------------------------------------------------------------
// Resolve header content by variant.
// ---------------------------------------------------------------------------

$active_section_label = isset( $active_section_label ) ? (string) $active_section_label : '';

if ( $variant === 'business' ) {

	$eyebrow = 'BUSINESS PROFILE';
	$title   = (string) get_field( 'member_directory_business_name', $post_id );

} else {

	$eyebrow = 'MEMBER PROFILE';
	$title   = (string) get_field( 'member_directory_profile_page_name', $post_id );

}

// Fall back to the WP post title if the ACF title field has not been filled yet.
if ( empty( $title ) ) {
	$title = (string) get_the_title( $post_id );
}

?>
<header class="memdir-header memdir-header--<?php echo esc_attr( $variant ); ?>">

	<div class="memdir-header__identity">

		<p class="memdir-header__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>

		<h1 class="memdir-header__title"><?php echo esc_html( $title ); ?></h1>

		<?php // Subline: TBD — tagline + location from primary section. See frontend-layout.md §Header. ?>
		<div class="memdir-header__subline"></div>

		<?php // Social links: TBD — global social fields shared across author posts. ?>
		<div class="memdir-header__social"></div>

	</div>

	<?php if ( $show_badges ) : ?>
	<div class="memdir-header__badges">

		<?php if ( $is_edit ) : ?>
		<div class="memdir-header__badge memdir-header__badge--edit">Edit mode</div>
		<?php endif; ?>

		<?php if ( ! empty( $active_section_label ) ) : ?>
		<div class="memdir-header__badge memdir-header__badge--viewing">Viewing: <?php echo esc_html( $active_section_label ); ?></div>
		<?php endif; ?>

	</div>
	<?php endif; ?>

</header><?php
// No closing PHP tag — intentional. Prevents accidental whitespace output.
