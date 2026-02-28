<?php
/**
 * Partial: Pill Navigation.
 *
 * Renders the horizontal pill row below the sticky header. Provides All
 * Sections / single-section switching and per-section enable/disable checkboxes.
 * JS (memdir.js) handles AJAX saves and live DOM updates; this partial renders
 * the correct initial state on page load.
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
//
// We loop once up front so we can count enabled sections for the All Sections
// badge before rendering any pills.
// ---------------------------------------------------------------------------

$section_enabled_map = [];
$enabled_count       = 0;

foreach ( $sections as $section ) {
	$key   = $section['key'] ?? '';
	$value = get_field( 'member_directory_' . $key . '_enabled', $post_id );
	$is_on = ( $value !== false );

	$section_enabled_map[ $key ] = $is_on;

	if ( $is_on ) {
		$enabled_count++;
	}
}

// Normalise active_section — fall back to 'all' if the caller didn't set it.
$active_section = isset( $active_section ) ? (string) $active_section : 'all';

?>
<nav class="memdir-pills">

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
		<span class="memdir-pill__count"><?php echo esc_html( (string) $enabled_count ); ?></span>
	</button>

	<?php
	// -----------------------------------------------------------------------
	// One pill per registered section (in SectionRegistry order).
	// -----------------------------------------------------------------------
	foreach ( $sections as $section ) :
		$key   = $section['key']   ?? '';
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
		type="button"
	>
		<input
			type="checkbox"
			class="memdir-pill__checkbox"
			data-section="<?php echo esc_attr( $key ); ?>"
			<?php checked( $is_on ); ?>
		>
		<span class="memdir-pill__label"><?php echo esc_html( $label ); ?></span>
	</button>

	<?php endforeach; ?>

</nav><?php
// No closing PHP tag — intentional. Prevents accidental whitespace output.
