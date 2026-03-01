<?php
/**
 * Partial: Section — Read-Only View Mode.
 *
 * Renders one section in view mode: section title + PmpResolver-filtered
 * FieldRenderer output. No controls panel — PMP controls are edit mode only.
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
$all_fields     = SectionRegistry::get_all_fields( $section );

// ---------------------------------------------------------------------------
// Resolve section PMP.
//
// Read privacy_mode / privacy_level pair.
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
// Default to 'public' so fields are visible when the global PMP field is unset.
// ---------------------------------------------------------------------------

$global_pmp    = get_field( 'member_directory_global_pmp', $post_id ) ?: 'public';
$effective_pmp = ( $section_pmp !== 'inherit' ) ? $section_pmp : $global_pmp;

?>
<div class="memdir-section" data-section="<?php echo esc_attr( $section_key ); ?>">

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

			// Buffer the render: FieldRenderer outputs nothing for empty/unsupported
			// values. Only count and echo when actual HTML is produced.
			ob_start();
			FieldRenderer::render( $field, $post_id );
			$field_html = ob_get_clean();
			if ( $field_html !== '' ) {
				$section_field_count = ( $section_field_count ?? 0 ) + 1;
				echo $field_html;
			}
			?>
		<?php endforeach; ?>

	</div>

</div><?php
// No closing PHP tag — intentional. Prevents accidental whitespace output.
