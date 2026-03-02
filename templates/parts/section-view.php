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

defined( 'ABSPATH' ) || exit;

$section_key    = $section['key']   ?? '';
$section_label  = $section['label'] ?? '';

// Derive content fields directly from ACF — any field added to the group and
// synced appears here automatically. Tab dividers, button_group fields (PMP
// companions and system selectors), and section-level system fields are excluded.
$group_key  = $section['acf_group_key'] ?? '';
$raw_fields = $group_key ? ( acf_get_fields( $group_key ) ?: [] ) : [];
$all_fields = array_values( array_filter( $raw_fields, static function ( array $f ): bool {
	$type = $f['type'] ?? '';
	$key  = $f['key']  ?? '';
	if ( $type === 'tab' )          return false; // Structural divider — no value.
	if ( $type === 'button_group' ) return false; // PMP companions and system selectors.
	if ( preg_match( '/_(enabled|privacy_mode|privacy_level)$/', $key ) ) return false;
	return true;
} ) );

// ---------------------------------------------------------------------------
// Resolve section PMP.
//
// Read privacy_mode / privacy_level pair.
//   privacy_mode  'inherit' → defer to global   'custom' → use privacy_level
//   privacy_level 'public' | 'member' | 'private' (only meaningful when custom)
// ---------------------------------------------------------------------------

// 4-state field: public | member | private | inherit (missing/null → inherit).
$section_pmp = (string) ( get_field( 'member_directory_' . $section_key . '_privacy_mode', $post_id ) ?: 'inherit' );

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
			// name: member_directory_field_pmp_{field_name_suffix}
			// Valid values: public, member, private, inherit.
			// Missing/null → treat as inherit so waterfall resolves naturally.
			// -----------------------------------------------------------------

			// Derive the companion ACF name from the content field's name, not its key.
			// Field name:  member_directory_business_name
			// Companion:   member_directory_field_pmp_business_name
			$field_name_suffix = preg_replace( '/^member_directory_/', '', $field['name'] ?? '' );
			$field_pmp = get_field( 'member_directory_field_pmp_' . $field_name_suffix, $post_id );

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
