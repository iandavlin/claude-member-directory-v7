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
 *
 * Optional (perf: set by caller to avoid redundant DB reads):
 *
 *   @var string $global_pmp       Pre-fetched global PMP value. If unset,
 *                                 this partial reads it from ACF.
 *   @var array  $all_post_meta    Pre-fetched get_post_meta($post_id) array.
 *                                 Used for batch field-PMP lookups instead
 *                                 of one get_field() per content field.
 *   @var array  $cached_acf_fields  Map of acf_group_key => acf_get_fields()
 *                                   result, built once in the parent template.
 */

use MemberDirectory\FieldRenderer;
use MemberDirectory\PmpResolver;
use MemberDirectory\SectionRegistry;

defined( 'ABSPATH' ) || exit;

$section_key    = $section['key']   ?? '';
$section_label  = $section['label'] ?? '';

// ---------------------------------------------------------------------------
// PERF: Use cached ACF fields if the parent template pre-fetched them.
// Avoids a duplicate acf_get_fields() call when the same group was already
// loaded for header-tab scanning in single-member-directory.php.
// Revert: remove $cached_acf_fields references; restore direct acf_get_fields().
// ---------------------------------------------------------------------------
$group_key  = $section['acf_group_key'] ?? '';
$raw_fields = [];
if ( $group_key ) {
	$raw_fields = ( isset( $cached_acf_fields[ $group_key ] ) )
		? $cached_acf_fields[ $group_key ]
		: ( acf_get_fields( $group_key ) ?: [] );
}
// ---------------------------------------------------------------------------
// Identify header-tab field keys so they can be excluded from the content
// area below. These fields are already rendered in the sticky header
// (header-section.php), so showing them again in the section body would
// duplicate visible content for member/public viewers.
//
// Uses the same tab-scanning logic as header-section.php: walk raw_fields,
// flip a flag when a tab labelled "header" is found, collect keys until
// the next tab (or end of list).
//
// Revert: remove $header_field_keys and the in_array() check in the filter.
// ---------------------------------------------------------------------------
$header_field_keys = [];
$_in_hdr_tab = false;
foreach ( $raw_fields as $_f ) {
	if ( ( $_f['type'] ?? '' ) === 'tab' ) {
		$_in_hdr_tab = ( stripos( $_f['label'] ?? '', 'header' ) !== false );
		continue;
	}
	if ( $_in_hdr_tab && ! empty( $_f['key'] ) ) {
		$header_field_keys[] = $_f['key'];
	}
}
unset( $_in_hdr_tab, $_f );

// ---------------------------------------------------------------------------
// Conditional tabs: [if:section_key] in a tab label.
//
// When a tab label contains [if:business] (for example), all fields under
// that tab are hidden when the Business section is disabled for this post.
// Uses the same walk-and-flag pattern as the header tab scan above.
//
// Revert: remove $conditional_excluded_keys and the in_array() check below.
// ---------------------------------------------------------------------------
$conditional_excluded_keys = [];
$_cond_active = true;
foreach ( $raw_fields as $_f ) {
	if ( ( $_f['type'] ?? '' ) === 'tab' ) {
		$_cond_active = true;
		if ( preg_match( '/\[if:([a-z0-9_-]+)\]/i', $_f['label'] ?? '', $_cm ) ) {
			$ref_enabled  = get_field( 'member_directory_' . $_cm[1] . '_enabled', $post_id );
			$_cond_active = ! empty( $ref_enabled );
		}
		continue;
	}
	if ( ! $_cond_active && ! empty( $_f['key'] ) ) {
		$conditional_excluded_keys[] = $_f['key'];
	}
}
unset( $_cond_active, $_cm, $_f );

$all_fields = array_values( array_filter( $raw_fields, static function ( array $f ) use ( $header_field_keys, $conditional_excluded_keys ): bool {
	$key  = $f['key']  ?? '';
	if ( SectionRegistry::is_system_field( $f ) )   return false; // Tabs, PMP companions, display_precision, etc.
	if ( in_array( $key, $header_field_keys, true ) ) return false; // Already in sticky header.
	if ( in_array( $key, $conditional_excluded_keys, true ) ) return false; // Disabled conditional tab.
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
//
// PERF: $global_pmp may already be set by the parent template to avoid
// one get_field() call per section render. Falls back to ACF read if unset.
// Revert: remove the isset() guard; always call get_field() directly.
// ---------------------------------------------------------------------------

if ( ! isset( $global_pmp ) ) {
	$global_pmp = get_field( 'member_directory_global_pmp', $post_id ) ?: 'public';
}
$effective_pmp = ( $section_pmp !== 'inherit' ) ? $section_pmp : $global_pmp;

?>
<!-- sv:2 -->
<div class="memdir-section" data-section="<?php echo esc_attr( $section_key ); ?>" data-color="<?php echo esc_attr( (string) ( $section_color ?? 0 ) ); ?>">

	<div class="memdir-field-content">
		<h2 class="memdir-section-title"><?php echo esc_html( $section_label ); ?></h2>

		<?php
		// -----------------------------------------------------------------
		// PERF: Batch field-PMP lookup from cached post meta.
		//
		// Instead of calling get_field() once per content field to read its
		// PMP companion value, we pull ALL post meta in one query and filter
		// for the 'member_directory_field_pmp_' prefix. This turns N queries
		// (one per field) into a single get_post_meta() call.
		//
		// $all_post_meta is optionally pre-fetched by the parent template.
		// WordPress caches get_post_meta($id) internally, so even without
		// the parent cache the first call primes WP's object cache and
		// subsequent calls are free.
		//
		// Revert: remove $pmp_meta_map and restore per-field get_field()
		// calls inside the foreach loop below.
		// -----------------------------------------------------------------
		if ( ! isset( $all_post_meta ) ) {
			$all_post_meta = get_post_meta( $post_id );
		}
		$pmp_meta_map = [];
		foreach ( $all_post_meta as $meta_key => $meta_values ) {
			if ( str_starts_with( $meta_key, 'member_directory_field_pmp_' ) ) {
				$pmp_meta_map[ $meta_key ] = $meta_values[0] ?? 'inherit';
			}
		}
		?>

		<?php foreach ( $all_fields as $field ) : ?>
			<?php
			// -----------------------------------------------------------------
			// Resolve field-level PMP from the batch map (no per-field query).
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
			$companion_meta_key = 'member_directory_field_pmp_' . $field_name_suffix;
			$field_pmp = $pmp_meta_map[ $companion_meta_key ] ?? 'inherit';

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
