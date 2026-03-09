<?php
/**
 * Partial: Section Header — generic, data-driven.
 *
 * Renders the sticky header for any section that has a tab whose label
 * contains the word "header" (case-insensitive). Fields within that tab
 * are mapped to display slots by type and field-name suffix:
 *
 *   First text field   → title (h1)
 *   Image fields       → avatar or banner (matched by suffix):
 *     Avatar suffixes:  _photo, _avatar, _headshot, _portrait → circular avatar
 *     Banner suffixes:  _banner, _cover, _header_image        → full-width banner
 *     Unmatched image:  first unmatched → avatar fallback
 *   taxonomy fields    → category badge pills
 *   url fields         → social icon links (matched by name suffix to
 *                        platform icons via inline SVGs)
 *
 * Layout: optional banner across full width, then identity (avatar + name)
 * left, badges + social icons right.
 *
 * Social platform suffixes (field name must end with one of):
 *   _website, _linkedin, _instagram, _twitter, _facebook,
 *   _youtube, _tiktok, _vimeo, _linktree
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

// Inline SVG icons keyed by platform suffix.
// Each SVG uses currentColor so it inherits the link's CSS color.
$social_platforms = [
	'website'   => [
		'label' => 'Website',
		'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>',
	],
	'linkedin'  => [
		'label' => 'LinkedIn',
		'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.064 2.064 0 1 1 0-4.128 2.064 2.064 0 0 1 0 4.128zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0z"/></svg>',
	],
	'instagram' => [
		'label' => 'Instagram',
		'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/></svg>',
	],
	'twitter'   => [
		'label' => 'X (Twitter)',
		'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
	],
	'facebook'  => [
		'label' => 'Facebook',
		'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
	],
	'youtube'   => [
		'label' => 'YouTube',
		'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>',
	],
	'tiktok'    => [
		'label' => 'TikTok',
		'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>',
	],
	'vimeo'     => [
		'label' => 'Vimeo',
		'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M23.977 6.416c-.105 2.338-1.739 5.543-4.894 9.609-3.268 4.247-6.026 6.37-8.29 6.37-1.409 0-2.578-1.294-3.553-3.881L5.322 11.4C4.603 8.816 3.834 7.522 3.01 7.522c-.179 0-.806.378-1.881 1.132L0 7.197c1.185-1.044 2.351-2.084 3.501-3.128C5.08 2.701 6.266 1.984 7.055 1.91c1.867-.18 3.016 1.1 3.447 3.838.465 2.953.789 4.789.971 5.507.539 2.45 1.131 3.674 1.776 3.674.502 0 1.256-.796 2.265-2.385 1.004-1.589 1.54-2.797 1.612-3.628.144-1.371-.395-2.061-1.614-2.061-.574 0-1.167.121-1.777.391 1.186-3.868 3.434-5.757 6.762-5.637 2.473.06 3.628 1.664 3.493 4.797z"/></svg>',
	],
	'linktree'  => [
		'label' => 'Linktree',
		'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M7.953 15.066l-.038.002-3.293-3.307L.915 15.46l3.721 3.72 3.33-3.293-.013-.822zm8.132 0l-.012.822 3.33 3.293 3.72-3.72-3.706-3.699-3.294 3.307-.038-.002zM11.993 0L7.88 4.112l4.113 4.113 4.112-4.112L11.993 0zm0 11.888L7.88 16l4.113 4.112L16.105 16l-4.112-4.112zm0 7.739l-1.387 1.386L11.993 22.4l1.387-1.387-1.387-1.386z"/></svg>',
	],
];

// Suffix lists for image field slot detection.
$avatar_suffixes = [ '_photo', '_avatar', '_headshot', '_portrait' ];
$banner_suffixes = [ '_banner', '_cover', '_header_image' ];

// ---------------------------------------------------------------------------
// PERF: Use cached ACF fields if the parent template pre-fetched them.
// Avoids a duplicate acf_get_fields() call — the same group was already
// loaded by single-member-directory.php for the header-tab scan.
// Revert: replace with  $raw_fields = acf_get_fields( $acf_group_key );
// ---------------------------------------------------------------------------
$raw_fields = ( isset( $cached_acf_fields[ $acf_group_key ] ) )
	? $cached_acf_fields[ $acf_group_key ]
	: acf_get_fields( $acf_group_key );

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
$avatar_value = null;   // circular avatar image
$banner_value = null;   // full-width banner image
$taxo_terms   = [];     // grouped: [ [ WP_Term|int|string, ... ], ... ]
$social_links = [];

$found_title  = false;
$found_avatar = false;
$found_banner = false;

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
			if ( ! empty( $value ) ) {
				// Match by suffix: avatar suffixes → avatar slot, banner suffixes → banner slot.
				$matched = false;

				if ( ! $found_banner ) {
					foreach ( $banner_suffixes as $sfx ) {
						if ( str_ends_with( $fname, $sfx ) ) {
							$banner_value = $value;
							$found_banner = true;
							$matched      = true;
							break;
						}
					}
				}

				if ( ! $matched && ! $found_avatar ) {
					foreach ( $avatar_suffixes as $sfx ) {
						if ( str_ends_with( $fname, $sfx ) ) {
							$avatar_value = $value;
							$found_avatar = true;
							$matched      = true;
							break;
						}
					}
				}

				// Fallback: first unmatched image → avatar (backward compat).
				if ( ! $matched && ! $found_avatar ) {
					$avatar_value = $value;
					$found_avatar = true;
				}
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
							'url'      => $value,
							'platform' => $suffix,
							'svg'      => $platform['svg'],
							'label'    => $platform['label'],
						];
						break;
					}
				}
			}
			break;
	}
}

// Fallback avatar: section default_avatar if member hasn't set one.
if ( ! $found_avatar && ! empty( $section['default_avatar'] ) ) {
	$avatar_value = (int) $section['default_avatar'];
	$found_avatar = true;
}

// Fallback title: post title.
if ( empty( $title_value ) ) {
	$title_value = (string) get_the_title( $post_id );
}

// Resolve banner image URL for the background.
$banner_url = '';
if ( $banner_value ) {
	if ( is_array( $banner_value ) ) {
		// ACF array return — prefer large size, fall back to full URL.
		$banner_url = $banner_value['sizes']['large'] ?? $banner_value['url'] ?? '';
	} else {
		$banner_url = (string) wp_get_attachment_image_url( (int) $banner_value, 'large' );
	}
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

$has_meta       = ! empty( $badge_names ) || ! empty( $social_links );
$has_banner     = ! empty( $banner_url );
$is_edit_mode   = ! empty( $is_edit ); // inherited from single-member-directory.php

// In edit mode, find the banner field key so JS can wire up the upload overlay.
$banner_field_key = '';
if ( $is_edit_mode ) {
	foreach ( $header_fields as $bf ) {
		if ( ( $bf['type'] ?? '' ) === 'image' ) {
			$bfname = $bf['name'] ?? '';
			foreach ( $banner_suffixes as $sfx ) {
				if ( str_ends_with( $bfname, $sfx ) ) {
					$banner_field_key = $bf['key'] ?? '';
					break 2;
				}
			}
		}
	}
}

// Show banner in edit mode even when empty (so JS can render the upload overlay).
$show_banner = $has_banner || ( $is_edit_mode && ! empty( $banner_field_key ) );

?>
<header class="memdir-header memdir-header--<?php echo esc_attr( $section_key ); ?><?php echo $show_banner ? ' memdir-header--has-banner' : ''; ?>">

	<?php if ( $show_banner ) : ?>
	<div class="memdir-header__banner<?php echo ! $has_banner ? ' memdir-header__banner--empty' : ''; ?>"
		<?php if ( $has_banner ) : ?> style="background-image: url(<?php echo esc_url( $banner_url ); ?>);"<?php endif; ?>
		role="img"
		aria-label="<?php echo esc_attr( $title_value ); ?> banner"
		<?php if ( $is_edit_mode && $banner_field_key ) : ?> data-banner-field-key="<?php echo esc_attr( $banner_field_key ); ?>"<?php endif; ?>
	></div>
	<?php endif; ?>

	<div class="memdir-header__body<?php echo $has_banner ? ' memdir-header__body--with-banner' : ''; ?>">

		<div class="memdir-header__identity">

			<div class="memdir-header__avatar-col">
				<?php if ( $avatar_value ) : ?>
				<div class="memdir-header__avatar-wrap">
					<?php if ( is_array( $avatar_value ) ) : ?>
						<img
							class="memdir-header__avatar"
							src="<?php echo esc_url( $avatar_value['sizes']['thumbnail'] ?? $avatar_value['url'] ?? '' ); ?>"
							alt="<?php echo esc_attr( $avatar_value['alt'] ?? '' ); ?>"
						>
					<?php else : ?>
						<?php $img_src = wp_get_attachment_image_url( (int) $avatar_value, 'thumbnail' ); ?>
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
				<p class="memdir-header__eyebrow"><?php echo esc_html( strtoupper( $section_label ) ); ?></p>
			</div>

			<div class="memdir-header__text">
				<h1 class="memdir-header__title"><?php echo esc_html( $title_value ); ?></h1>
				<?php
				// Location subtitle — pulled from the Location section's google_map field.
				$loc_value     = get_field( 'member_directory_location_location', $post_id );
				$loc_precision = get_field( 'member_directory_location_display_precision', $post_id ) ?: 'city';
				if ( is_array( $loc_value ) && ! empty( $loc_value['address'] ) ) {
					$loc_display = \MemberDirectory\FieldRenderer::format_location( $loc_value, $loc_precision );
					if ( ! empty( $loc_display ) ) :
				?>
				<p class="memdir-header__location"><?php echo esc_html( $loc_display ); ?></p>
				<?php endif; } ?>
			</div>

		</div>

		<?php if ( $has_meta || $is_edit_mode ) : ?>
		<div class="memdir-header__meta">

			<?php if ( ! empty( $badge_names ) ) : ?>
			<div class="memdir-header__taxo">
				<?php foreach ( $badge_names as $badge_name ) : ?>
					<span class="memdir-header__taxo-badge"><?php echo esc_html( $badge_name ); ?></span>
				<?php endforeach; ?>
			</div>
			<?php elseif ( $is_edit_mode ) : ?>
			<div class="memdir-header__taxo">
				<span class="memdir-header__placeholder">Edit Quick Focus</span>
			</div>
			<?php endif; ?>

			<?php if ( ( ! empty( $badge_names ) || $is_edit_mode ) && ( ! empty( $social_links ) || $is_edit_mode ) ) : ?>
			<span class="memdir-header__divider" aria-hidden="true"></span>
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
					><?php echo $link['svg']; ?></a>
				<?php endforeach; ?>
			</div>
			<?php elseif ( $is_edit_mode ) : ?>
			<div class="memdir-header__social">
				<span class="memdir-header__placeholder">Add Links</span>
			</div>
			<?php endif; ?>

		</div>
		<?php endif; ?>

		<?php
		$author_user_id  = (int) get_post_field( 'post_author', $post_id );
		$messaging_access = \MemberDirectory\Messaging::get_access( $post_id );
		$access_label     = \MemberDirectory\Messaging::get_access_label( $messaging_access );

		$envelope_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>';

		if ( $is_edit_mode && \MemberDirectory\Messaging::is_available() ) :
			// Edit mode — settings button showing current access state.
		?>
		<button type="button"
		        class="memdir-header__message-btn memdir-header__message-btn--edit"
		        data-action="messaging-settings"
		        data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"
		        data-messaging-access="<?php echo esc_attr( $messaging_access ); ?>">
			<?php echo $envelope_svg; ?>
			<span class="memdir-header__message-btn-text">
				<span class="memdir-header__message-btn-state"><?php echo esc_html( $access_label ); ?></span>
				<span class="memdir-header__message-btn-label">Messages</span>
			</span>
		</button>
		<?php
		elseif (
			! $is_edit_mode
			&& is_user_logged_in()
			&& get_current_user_id() !== $author_user_id
			&& \MemberDirectory\Messaging::can_message( $post_id, get_current_user_id() )
		) :
			// View mode — send message button (only if access allows).
		?>
		<button type="button"
		        class="memdir-header__message-btn"
		        data-action="send-message"
		        data-recipient-id="<?php echo esc_attr( (string) $author_user_id ); ?>">
			<?php echo $envelope_svg; ?>
			Message
		</button>
		<?php endif; ?>

	</div>

</header><?php
// No closing PHP tag — intentional. Prevents accidental whitespace output.
