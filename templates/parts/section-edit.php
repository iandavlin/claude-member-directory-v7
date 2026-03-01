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
use MemberDirectory\SectionRegistry;

defined( 'ABSPATH' ) || exit;

$section_key    = $section['key']   ?? '';
$section_label  = $section['label'] ?? '';
$field_groups   = SectionRegistry::get_field_groups( $section );

// ---------------------------------------------------------------------------
// Resolve section PMP for initial active-button state and eyebrow text.
//
// $section_pmp is 'inherit', 'public', 'member', or 'private'.
// JS takes over active state and eyebrow text when the author clicks a button.
// ---------------------------------------------------------------------------

$section_privacy_mode  = get_field( 'member_directory_' . $section_key . '_privacy_mode',  $post_id );
$section_privacy_level = get_field( 'member_directory_' . $section_key . '_privacy_level', $post_id );
$section_pmp           = ( $section_privacy_mode === 'custom' )
	? (string) $section_privacy_level
	: 'inherit';

$global_pmp  = get_field( 'member_directory_global_pmp', $post_id ) ?: 'public';
$pmp_labels  = [ 'public' => 'Public', 'member' => 'Members only', 'private' => 'Private' ];

$pmp_status_text = ( $section_pmp === 'inherit' )
	? 'Global default: ' . ( $pmp_labels[ $global_pmp ] ?? 'Public' )
	: 'Section override: ' . ( $pmp_labels[ $section_pmp ] ?? ucfirst( $section_pmp ) );

$pmp_mode_attr = ( $section_pmp === 'inherit' ) ? 'inherit' : 'override';

?>
<div class="memdir-section memdir-section--edit" data-section="<?php echo esc_attr( $section_key ); ?>" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>">

	<div class="memdir-section-controls">

		<p class="memdir-section-controls__title"><?php echo esc_html( $section_label ); ?></p>

		<div class="memdir-section-controls__pmp">
			<button type="button" class="memdir-section-controls__pmp-btn memdir-section-controls__pmp-btn--inherit<?php echo $section_pmp === 'inherit'  ? ' is-active' : ''; ?>" data-pmp="inherit" aria-label="Inherit global setting"></button>
			<button type="button" class="memdir-section-controls__pmp-btn memdir-section-controls__pmp-btn--public<?php  echo $section_pmp === 'public'   ? ' is-active' : ''; ?>" data-pmp="public"  aria-label="Public"></button>
			<button type="button" class="memdir-section-controls__pmp-btn memdir-section-controls__pmp-btn--member<?php  echo $section_pmp === 'member'   ? ' is-active' : ''; ?>" data-pmp="member"  aria-label="Members only"></button>
			<button type="button" class="memdir-section-controls__pmp-btn memdir-section-controls__pmp-btn--private<?php echo $section_pmp === 'private'  ? ' is-active' : ''; ?>" data-pmp="private" aria-label="Private"></button>
		</div>

		<p class="memdir-section-controls__pmp-status" data-pmp-mode="<?php echo esc_attr( $pmp_mode_attr ); ?>"><?php echo esc_html( $pmp_status_text ); ?></p>

		<div class="memdir-unsaved-banner" style="display:none">
			You have unsaved changes in this section.
		</div>

		<div class="memdir-section-controls__tabs">
			<?php foreach ( $field_groups as $group ) : ?>
			<button
				type="button"
				class="memdir-section-controls__tab-item"
				data-tab="<?php echo esc_attr( $group['tab'] ?? '' ); ?>"
				data-field-keys="<?php echo esc_attr( json_encode( array_column( $group['fields'] ?? [], 'key' ) ) ); ?>"
			>
				<?php echo esc_html( $group['tab'] ?? '' ); ?>
			</button>
			<?php endforeach; ?>
		</div>

		<button type="button" class="memdir-section-save" data-section="<?php echo esc_attr( $section_key ); ?>">
			Save <?php echo esc_html( $section_label ); ?>
		</button>

	</div>

	<div class="memdir-field-content">
		<h2 class="memdir-section-title"><?php echo esc_html( $section_label ); ?></h2>
		<p class="memdir-section-subtitle">Edit surface mirrors live layout; fields update immediately.</p>
		<?php AcfFormHelper::render_edit_form( $section, $post_id ); ?>
	</div>

</div><?php
// No closing PHP tag — intentional. Prevents accidental whitespace output.
