<?php
/**
 * Partial: Section — Edit Mode.
 *
 * Renders one section of a member profile in edit mode using ACF's
 * acf_form(). Called from templates/single-member-directory.php when
 * AcfFormHelper::is_edit_mode() returns true.
 *
 * Expected variables (set by the caller before include):
 *
 *   @var array  $section  A section array from SectionRegistry::get_section().
 *                         Keys used: key, label, acf_group.
 *   @var int    $post_id  The member-directory post ID being edited.
 *   @var array  $viewer   Viewer context from PmpResolver::resolve_viewer().
 *                         Not used directly here but passed through for
 *                         consistency with section-view.php.
 *
 * acf_form_head() must have already been called before any HTML output
 * (handled by AcfFormHelper::maybe_render_form_head() at the top of the
 * single template). Without that prior call, acf_form() will not render
 * and form submissions will not be processed.
 */

use MemberDirectory\AcfFormHelper;

defined( 'ABSPATH' ) || exit;

$section_key   = $section['key']   ?? '';
$section_label = $section['label'] ?? '';

?>
<div class="memdir-section memdir-section--edit" data-section="<?php echo esc_attr( $section_key ); ?>">
	<h2 class="memdir-section-title"><?php echo esc_html( $section_label ); ?></h2>

	<?php AcfFormHelper::render_edit_form( $section, $post_id ); ?>

</div><?php
// No closing PHP tag — intentional. Prevents accidental whitespace output.
