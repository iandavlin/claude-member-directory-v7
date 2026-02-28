<?php
/**
 * Partial: Right Panel.
 *
 * Renders the author/admin utility panel on a member profile page.
 * Contains the View As toggle (Edit / Member / Public) and a note
 * directing the author to the post editor for Global Visibility.
 *
 * Only included when the viewer is the genuine post author or admin —
 * the caller is responsible for that gate. Never include this for
 * spoofed viewers.
 *
 * Expected variables (set by the caller before include):
 *
 *   @var int   $post_id  The member-directory post ID.
 *   @var array $viewer   Viewer context from PmpResolver::resolve_viewer().
 *                        Not read directly here but available for future use.
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Resolve the current View As state.
//
// We read $_GET['view_as'] directly — the caller may have already spoofed
// $viewer for page rendering, but we need the raw param to highlight the
// correct button. Empty string means the author is in their own edit mode.
// ---------------------------------------------------------------------------

$base_url = get_permalink( $post_id );
$view_as  = isset( $_GET['view_as'] )
	? sanitize_text_field( wp_unslash( $_GET['view_as'] ) )
	: '';

?>
<div class="memdir-right-panel">

	<div class="memdir-panel-block">
		<h3>View As</h3>
		<div class="memdir-view-as">

			<a href="<?php echo esc_url( $base_url ); ?>"
			   class="button<?php echo $view_as === '' ? ' is-active' : ''; ?>">Edit</a>

			<a href="<?php echo esc_url( add_query_arg( 'view_as', 'member', $base_url ) ); ?>"
			   class="button<?php echo $view_as === 'member' ? ' is-active' : ''; ?>">Member</a>

			<a href="<?php echo esc_url( add_query_arg( 'view_as', 'public', $base_url ) ); ?>"
			   class="button<?php echo $view_as === 'public' ? ' is-active' : ''; ?>">Public</a>

		</div>
	</div>

	<div class="memdir-panel-block">
		<h3>Global Visibility</h3>
		<p>Change in the post editor under <strong>Member Directory &mdash; Global Controls</strong>.</p>
	</div>

</div><?php
// No closing PHP tag — intentional. Prevents accidental whitespace output.
