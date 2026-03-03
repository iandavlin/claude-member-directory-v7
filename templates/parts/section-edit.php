<?php
/**
 * Partial: Section — Edit Mode.
 *
 * Renders one section in edit mode using the two-column layout:
 *   Left  — section controls (title, PMP buttons, eyebrow, field list)
 *   Right — section title + ACF form via AcfFormHelper::render_edit_form()
 *
 * acf_form_head() must have already been called before any HTML output
 * (handled by AcfFormHelper::maybe_render_form_head() in the single template).
 *
 * Expected variables (set by the caller before include):
 *
 *   @var array  $section  Section array from SectionRegistry. Keys used:
 *                         key, label, field_groups[], acf_group.
 *   @var int    $post_id  The member-directory post ID being edited.
 *   @var array  $viewer   Viewer context from PmpResolver::resolve_viewer().
 */

use MemberDirectory\AcfFormHelper;

defined( 'ABSPATH' ) || exit;

$section_key    = $section['key']   ?? '';
$section_label  = $section['label'] ?? '';

// Derive tab groups directly from ACF — any field added to the group and synced
// is reflected in the tab list automatically. Section-level system fields are
// excluded. Content fields and their per-field PMP companions are both included
// so the JS tab filter controls them together.
$group_key     = $section['acf_group_key'] ?? '';
$raw_fields    = $group_key ? ( acf_get_fields( $group_key ) ?: [] ) : [];
$field_groups  = [];
$current_tab   = 'General';
$current_keys  = [];

foreach ( $raw_fields as $f ) {
	$fkey  = $f['key']  ?? '';
	$ftype = $f['type'] ?? '';
	if ( preg_match( '/_(enabled|privacy_mode)$/', $fkey ) ) {
		continue; // Section-level system fields — managed by sidebar controls.
	}
	if ( $ftype === 'tab' ) {
		if ( ! empty( $current_keys ) ) {
			$field_groups[] = [ 'tab' => $current_tab, 'field_keys' => $current_keys ];
		}
		$current_tab  = $f['label'] ?? 'Tab';
		$current_keys = [];
	} else {
		$current_keys[] = $fkey;
	}
}
if ( ! empty( $current_keys ) ) {
	$field_groups[] = [ 'tab' => $current_tab, 'field_keys' => $current_keys ];
}

// ---------------------------------------------------------------------------
// Build per-field PMP data for JS injection of custom PMP controls.
//
// For each content field, derive the companion ACF field key and read the
// currently stored PMP value. Passed to JS via data-field-pmp on the wrapper
// so initFieldPmp() can inject icon-button controls after each ACF field.
// ---------------------------------------------------------------------------

$field_name_to_key = [];
foreach ( $raw_fields as $f ) {
	if ( ! empty( $f['name'] ) && ! empty( $f['key'] ) ) {
		$field_name_to_key[ $f['name'] ] = $f['key'];
	}
}

$field_pmp_data = [];
foreach ( $raw_fields as $f ) {
	$fkey  = $f['key']  ?? '';
	$fname = $f['name'] ?? '';
	$ftype = $f['type'] ?? '';

	// Skip non-content fields.
	if ( $ftype === 'tab' || $ftype === 'button_group' ) {
		continue;
	}
	if ( preg_match( '/_(enabled|privacy_mode)$/', $fkey ) ) {
		continue;
	}
	if ( str_contains( $fkey, '_pmp_' ) ) {
		continue;
	}

	// Derive the companion ACF field name and key.
	$suffix         = preg_replace( '/^member_directory_/', '', $fname );
	$companion_name = 'member_directory_field_pmp_' . $suffix;
	$companion_key  = $field_name_to_key[ $companion_name ] ?? '';

	// Read the field's currently stored PMP.
	$stored_pmp = (string) ( get_field( $companion_name, $post_id ) ?: 'inherit' );

	$field_pmp_data[ $fkey ] = [
		'companionKey' => $companion_key,
		'storedPmp'    => $stored_pmp,
	];
}

// ---------------------------------------------------------------------------
// Resolve section PMP for initial active-button state and eyebrow text.
//
// 4-state field: public | member | private | inherit (missing/null → inherit).
// JS takes over active state and eyebrow text when the author clicks a button.
// ---------------------------------------------------------------------------

$section_pmp = (string) ( get_field( 'member_directory_' . $section_key . '_privacy_mode', $post_id ) ?: 'inherit' );

$global_pmp  = get_field( 'member_directory_global_pmp', $post_id ) ?: 'public';
$pmp_labels  = [ 'public' => 'Public', 'member' => 'Members only', 'private' => 'Private' ];

$pmp_status_text = ( $section_pmp === 'inherit' )
	? 'Global default: ' . ( $pmp_labels[ $global_pmp ] ?? 'Public' )
	: 'Section override: ' . ( $pmp_labels[ $section_pmp ] ?? ucfirst( $section_pmp ) );

$pmp_mode_attr = ( $section_pmp === 'inherit' ) ? 'inherit' : 'override';

?>
<div class="memdir-section memdir-section--edit" data-section="<?php echo esc_attr( $section_key ); ?>" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>" data-field-pmp="<?php echo esc_attr( wp_json_encode( $field_pmp_data ) ?: '{}' ); ?>">

	<div class="memdir-section-controls">

		<p class="memdir-section-controls__title"><?php echo esc_html( $section_label ); ?></p>

		<div class="memdir-unsaved-banner" style="display:none">
			You have unsaved changes in this section.
		</div>

		<div class="memdir-section-controls__tabs">
			<?php foreach ( $field_groups as $group ) : ?>
			<button
				type="button"
				class="memdir-section-controls__tab-item"
				data-tab="<?php echo esc_attr( $group['tab'] ?? '' ); ?>"
				data-field-keys="<?php echo esc_attr( wp_json_encode( $group['field_keys'] ?? [] ) ); ?>"
			>
				<?php echo esc_html( $group['tab'] ?? '' ); ?>
			</button>
			<?php endforeach; ?>
		</div>

		<button type="button" class="memdir-section-save" data-section="<?php echo esc_attr( $section_key ); ?>">
			Save <?php echo esc_html( $section_label ); ?>
		</button>

		<p class="memdir-section-controls__pmp-heading">Section Default Visibility</p>

		<div class="memdir-section-controls__pmp">
			<button type="button" class="memdir-section-controls__pmp-btn memdir-section-controls__pmp-btn--inherit<?php echo $section_pmp === 'inherit'  ? ' is-active' : ''; ?>" data-pmp="inherit" aria-label="Inherit global setting"></button>
			<button type="button" class="memdir-section-controls__pmp-btn memdir-section-controls__pmp-btn--public<?php  echo $section_pmp === 'public'   ? ' is-active' : ''; ?>" data-pmp="public"  aria-label="Public"></button>
			<button type="button" class="memdir-section-controls__pmp-btn memdir-section-controls__pmp-btn--member<?php  echo $section_pmp === 'member'   ? ' is-active' : ''; ?>" data-pmp="member"  aria-label="Members only"></button>
			<button type="button" class="memdir-section-controls__pmp-btn memdir-section-controls__pmp-btn--private<?php echo $section_pmp === 'private'  ? ' is-active' : ''; ?>" data-pmp="private" aria-label="Private"></button>
		</div>

		<p class="memdir-section-controls__pmp-status" data-pmp-mode="<?php echo esc_attr( $pmp_mode_attr ); ?>"><?php echo esc_html( $pmp_status_text ); ?></p>

	</div>

	<div class="memdir-field-content">
		<h2 class="memdir-section-title"><?php echo esc_html( $section_label ); ?></h2>
		<p class="memdir-section-subtitle">Edit surface mirrors live layout; fields update immediately.</p>
		<?php AcfFormHelper::render_edit_form( $section, $post_id ); ?>
	</div>

</div><?php
// No closing PHP tag — intentional. Prevents accidental whitespace output.
