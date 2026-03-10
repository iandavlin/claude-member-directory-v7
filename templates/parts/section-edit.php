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
 *
 * Optional (perf: set by caller to avoid redundant DB reads):
 *
 *   @var array  $all_post_meta    Pre-fetched get_post_meta($post_id) array.
 *                                 Used for batch field-PMP lookups.
 *   @var array  $cached_acf_fields  Map of acf_group_key => acf_get_fields().
 */

use MemberDirectory\AcfFormHelper;

defined( 'ABSPATH' ) || exit;

$section_key    = $section['key']   ?? '';
$section_label  = $section['label'] ?? '';

// ---------------------------------------------------------------------------
// PERF: Use cached ACF fields if the parent template pre-fetched them.
// Avoids a duplicate acf_get_fields() call when the same group was already
// loaded for header-tab scanning in single-member-directory.php.
// Revert: remove $cached_acf_fields references; restore direct acf_get_fields().
// ---------------------------------------------------------------------------
$group_key     = $section['acf_group_key'] ?? '';
$raw_fields    = [];
if ( $group_key ) {
	$raw_fields = ( isset( $cached_acf_fields[ $group_key ] ) )
		? $cached_acf_fields[ $group_key ]
		: ( acf_get_fields( $group_key ) ?: [] );
}
$field_groups  = [];
$current_tab   = 'General';
$current_keys  = [];

// ---------------------------------------------------------------------------
// Conditional tabs: [if:section_key] in a tab label.
//
// When a tab label contains [if:business] (for example), that tab and all
// its fields are only shown when the Business section is enabled for this
// post. If the referenced section is disabled, the tab button is omitted
// and its field keys are collected in $conditional_excluded_keys so they
// can be excluded from PMP data and from acf_form().
//
// Convention: the marker is stripped from the displayed tab name.
// ---------------------------------------------------------------------------
$conditional_excluded_keys = [];
$_cond_tab_active = true;

foreach ( $raw_fields as $f ) {
	$fkey  = $f['key']  ?? '';
	$ftype = $f['type'] ?? '';
	if ( preg_match( '/_(enabled|privacy_mode)$/', $fkey ) ) {
		continue; // Section-level system fields — managed by sidebar controls.
	}
	if ( $ftype === 'tab' ) {
		// Flush the previous tab group (only if it was active).
		if ( $_cond_tab_active && ! empty( $current_keys ) ) {
			$field_groups[] = [ 'tab' => $current_tab, 'field_keys' => $current_keys ];
		}

		$tab_label        = $f['label'] ?? 'Tab';
		$_cond_tab_active = true;

		// Check for [if:section_key] conditional marker.
		if ( preg_match( '/\[if:([a-z0-9_-]+)\]/i', $tab_label, $_cm ) ) {
			$ref_enabled      = get_field( 'member_directory_' . $_cm[1] . '_enabled', $post_id );
			$_cond_tab_active = ! empty( $ref_enabled );
			$tab_label        = trim( preg_replace( '/\s*\[if:[a-z0-9_-]+\]/i', '', $tab_label ) );
		}

		$current_tab  = $tab_label;
		$current_keys = [];
	} else {
		if ( $_cond_tab_active ) {
			$current_keys[] = $fkey;
		} else {
			$conditional_excluded_keys[] = $fkey;
		}
	}
}
if ( $_cond_tab_active && ! empty( $current_keys ) ) {
	$field_groups[] = [ 'tab' => $current_tab, 'field_keys' => $current_keys ];
}
unset( $_cond_tab_active, $_cm );

// ---------------------------------------------------------------------------
// Build per-field PMP data for JS injection of custom PMP controls.
//
// For each content field, derive the companion ACF field key and read the
// currently stored PMP value. Passed to JS via data-field-pmp on the wrapper
// so initFieldPmp() can inject icon-button controls after each ACF field.
//
// PERF: PMP values are read from a batch post-meta map instead of calling
// get_field() once per content field. This eliminates N individual DB queries
// (one per field) and replaces them with a single get_post_meta() call.
// WordPress caches get_post_meta() internally, so even repeated calls to this
// partial across sections share the same cached result.
//
// Revert: replace $pmp_meta_map lookups with get_field( $companion_name, $post_id ).
// ---------------------------------------------------------------------------

if ( ! isset( $all_post_meta ) ) {
	$all_post_meta = get_post_meta( $post_id );
}
$pmp_meta_map = [];
foreach ( $all_post_meta as $meta_key => $meta_values ) {
	if ( str_starts_with( $meta_key, 'member_directory_field_pmp_' ) ) {
		$pmp_meta_map[ $meta_key ] = $meta_values[0] ?? 'inherit';
	}
}

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
	// Skip fields under disabled conditional tabs.
	if ( in_array( $fkey, $conditional_excluded_keys, true ) ) {
		continue;
	}

	// Derive the companion ACF field name and key.
	$suffix         = preg_replace( '/^member_directory_/', '', $fname );
	$companion_name = 'member_directory_field_pmp_' . $suffix;
	$companion_key  = $field_name_to_key[ $companion_name ] ?? '';

	// PERF: Read the stored PMP from the batch map (no per-field DB query).
	$stored_pmp = (string) ( $pmp_meta_map[ $companion_name ] ?? 'inherit' );

	$field_pmp_data[ $fkey ] = [
		'companionKey'  => $companion_key,
		'companionName' => $companion_name,
		'storedPmp'     => $stored_pmp,
	];
}

// ---------------------------------------------------------------------------
// Resolve section PMP for initial active-button state and eyebrow text.
//
// 4-state field: public | member | private | inherit (missing/null → inherit).
// JS takes over active state and eyebrow text when the author clicks a button.
// ---------------------------------------------------------------------------

$section_pmp = (string) ( get_field( 'member_directory_' . $section_key . '_privacy_mode', $post_id ) ?: 'inherit' );

// PERF: $global_pmp may already be set by the parent template.
// Revert: remove the isset() guard; always call get_field() directly.
if ( ! isset( $global_pmp ) ) {
	$global_pmp = get_field( 'member_directory_global_pmp', $post_id ) ?: 'public';
}
$pmp_labels  = [ 'public' => 'Public', 'member' => 'Members only', 'private' => 'Private' ];

$pmp_status_text = ( $section_pmp === 'inherit' )
	? 'Global default: ' . ( $pmp_labels[ $global_pmp ] ?? 'Public' )
	: 'Section override: ' . ( $pmp_labels[ $section_pmp ] ?? ucfirst( $section_pmp ) );

$pmp_mode_attr = ( $section_pmp === 'inherit' ) ? 'inherit' : 'override';

?>
<div class="memdir-section memdir-section--edit" data-section="<?php echo esc_attr( $section_key ); ?>" data-color="<?php echo esc_attr( (string) ( $section_color ?? 0 ) ); ?>" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>" data-field-pmp="<?php echo esc_attr( wp_json_encode( $field_pmp_data ) ?: '{}' ); ?>">

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

		<?php
		$pmp_options = [
			'inherit' => 'Inherit',
			'public'  => 'Public',
			'member'  => 'Members',
			'private' => 'Private',
		];
		$current_pmp_label = $pmp_options[ $section_pmp ] ?? 'Inherit';
		?>
		<div class="memdir-pmp-dropdown" data-pmp="<?php echo esc_attr( $section_pmp ); ?>">
			<button type="button"
				class="memdir-pmp-dropdown__trigger memdir-pmp-dropdown__trigger--<?php echo esc_attr( $section_pmp ); ?>"
				aria-haspopup="listbox" aria-expanded="false">
				<span class="memdir-pmp-dropdown__icon" aria-hidden="true"></span>
				<span class="memdir-pmp-dropdown__label"><?php echo esc_html( $current_pmp_label ); ?></span>
				<span class="memdir-pmp-dropdown__caret" aria-hidden="true"></span>
			</button>
			<ul class="memdir-pmp-dropdown__menu" role="listbox" tabindex="-1">
				<?php foreach ( $pmp_options as $val => $label ) : ?>
				<li class="memdir-pmp-dropdown__option memdir-pmp-dropdown__option--<?php echo esc_attr( $val ); ?>"
					role="option" data-pmp="<?php echo esc_attr( $val ); ?>"
					aria-selected="<?php echo $val === $section_pmp ? 'true' : 'false'; ?>"
					tabindex="-1">
					<span class="memdir-pmp-dropdown__option-icon" aria-hidden="true"></span>
					<?php echo esc_html( $label ); ?>
				</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<p class="memdir-section-controls__pmp-status" data-pmp-mode="<?php echo esc_attr( $pmp_mode_attr ); ?>"><?php echo esc_html( $pmp_status_text ); ?></p>

	</div>

	<div class="memdir-field-content">
		<h2 class="memdir-section-title"><?php echo esc_html( $section_label ); ?></h2>
		<p class="memdir-section-subtitle">Edit surface mirrors live layout; fields update immediately.</p>
		<?php AcfFormHelper::render_edit_form( $section, $post_id, $conditional_excluded_keys ); ?>
	</div>

</div><?php
// No closing PHP tag — intentional. Prevents accidental whitespace output.
