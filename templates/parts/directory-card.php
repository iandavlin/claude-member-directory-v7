<?php
/**
 * Partial: Directory Card — single member card in the directory grid.
 *
 * Receives $card array from Directory::extract_card_data():
 *   - post_id    (int)
 *   - permalink  (string)
 *   - title      (string)
 *   - avatar     (string) URL
 *   - banner     (string) URL
 *   - badges     (string[]) term names
 *   - location   (string)
 *   - social     (array[]) [ url, platform, svg ]
 *
 * @var array $card  Card data array.
 */

defined( 'ABSPATH' ) || exit;

$has_banner = ! empty( $card['banner'] );
$has_avatar = ! empty( $card['avatar'] );

?>
<a class="memdir-card<?php echo $has_banner ? ' memdir-card--has-banner' : ''; ?>" href="<?php echo esc_url( $card['permalink'] ); ?>">

	<div class="memdir-card__banner"
		<?php if ( $has_banner ) : ?>
			style="background-image: url(<?php echo esc_url( $card['banner'] ); ?>);"
		<?php endif; ?>
	>
		<?php if ( $has_avatar ) : ?>
		<img
			class="memdir-card__avatar"
			src="<?php echo esc_url( $card['avatar'] ); ?>"
			alt="<?php echo esc_attr( $card['title'] ); ?>"
			loading="lazy"
		>
		<?php endif; ?>
	</div>

	<div class="memdir-card__body">
		<h3 class="memdir-card__name"><?php echo esc_html( $card['title'] ); ?></h3>

		<?php if ( ! empty( $card['badges'] ) ) : ?>
		<div class="memdir-card__badges">
			<?php foreach ( $card['badges'] as $badge ) : ?>
				<span class="memdir-card__badge"><?php echo esc_html( $badge ); ?></span>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $card['location'] ) ) : ?>
		<p class="memdir-card__location"><?php echo esc_html( $card['location'] ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $card['social'] ) ) : ?>
		<div class="memdir-card__social">
			<?php foreach ( $card['social'] as $link ) : ?>
				<span
					class="memdir-card__social-icon memdir-card__social-icon--<?php echo esc_attr( $link['platform'] ); ?>"
					aria-label="<?php echo esc_attr( ucfirst( $link['platform'] ) ); ?>"
				><?php echo $link['svg']; ?></span>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
	</div>

</a>
<?php
// No closing PHP tag — intentional.
