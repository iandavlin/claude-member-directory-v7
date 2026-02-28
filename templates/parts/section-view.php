<?php
/**
 * Partial: Section — Read-Only View Mode.
 *
 * Renders one section in view mode using the two-column layout:
 *   Left  — section controls (title, PMP buttons, Override, field list)
 *   Right — section title + PmpResolver-filtered FieldRenderer output
 *
 * PMP checks are delegated entirely to PmpResolver; HTML output is
 * delegated entirely to FieldRenderer. This partial only orchestrates.
 *
 * Expected variables (set by the caller before include):
 *
 *   @var array  $section  Section array from SectionRegistry. Keys used:
 *                         key, label, field_groups[], pmp_default.
 *   @var int    $post_id  The member-directory post ID being viewed.
 *   @var array  $viewer   Viewer context from PmpResolver::resolve_viewer()
 *                         or PmpResolver::spoof_viewer() for View As mode.
 */

use MemberDirectory\FieldRenderer;
use MemberDirectory\PmpResolver;
use MemberDirectory\SectionRegistry;

defined( 'ABSPATH' ) || exit;

$section_key    = $section['key']   ?? '';
$section_label  = $section['label'] ?? '';
$field_groups   = SectionRegistry::get_field_groups( $section );
$all_fields     = SectionRegistry::get_all_fields( $section );

// ---------------------------------------------------------------------------
// Resolve section PMP.
//
// Read privacy_mode / privacy_level pair — same fields used by section-edit.php
// so the controls reflect the same effective level in both modes.
//
//   privacy_mode  'inherit' → defer to global   'custom' → use privacy_level
//   privacy_level 'public' | 'member' | 'private' (only meaningful when custom)
// ---------------------------------------------------------------------------

$section_privacy_mode  = get_field( 'member_directory_' . $section_key . '_privacy_mode',  $post_id );
$section_privacy_level = get_field( 'member_directory_' . $section_key . '_privacy_level', $post_id );

$section_pmp = ( $section_privacy_mode === 'custom' )
	? (string) $section_privacy_level  // Explicit override — use the stored level.
	: 'inherit';                        // Defer to global.

// ---------------------------------------------------------------------------
// Resolve global PMP.
//
// Profile-wide default. Cannot itself be 'inherit' — top of the waterfall.
// Default to 'member' as a safe fallback.
// ---------------------------------------------------------------------------

$global_pmp    = get_field( 'member_directory_global_pmp', $post_id ) ?: 'member';
$effective_pmp = ( $section_pmp !== 'inherit' ) ? $section_pmp : $global_pmp;

?>
<div class="memdir-section" data-section="<?php echo esc_attr( $section_key ); ?>">

	<div class="memdir-section-controls">

		<p class="memdir-section-controls__title"><?php echo esc_html( $section_label ); ?></p>

		<div class="memdir-section-controls__pmp">
			<button type="button" class="memdir-section-controls__pmp-btn memdir-section-controls__pmp-btn--public<?php echo $effective_pmp === 'public'  ? ' memdir-section-controls__pmp-btn--active' : ''; ?>" data-pmp="public">🌐</button>
			<button type="button" class="memdir-section-controls__pmp-btn memdir-section-controls__pmp-btn--member<?php echo $effective_pmp === 'member'  ? ' memdir-section-controls__pmp-btn--active' : ''; ?>" data-pmp="member">👥</button>
			<button type="button" class="memdir-section-controls__pmp-btn memdir-section-controls__pmp-btn--private<?php echo $effective_pmp === 'private' ? ' memdir-section-controls__pmp-btn--active' : ''; ?>" data-pmp="private">🔒</button>
			<button type="button" class="memdir-section-controls__override">Override</button>
		</div>

		<div class="memdir-section-controls__tabs">
			<?php foreach ( $field_groups as $group ) : ?>
			<button type="button" class="memdir-section-controls__tab-item" data-tab="<?php echo esc_attr( $group['tab'] ?? '' ); ?>">
				<?php echo esc_html( $group['tab'] ?? '' ); ?>
			</button>
			<?php endforeach; ?>
		</div>

	</div>

	<div class="memdir-field-content">
		<h2 class="memdir-section-title"><?php echo esc_html( $section_label ); ?></h2>

		<?php foreach ( $all_fields as $field ) : ?>
			<?php
			// -----------------------------------------------------------------
			// Resolve field-level PMP.
			//
			// Each content field has its own stored PMP override. ACF field
			// name: member_directory_field_pmp_{field_key}
			// Valid values: public, member, private, inherit.
			// Missing/null → treat as inherit so waterfall resolves naturally.
			// -----------------------------------------------------------------

			$field_pmp = get_field( 'member_directory_field_pmp_' . ( $field['key'] ?? '' ), $post_id );

			if ( empty( $field_pmp ) ) {
				$field_pmp = 'inherit';
			}

			// -----------------------------------------------------------------
			// PMP visibility check.
			//
			// Waterfall: field → section → global. Author and admin always
			// pass. Ghost behavior: false → output nothing, no empty wrapper.
			// -----------------------------------------------------------------

			$visible = PmpResolver::can_view(
				[
					'field_pmp'   => (string) $field_pmp,
					'section_pmp' => $section_pmp,
					'global_pmp'  => (string) $global_pmp,
				],
				$viewer
			);

			if ( ! $visible ) {
				continue; // Ghost — this field does not exist for this viewer.
			}

			FieldRenderer::render( $field, $post_id );
			?>
		<?php endforeach; ?>

	</div>

</div><?php
// No closing PHP tag — intentional. Prevents accidental whitespace output.
