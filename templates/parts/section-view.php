<?php
/**
 * Partial: Section — Read-Only View Mode.
 *
 * Renders one section of a member profile in view mode.
 * Called from templates/single-member-directory.php.
 *
 * Expected variables (set by the caller before get_template_part / include):
 *
 *   @var array  $section  A section array from SectionRegistry::get_section().
 *                         Keys used: key, label, fields, pmp_default.
 *   @var int    $post_id  The member-directory post ID being viewed.
 *   @var array  $viewer   Viewer context from PmpResolver::resolve_viewer()
 *                         or PmpResolver::spoof_viewer() for View As mode.
 *
 * This partial is responsible only for visibility decisions and rendering.
 * It never modifies data. PMP checks are delegated entirely to PmpResolver;
 * HTML output is delegated entirely to FieldRenderer.
 */

use MemberDirectory\FieldRenderer;
use MemberDirectory\PmpResolver;

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// 1. Resolve the section-level PMP value.
//
// Each section has two stored ACF fields that together represent its PMP:
//
//   privacy_mode  — 'inherit' (defer to global) or 'custom' (override)
//   privacy_level — 'public', 'member', or 'private' (only meaningful
//                   when privacy_mode is 'custom')
//
// When mode is 'inherit', the section has no explicit override; PmpResolver
// will walk up to the global value. When mode is 'custom', the level value
// IS the explicit section-level PMP and PmpResolver will stop there.
// ---------------------------------------------------------------------------

$section_key = $section['key'] ?? '';

$section_privacy_mode  = get_field( 'member_directory_' . $section_key . '_privacy_mode',  $post_id );
$section_privacy_level = get_field( 'member_directory_' . $section_key . '_privacy_level', $post_id );

// Translate mode + level into the single PMP string PmpResolver expects.
$section_pmp = ( $section_privacy_mode === 'custom' )
	? (string) $section_privacy_level  // Explicit override — use the stored level.
	: 'inherit';                        // Defer to global.

// ---------------------------------------------------------------------------
// 2. Resolve the global PMP value.
//
// A single ACF field on the post stores the profile-wide default PMP.
// Global can never be 'inherit' — it is the top of the waterfall and always
// carries an explicit value. Default to 'member' as a safe fallback in case
// the field is missing (e.g. before the first sync or save).
// ---------------------------------------------------------------------------

$global_pmp = get_field( 'member_directory_global_pmp', $post_id );

if ( empty( $global_pmp ) ) {
	$global_pmp = 'member';
}

// ---------------------------------------------------------------------------
// 3. Render the section wrapper and loop through fields.
// ---------------------------------------------------------------------------
?>
<div class="memdir-section" data-section="<?php echo esc_attr( $section_key ); ?>">
	<h2 class="memdir-section-title"><?php echo esc_html( $section['label'] ?? '' ); ?></h2>

	<?php foreach ( $section['fields'] as $field ) : ?>
		<?php
		// -----------------------------------------------------------------
		// Resolve the field-level PMP value.
		//
		// Each content field has its own stored PMP override. The ACF field
		// name is constructed from a fixed prefix plus the field's own key
		// from the section config. Valid values: public, member, private,
		// or inherit (defer to section, then global).
		// -----------------------------------------------------------------

		$field_pmp = get_field( 'member_directory_field_pmp_' . ( $field['key'] ?? '' ), $post_id );

		// Treat missing/null as inherit so the waterfall resolves naturally.
		if ( empty( $field_pmp ) ) {
			$field_pmp = 'inherit';
		}

		// -----------------------------------------------------------------
		// PMP visibility check.
		//
		// Pass all three levels to PmpResolver::can_view(). It applies the
		// waterfall rule: field first, then section, then global. Author and
		// admin always pass. Ghost behavior: if can_view() returns false,
		// output nothing — no placeholder, no empty wrapper, nothing.
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

		// -----------------------------------------------------------------
		// Render the field.
		//
		// FieldRenderer knows nothing about PMP — it only knows how to turn
		// a field definition + post ID into HTML. PMP was fully resolved
		// above; by the time we reach here the field is confirmed visible.
		// -----------------------------------------------------------------

		FieldRenderer::render( $field, $post_id );
		?>
	<?php endforeach; ?>

</div><?php
// No closing PHP tag — intentional. Prevents accidental whitespace output.
