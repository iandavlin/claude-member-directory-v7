<?php
/**
 * Partial: Pill Navigation.
 *
 * Renders the horizontal pill row below the sticky header. Provides All
 * Sections / single-section navigation. Enable/disable toggles for sections
 * live in the right panel (right-panel.php). This partial renders the correct
 * initial state on page load — disabled pills appear greyed out.
 *
 * Expected variables (set by the caller before include):
 *
 *   @var array  $sections        Section configs from SectionRegistry::get_sections().
 *   @var int    $post_id         The member-directory post ID.
 *   @var string $active_section  Currently active section key, or 'all' for
 *                                All Sections view (default on first load).
 */

use MemberDirectory\SectionRegistry;

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Pre-compute enabled state for each section.
//
// Convention: member_directory_{key}_enabled
//   false          → author explicitly disabled this section
//   null / missing → show (default for a freshly created profile)
// ---------------------------------------------------------------------------

// Primary section — resolved early so the enabled-map loop can reference it.
$primary_section = get_field( 'member_directory_primary_section', $post_id ) ?: 'profile';

$section_enabled_map = [];

foreach ( $sections as $section ) {
	$key   = $section['key'] ?? '';
	$value = get_field( 'member_directory_' . $key . '_enabled', $post_id );

	// Primary and always_on sections are always on — treat any saved
	// false/0/'0' value as disabled for the rest.
	// get_field returns: true (ACF true_false checked), false (unchecked),
	// null (no value/field), '0' (raw meta fallback), 0 (int cast).
	$is_always_on = ! empty( $section['always_on'] );
	$is_on = ( $key === $primary_section || $is_always_on )
		? true
		: ( $value !== false && $value !== 0 && $value !== '0' );

	$section_enabled_map[ $key ] = $is_on;
}

// Normalise active_section — fall back to 'all' if the caller didn't set it.
$active_section = isset( $active_section ) ? (string) $active_section : 'all';

?>
<nav class="memdir-pills"
     data-primary-section="<?php echo esc_attr( $primary_section ); ?>"
     data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"
>

	<?php
	// -----------------------------------------------------------------------
	// All Sections pill
	// -----------------------------------------------------------------------
	$all_classes = 'memdir-pill memdir-pill--all';
	if ( $active_section === 'all' ) {
		$all_classes .= ' memdir-pill--active';
	}
	?>
	<button
		class="<?php echo esc_attr( $all_classes ); ?>"
		data-section="all"
		type="button"
	>
		<span class="memdir-pill__icon" aria-hidden="true">&#9776;</span>
		<span class="memdir-pill__label">All sections</span>
	</button>

	<?php
	// -----------------------------------------------------------------------
	// Primary section pill — rendered first, no checkbox, cannot be disabled.
	// -----------------------------------------------------------------------
	foreach ( $sections as $section ) :
		if ( ( $section['key'] ?? '' ) !== $primary_section ) {
			continue;
		}
		$key   = $section['key']   ?? '';
		$label = $section['label'] ?? '';

		$pill_classes = 'memdir-pill memdir-pill--primary';
		if ( $active_section === $key ) {
			$pill_classes .= ' memdir-pill--active';
		}
	?>
	<button
		class="<?php echo esc_attr( $pill_classes ); ?>"
		data-section="<?php echo esc_attr( $key ); ?>"
		data-order="<?php echo esc_attr( (string) ( $section['order'] ?? 99 ) ); ?>"
		type="button"
	>
		<span class="memdir-pill__label"><?php echo esc_html( $label ); ?></span>
	</button>
	<?php endforeach; ?>

	<?php
	// -----------------------------------------------------------------------
	// Non-primary section pills — in SectionRegistry order, no checkboxes.
	// Enable/disable toggles live in the right panel (right-panel.php).
	// -----------------------------------------------------------------------
	foreach ( $sections as $section ) :
		$key = $section['key'] ?? '';

		// Primary is already rendered above.
		if ( $key === $primary_section ) {
			continue;
		}

		$label = $section['label'] ?? '';
		$is_on = $section_enabled_map[ $key ] ?? true;

		$pill_classes = 'memdir-pill';
		if ( $active_section === $key ) {
			$pill_classes .= ' memdir-pill--active';
		}
		if ( ! $is_on ) {
			$pill_classes .= ' memdir-pill--disabled';
		}
	?>
	<button
		class="<?php echo esc_attr( $pill_classes ); ?>"
		data-section="<?php echo esc_attr( $key ); ?>"
		data-order="<?php echo esc_attr( (string) ( $section['order'] ?? 99 ) ); ?>"
		type="button"
	>
		<span class="memdir-pill__label"><?php echo esc_html( $label ); ?></span>
	</button>

	<?php endforeach; ?>

	<?php
	// -----------------------------------------------------------------------
	// Trust pill — hard-coded outside the SectionRegistry loop.
	// This is the plugin's first non-ACF code-driven section.
	// -----------------------------------------------------------------------
	$trust_enabled = \MemberDirectory\TrustNetwork::is_trust_enabled( $post_id );
	$trust_pill_classes = 'memdir-pill';
	if ( ! $trust_enabled ) {
		$trust_pill_classes .= ' memdir-pill--disabled';
	}
	?>
	<button class="<?php echo esc_attr( $trust_pill_classes ); ?>"
	        data-section="trust" type="button">
		<span class="memdir-pill__label">Trust</span>
	</button>

	<?php
	// -----------------------------------------------------------------------
	// Message button — pushed to the far right of the pill row.
	// Edit mode: messaging settings button. View mode: send message button.
	// -----------------------------------------------------------------------
	$author_user_id   = (int) get_post_field( 'post_author', $post_id );
	$messaging_access = \MemberDirectory\Messaging::get_access( $post_id );
	$access_label     = \MemberDirectory\Messaging::get_access_label( $messaging_access );
	$is_edit_mode     = ! empty( $is_edit );

	$envelope_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>';

	if ( $is_edit_mode && \MemberDirectory\Messaging::is_available() ) :
	?>
	<button type="button"
	        class="memdir-pill memdir-pill--message memdir-pill--message-edit"
	        data-action="messaging-settings"
	        data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"
	        data-messaging-access="<?php echo esc_attr( $messaging_access ); ?>">
		<?php echo $envelope_svg; ?>
		<span class="memdir-pill__label">
			<span class="memdir-pill--message__state"><?php echo esc_html( $access_label ); ?></span>
			<span class="memdir-pill--message__sublabel">Messages</span>
		</span>
	</button>
	<?php
	elseif (
		! $is_edit_mode
		&& is_user_logged_in()
		&& get_current_user_id() !== $author_user_id
		&& \MemberDirectory\Messaging::can_message( $post_id, get_current_user_id() )
	) :
	?>
	<button type="button"
	        class="memdir-pill memdir-pill--message"
	        data-action="send-message"
	        data-recipient-id="<?php echo esc_attr( (string) $author_user_id ); ?>">
		<?php echo $envelope_svg; ?>
		<span class="memdir-pill__label">Message</span>
	</button>
	<?php endif; ?>

</nav><?php
// No closing PHP tag — intentional. Prevents accidental whitespace output.
