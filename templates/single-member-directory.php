<?php
/**
 * Template: Single Member Profile
 *
 * Scaffold only — no section rendering, no PMP, no sidebar yet.
 * TemplateLoader routes member-directory single posts here instead of
 * the active theme template.
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="memdir-profile">
<?php if ( have_posts() ) : the_post(); ?>

	<p>
		<strong><?php the_title(); ?></strong>
		&mdash; Post ID: <?php echo esc_html( get_the_ID() ); ?>
	</p>

<?php endif; ?>
</div>

<?php get_footer();
