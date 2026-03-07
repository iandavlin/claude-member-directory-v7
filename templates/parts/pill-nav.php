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

	// Primary section is always on — treat any saved false/0/'0' value as disabled.
	// get_field returns: true (ACF true_false checked), false (unchecked),
	// null (no value/field), '0' (raw meta fallback), 0 (int cast).
	$is_on = ( $key === $primary_section ) ? true : ( $value !== false && $value !== 0 && $value !== '0' );

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

</nav><?php
// No closing PHP tag — intentional. Prevents accidental whitespace output.
