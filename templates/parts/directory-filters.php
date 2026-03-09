<?php
/**
 * Partial: Directory Filters — sidebar layout with search, unified filter
 * stack, and per-taxonomy multi-select search fields with "Browse all".
 *
 * Visual pattern mirrors the profile edit form taxonomy search (memdir-taxo-search).
 *
 * Expected variables (set by the caller before include):
 *
 *   @var array $filter_data  Contains:
 *     - config         (array)  Directory config from Directory::get_config()
 *     - active_filters  (array)  Currently active taxonomy filters: [ 'tax_slug' => [ 'term1', ... ] ]
 *     - search          (string) Current search string
 */

defined( 'ABSPATH' ) || exit;

$config         = $filter_data['config']         ?? [];
$active_filters = $filter_data['active_filters'] ?? [];
$search         = $filter_data['search']         ?? '';
$filters        = $config['filters']             ?? [];

// Only render enabled filters.
$enabled_filters = array_filter( $filters, function( $f ) {
	return ! empty( $f['enabled'] );
} );

if ( empty( $config['search_enabled'] ) && empty( $enabled_filters ) ) {
	return;
}

// Collect all active pills across taxonomies for the unified filter stack.
$all_active_pills = [];
foreach ( $enabled_filters as $filter ) {
	$tax   = $filter['taxonomy'] ?? '';
	$label = $filter['label']    ?? $tax;
	$terms = $active_filters[ $tax ] ?? [];
	foreach ( $terms as $term_slug ) {
		$term_obj  = get_term_by( 'slug', $term_slug, $tax );
		$term_name = $term_obj ? $term_obj->name : $term_slug;
		$all_active_pills[] = [
			'taxonomy'  => $tax,
			'slug'      => $term_slug,
			'name'      => $term_name,
			'tax_label' => $label,
		];
	}
}

?>
<div class="memdir-directory__filters">

	<?php if ( ! empty( $config['search_enabled'] ) ) : ?>
	<div class="memdir-directory__search-wrap">
		<svg class="memdir-directory__search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
		<input
			type="text"
			class="memdir-directory__search"
			placeholder="<?php echo esc_attr( $config['search_placeholder'] ?? 'Search members...' ); ?>"
			value="<?php echo esc_attr( $search ); ?>"
			data-memdir-search
		>
	</div>
	<?php endif; ?>

	<?php // Unified filter stack: all active pills from every taxonomy. ?>
	<div class="memdir-directory__filter-stack" data-filter-stack>
		<?php if ( ! empty( $all_active_pills ) ) : ?>
			<?php foreach ( $all_active_pills as $pill ) : ?>
				<button class="memdir-directory__filter-pill" data-term="<?php echo esc_attr( $pill['slug'] ); ?>" data-taxonomy="<?php echo esc_attr( $pill['taxonomy'] ); ?>">
					<?php echo esc_html( $pill['name'] ); ?>
					<span class="remove">&times;</span>
				</button>
			<?php endforeach; ?>
			<button class="memdir-directory__filter-clear" data-clear-all>Clear all</button>
		<?php endif; ?>
	</div>

	<?php foreach ( $enabled_filters as $filter ) :
		$tax   = $filter['taxonomy'] ?? '';
		$label = $filter['label']    ?? $tax;

		// Load all terms for search + browse-all.
		$all_terms = get_terms( [
			'taxonomy'   => $tax,
			'hide_empty' => true,
			'number'     => 200,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );
		if ( is_wp_error( $all_terms ) ) {
			$all_terms = [];
		}

		$active_terms = $active_filters[ $tax ] ?? [];
	?>
	<div class="memdir-directory__filter-group" data-taxonomy="<?php echo esc_attr( $tax ); ?>">
		<label class="memdir-directory__filter-label"><?php echo esc_html( $label ); ?></label>

		<div class="memdir-directory__filter-search" data-filter-search>
			<input
				type="text"
				class="memdir-directory__filter-input"
				placeholder="Type to search..."
				data-filter-input
			>
			<div class="memdir-directory__filter-results" data-filter-results></div>
		</div>

		<div class="memdir-directory__filter-pills" data-filter-pills>
			<?php foreach ( $active_terms as $term_slug ) :
				$term_obj  = get_term_by( 'slug', $term_slug, $tax );
				$term_name = $term_obj ? $term_obj->name : $term_slug;
			?>
				<span class="memdir-directory__filter-badge" data-term="<?php echo esc_attr( $term_slug ); ?>">
					<?php echo esc_html( $term_name ); ?>
					<button type="button" class="memdir-directory__filter-badge-remove" data-remove-term="<?php echo esc_attr( $term_slug ); ?>">&times;</button>
				</span>
			<?php endforeach; ?>
		</div>

		<button type="button" class="memdir-directory__filter-browse" data-filter-browse>Browse all</button>

		<?php // Hidden data for JS: all terms as JSON ?>
		<script type="application/json" class="memdir-directory__terms-data">
		<?php
			$terms_data = [];
			foreach ( $all_terms as $t ) {
				$terms_data[] = [
					'slug' => $t->slug,
					'name' => $t->name,
				];
			}
			echo wp_json_encode( $terms_data );
		?>
		</script>
	</div>
	<?php endforeach; ?>

</div>
<?php
// No closing PHP tag — intentional.
