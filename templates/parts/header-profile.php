<?php
/**
 * Partial: Profile Header — Member Profile variant.
 *
 * Renders the sticky header for members whose primary section is Profile
 * (or any non-Business primary). Outputs eyebrow, title, and placeholder
 * divs for subline and social links.
 *
 * Expected variables (set by the caller before include):
 *
 *   @var int $post_id  The member-directory post ID.
 */

defined( 'ABSPATH' ) || exit;

$title = (string) get_field( 'member_directory_profile_page_name', $post_id );

if ( empty( $title ) ) {
	$title = (string) get_the_title( $post_id );
}

?>
<header class="memdir-header memdir-header--profile">

	<div class="memdir-header__identity">

		<p class="memdir-header__eyebrow">MEMBER PROFILE</p>

		<h1 class="memdir-header__title"><?php echo esc_html( $title ); ?></h1>

		<?php // Subline: TBD — tagline + location from Profile section. See frontend-layout.md §Header. ?>
		<div class="memdir-header__subline"></div>

		<?php // Social links: TBD — global social fields shared across author posts. ?>
		<div class="memdir-header__social"></div>

	</div>

</header><?php
// No closing PHP tag — intentional. Prevents accidental whitespace output.
