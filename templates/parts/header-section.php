<?php
/**
 * Partial: Section Header — generic, data-driven.
 *
 * Renders the sticky header for any section that has a tab whose label
 * contains the word "header" (case-insensitive). Fields within that tab
 * are mapped to display slots by type and field-name suffix:
 *
 *   First text field   → title (h1)
 *   First image field  → avatar (72×72 circle)
 *   taxonomy fields    → category badge pills
 *   url fields         → social icon links (matched by name suffix to
 *                        platform icons via Font Awesome)
 *
 * Social platform suffixes (field name must end with one of):
 *   _website, _linkedin, _instagram, _twitter, _facebook,
 *   _youtube, _tiktok, _vimeo
 *
 * Expected variables (set by the caller before include):
 *
 *   @var array $section  Section config array from SectionRegistry.
 *   @var int   $post_id  The member-directory post ID.
 */

defined( 'ABSPATH' ) || exit;

use MemberDirectory\SectionRegistry;

$section_key   = $section['key']           ?? '';
$section_label = $section['label']         ?? $section_key;
$acf_group_key = $section['acf_group_key'] ?? '';

if ( empty( $acf_group_key ) ) {
	return;
}

// Platform suffix → Font Awesome icon map.
$social_platforms = [
	'website'   => [ 'label' => 'Website',   'fa_prefix' => 'fas', 'fa_icon' => 'globe' ],
	'linkedin'  => [ 'label' => 'LinkedIn',  'fa_prefix' => 'fab', 'fa_icon' => 'linkedin' ],
	'instagram' => [ 'label' => 'Instagram', 'fa_prefix' => 'fab', 'fa_icon' => 'instagram' ],
	'twitter'   => [ 'label' => 'Twitter',   'fa_prefix' => 'fab', 'fa_icon' => 'twitter' ],
	'facebook'  => [ 'label' => 'Facebook',  'fa_prefix' => 'fab', 'fa_icon' => 'facebook' ],
	'youtube'   => [ 'label' => 'YouTube',   'fa_prefix' => 'fab', 'fa_icon' => 'youtube' ],
	'tiktok'    => [ 'label' => 'TikTok',    'fa_prefix' => 'fab', 'fa_icon' => 'tiktok' ],
	'vimeo'     => [ 'label' => 'Vimeo',     'fa_prefix' => 'fab', 'fa_icon' => 'vimeo' ],
	'linktree'  => [ 'label' => 'Linktree',  'fa_prefix' => 'fab', 'fa_icon' => 'linktree' ],
];

// Fetch all raw fields for this section's ACF group.
$raw_fields = acf_get_fields( $acf_group_key );

if ( empty( $raw_fields ) || ! is_array( $raw_fields ) ) {
	return;
}

// Collect only fields that sit inside a tab whose label contains "header".
$in_header_tab = false;
$header_fields = [];

foreach ( $raw_fields as $f ) {
	if ( ( $f['type'] ?? '' ) === 'tab' ) {
		$in_header_tab = ( stripos( $f['label'] ?? '', 'header' ) !== false );
		continue;
	}
	if ( $in_header_tab ) {
		$header_fields[] = $f;
	}
}

if ( empty( $header_fields ) ) {
	return; // No header tab found — nothing to render.
}

// Resolve display slots from header tab fields.
$title_value  = '';
$image_value  = null;
$taxo_terms   = []; // grouped: [ [ WP_Term|int|string, ... ], ... ]
$social_links = [];

$found_title = false;
$found_image = false;

foreach ( $header_fields as $f ) {
	$ftype = $f['type'] ?? '';
	$fname = $f['name'] ?? '';
	$fkey  = $f['key']  ?? '';

	// Skip system fields (enabled toggle, privacy mode) and PMP companions.
	if ( SectionRegistry::is_system_field( $f ) || str_contains( $fkey, '_pmp_' ) ) {
		continue;
	}

	$value = get_field( $fname, $post_id );

	switch ( $ftype ) {
		case 'text':
			if ( ! $found_title && ! empty( $value ) ) {
				$title_value = (string) $value;
				$found_title = true;
			}
			break;

		case 'image':
			if ( ! $found_image && ! empty( $value ) ) {
				$image_value = $value;
				$found_image = true;
			}
			break;

		case 'taxonomy':
			if ( ! empty( $value ) ) {
				$taxo_terms[] = is_array( $value ) ? $value : [ $value ];
			}
			break;

		case 'url':
			if ( ! empty( $value ) ) {
				foreach ( $social_platforms as $suffix => $platform ) {
					if ( str_ends_with( $fname, '_' . $suffix ) ) {
						$social_links[] = [
							'url'       => $value,
							'platform'  => $suffix,
							'fa_prefix' => $platform['fa_prefix'],
							'fa_icon'   => $platform['fa_icon'],
							'label'     => $platform['label'],
						];
						break;
					}
				}
			}
			break;
	}
}

// Fallback title: post title.
if ( empty( $title_value ) ) {
	$title_value = (string) get_the_title( $post_id );
}

// Flatten taxonomy term groups into a single list of display names.
$badge_names = [];
foreach ( $taxo_terms as $group ) {
	foreach ( $group as $term ) {
		if ( $term instanceof WP_Term ) {
			$badge_names[] = $term->name;
		} elseif ( is_array( $term ) && isset( $term['name'] ) ) {
			$badge_names[] = $term['name'];
		} elseif ( is_int( $term ) ) {
			$t = get_term( $term );
			if ( $t instanceof WP_Term ) {
				$badge_names[] = $t->name;
			}
		} elseif ( is_string( $term ) && ! empty( $term ) ) {
			$badge_names[] = $term;
		}
	}
}

?>
<header class="memdir-header memdir-header--<?php echo esc_attr( $section_key ); ?>">

	<div class="memdir-header__body">

		<?php if ( $image_value ) : ?>
		<div class="memdir-header__image-wrap">
			<?php if ( is_array( $image_value ) ) : ?>
				<img
					class="memdir-header__avatar"
					src="<?php echo esc_url( $image_value['sizes']['thumbnail'] ?? $image_value['url'] ?? '' ); ?>"
					alt="<?php echo esc_attr( $image_value['alt'] ?? '' ); ?>"
				>
			<?php else : ?>
				<?php $img_src = wp_get_attachment_image_url( (int) $image_value, 'thumbnail' ); ?>
				<?php if ( $img_src ) : ?>
				<img
					class="memdir-header__avatar"
					src="<?php echo esc_url( $img_src ); ?>"
					alt="<?php echo esc_attr( $title_value ); ?>"
				>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<div class="memdir-header__text">

			<p class="memdir-header__eyebrow"><?php echo esc_html( strtoupper( $section_label ) ); ?></p>

			<h1 class="memdir-header__title"><?php echo esc_html( $title_value ); ?></h1>

			<?php if ( ! empty( $badge_names ) ) : ?>
			<div class="memdir-header__taxo">
				<?php foreach ( $badge_names as $badge_name ) : ?>
					<span class="memdir-header__taxo-badge"><?php echo esc_html( $badge_name ); ?></span>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<?php if ( ! empty( $social_links ) ) : ?>
			<div class="memdir-header__social">
				<?php foreach ( $social_links as $link ) : ?>
					<a
						href="<?php echo esc_url( $link['url'] ); ?>"
						class="memdir-social-link memdir-social-link--<?php echo esc_attr( $link['platform'] ); ?>"
						target="_blank"
						rel="noopener noreferrer"
						aria-label="<?php echo esc_attr( $link['label'] ); ?>"
					><i class="<?php echo esc_attr( $link['fa_prefix'] . ' fa-' . $link['fa_icon'] ); ?>" aria-hidden="true"></i></a>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

		</div>

	</div>

</header><?php
// No closing PHP tag — intentional. Prevents accidental whitespace output.
