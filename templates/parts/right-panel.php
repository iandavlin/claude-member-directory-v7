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
$all_sections = SectionRegistry::get_sections();
$primary_capable = array_filter(
	$all_sections,
	function ( $s ) { return ! empty( $s['can_be_primary'] ); }
);

// Build section-enabled map for the SECTIONS toggles (edit mode only).
$is_edit = ( $view_as === '' );
$section_enabled_map = [];
if ( $is_edit ) {
	foreach ( $all_sections as $s ) {
		$s_key = $s['key'] ?? '';
		if ( $s_key === $primary_section || ! empty( $s['always_on'] ) ) {
			$section_enabled_map[ $s_key ] = true; // Primary / always_on — always on.
			continue;
		}
		$val = get_field( 'member_directory_' . $s_key . '_enabled', $post_id );
		$section_enabled_map[ $s_key ] = ( $val !== false && $val !== 0 && $val !== '0' );
	}
}

?>
<div class="memdir-right-panel">
	<div class="memdir-right-panel__card">

		<h3 class="memdir-panel__heading">Controls</h3>

		<p class="memdir-panel__label">VIEW AS</p>

		<div class="memdir-panel__view-group">
			<a href="<?php echo esc_url( $base_url ); ?>"
			   class="memdir-panel__view-btn<?php echo $view_as === '' ? ' is-active' : ''; ?>">Edit</a>

			<a href="<?php echo esc_url( add_query_arg( 'view_as', 'member', $base_url ) ); ?>"
			   class="memdir-panel__view-btn<?php echo $view_as === 'member' ? ' is-active' : ''; ?>">Member</a>

			<a href="<?php echo esc_url( add_query_arg( 'view_as', 'public', $base_url ) ); ?>"
			   class="memdir-panel__view-btn<?php echo $view_as === 'public' ? ' is-active' : ''; ?>">Public</a>
		</div>

		<p class="memdir-panel__label">GLOBAL DEFAULT VISIBILITY</p>

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

		<?php if ( $is_edit ) : ?>
		<p class="memdir-panel__label">SECTIONS</p>
		<div class="memdir-panel__sections">
			<?php
			// Primary first, then remaining in registry order.
			$ordered = [];
			foreach ( $all_sections as $s ) {
				if ( ( $s['key'] ?? '' ) === $primary_section ) {
					array_unshift( $ordered, $s );
				} else {
					$ordered[] = $s;
				}
			}
			foreach ( $ordered as $s ) :
				$s_key        = $s['key']   ?? '';
				$s_label      = $s['label'] ?? '';
				$is_primary   = ( $s_key === $primary_section );
				$is_always_on = ! empty( $s['always_on'] );
				$is_on        = $section_enabled_map[ $s_key ] ?? true;
			?>
			<div class="memdir-panel__section-row">
				<span class="memdir-panel__section-name"><?php echo esc_html( $s_label ); ?></span>
				<?php if ( $is_primary ) : ?>
					<span class="memdir-panel__section-badge">Primary</span>
				<?php elseif ( $is_always_on ) : ?>
					<span class="memdir-panel__section-badge">Always on</span>
				<?php else : ?>
					<label class="memdir-panel__toggle">
						<input type="checkbox" data-section-key="<?php echo esc_attr( $s_key ); ?>" <?php checked( $is_on ); ?>>
						<span class="memdir-panel__toggle-slider"></span>
					</label>
				<?php endif; ?>
			</div>
			<?php endforeach; ?>
		<?php
		// Trust toggle — hard-coded, non-ACF section.
		$trust_is_on = \MemberDirectory\TrustNetwork::is_trust_enabled( $post_id );
		?>
		<div class="memdir-panel__section-row">
			<span class="memdir-panel__section-name">Trust</span>
			<label class="memdir-panel__toggle">
				<input type="checkbox" data-section-key="trust"
				       data-trust-toggle="1" <?php checked( $trust_is_on ); ?>>
				<span class="memdir-panel__toggle-slider"></span>
			</label>
		</div>
		</div>
		<?php endif; ?>

		<div class="memdir-panel__notes">
			<p class="memdir-panel__notes-text">Notes appear here.</p>
		</div>

	</div>
</div><?php
// No closing PHP tag — intentional. Prevents accidental whitespace output.
