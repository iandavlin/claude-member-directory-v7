<?php
/**
 * Partial: Section — Edit Mode.
 *
 * Renders one section in edit mode using the two-column layout:
 *   Left  — section controls (title, PMP buttons, Override, field list)
 *   Right — section title + ACF form via AcfFormHelper::render_edit_form()
 *
 * acf_form_head() must have already been called before any HTML output
 * (handled by AcfFormHelper::maybe_render_form_head() in the single template).
 *
 * Expected variables (set by the caller before include):
 *
 *   @var array  $section  Section array from SectionRegistry. Keys used:
 *                         key, label, fields[], acf_group.
 *   @var int    $post_id  The member-directory post ID being edited.
 *   @var array  $viewer   Viewer context from PmpResolver::resolve_viewer().
 */

use MemberDirectory\AcfFormHelper;

defined( 'ABSPATH' ) || exit;

$section_key    = $section['key']    ?? '';
$section_label  = $section['label']  ?? '';
$section_fields = $section['fields'] ?? [];

// ---------------------------------------------------------------------------
// Resolve section PMP for initial active-button state.
//
// Read the same privacy_mode / privacy_level pair used by section-view.php
// so the controls reflect the correct effective level on first render.
// JS takes over active state when the author clicks a PMP button.
// ---------------------------------------------------------------------------

$section_privacy_mode  = get_field( 'member_directory_' . $section_key . '_privacy_mode',  $post_id );
$section_privacy_level = get_field( 'member_directory_' . $section_key . '_privacy_level', $post_id );
$section_pmp           = ( $section_privacy_mode === 'custom' )
	? (string) $section_privacy_level
	: 'inherit';

$global_pmp    = get_field( 'member_directory_global_pmp', $post_id ) ?: 'member';
$effective_pmp = ( $section_pmp !== 'inherit' ) ? $section_pmp : $global_pmp;

?>
<div class="memdir-section memdir-section--edit" data-section="<?php echo esc_attr( $section_key ); ?>">

	<div class="memdir-section-controls">

		<p class="memdir-section-controls__title"><?php echo esc_html( $section_label ); ?></p>

		<div class="memdir-section-controls__pmp">
			<button type="button" class="memdir-section-controls__pmp-btn memdir-section-controls__pmp-btn--public<?php echo $effective_pmp === 'public'  ? ' memdir-section-controls__pmp-btn--active' : ''; ?>" data-pmp="public">🌐</button>
			<button type="button" class="memdir-section-controls__pmp-btn memdir-section-controls__pmp-btn--member<?php echo $effective_pmp === 'member'  ? ' memdir-section-controls__pmp-btn--active' : ''; ?>" data-pmp="member">👥</button>
			<button type="button" class="memdir-section-controls__pmp-btn memdir-section-controls__pmp-btn--private<?php echo $effective_pmp === 'private' ? ' memdir-section-controls__pmp-btn--active' : ''; ?>" data-pmp="private">🔒</button>
			<button type="button" class="memdir-section-controls__override">Override</button>
		</div>

		<div class="memdir-section-controls__fields">
			<?php foreach ( $section_fields as $field ) : ?>
			<button type="button" class="memdir-section-controls__field-item" data-field="<?php echo esc_attr( $field['key'] ?? '' ); ?>">
				<?php echo esc_html( $field['label'] ?? '' ); ?>
			</button>
			<?php endforeach; ?>
		</div>

	</div>

	<div class="memdir-field-content">
		<h2 class="memdir-section-title"><?php echo esc_html( $section_label ); ?></h2>
		<?php AcfFormHelper::render_edit_form( $section, $post_id ); ?>
	</div>

</div><?php
// No closing PHP tag — intentional. Prevents accidental whitespace output.
