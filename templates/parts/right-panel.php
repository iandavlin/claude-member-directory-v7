<?php
/**
 * Partial: Right Panel.
 *
 * Renders the author/admin utility panel on a member profile page.
 * Contains the View As toggle (Edit / Member / Public), the Global
 * Default visibility selector, and the Primary Section picker.
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

use MemberDirectory\SectionRegistry;

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Resolve current state.
// ---------------------------------------------------------------------------

$base_url = get_permalink( $post_id );
$view_as  = isset( $_GET['view_as'] )
	? sanitize_text_field( wp_unslash( $_GET['view_as'] ) )
	: '';

$global_pmp      = get_field( 'member_directory_global_pmp',      $post_id ) ?: 'member';
$primary_section = get_field( 'member_directory_primary_section', $post_id ) ?: 'profile';

// Build list of primary-capable sections for the PRIMARY SECTION buttons.
$primary_capable = array_filter(
	SectionRegistry::get_sections(),
	function ( $s ) { return ! empty( $s['can_be_primary'] ); }
);

?>
<div class="memdir-right-panel">
	<div class="memdir-right-panel__card">

		<h3 class="memdir-panel__heading">Controls</h3>

		<p class="memdir-panel__label">VIEW AS</p>

		<a href="<?php echo esc_url( $base_url ); ?>"
		   class="memdir-panel__view-btn<?php echo $view_as === '' ? ' is-active' : ''; ?>">Edit</a>

		<a href="<?php echo esc_url( add_query_arg( 'view_as', 'member', $base_url ) ); ?>"
		   class="memdir-panel__view-btn<?php echo $view_as === 'member' ? ' is-active' : ''; ?>">Member</a>

		<a href="<?php echo esc_url( add_query_arg( 'view_as', 'public', $base_url ) ); ?>"
		   class="memdir-panel__view-btn<?php echo $view_as === 'public' ? ' is-active' : ''; ?>">Public</a>

		<p class="memdir-panel__label">GLOBAL DEFAULT</p>

		<button class="memdir-panel__global-btn<?php echo $global_pmp === 'public'  ? ' memdir-panel__global-btn--active' : ''; ?>" data-pmp="public">
			<span class="memdir-panel__global-icon">🌐</span> Public
		</button>
		<button class="memdir-panel__global-btn<?php echo $global_pmp === 'member'  ? ' memdir-panel__global-btn--active' : ''; ?>" data-pmp="member">
			<span class="memdir-panel__global-icon">👥</span> Members
		</button>
		<button class="memdir-panel__global-btn<?php echo $global_pmp === 'private' ? ' memdir-panel__global-btn--active' : ''; ?>" data-pmp="private">
			<span class="memdir-panel__global-icon">🔒</span> Private
		</button>

		<?php if ( $primary_capable ) : ?>
		<p class="memdir-panel__label">PRIMARY SECTION</p>

		<?php foreach ( $primary_capable as $section ) :
			$s_key   = $section['key']   ?? '';
			$s_label = $section['label'] ?? '';
		?>
		<button
			class="memdir-panel__primary-btn<?php echo $primary_section === $s_key ? ' is-active' : ''; ?>"
			data-section-key="<?php echo esc_attr( $s_key ); ?>"
			type="button"
		><?php echo esc_html( $s_label ); ?></button>
		<?php endforeach; ?>
		<?php endif; ?>

	</div>
</div><?php
// No closing PHP tag — intentional. Prevents accidental whitespace output.
