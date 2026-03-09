<?php
/**
 * Partial: Directory Filters — search bar + taxonomy filter controls.
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

?>
<div class="memdir-directory__filters">

	<?php if ( ! empty( $config['search_enabled'] ) ) : ?>
	<input
		type="text"
		class="memdir-directory__search"
		placeholder="<?php echo esc_attr( $config['search_placeholder'] ?? 'Search members...' ); ?>"
		value="<?php echo esc_attr( $search ); ?>"
		data-memdir-search
	>
	<?php endif; ?>

	<?php foreach ( $enabled_filters as $filter ) :
		$tax   = $filter['taxonomy'] ?? '';
		$label = $filter['label']    ?? $tax;
		$active_terms = $active_filters[ $tax ] ?? [];

		// Load all terms for the "Browse all" dialog.
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
	?>
	<div class="memdir-directory__filter-group" data-taxonomy="<?php echo esc_attr( $tax ); ?>">
		<label><?php echo esc_html( $label ); ?></label>

		<div class="memdir-directory__filter-selected">
			<?php foreach ( $active_terms as $term_slug ) :
				// Resolve term name from slug.
				$term_obj  = get_term_by( 'slug', $term_slug, $tax );
				$term_name = $term_obj ? $term_obj->name : $term_slug;
			?>
				<button class="memdir-directory__filter-pill" data-term="<?php echo esc_attr( $term_slug ); ?>">
					<?php echo esc_html( $term_name ); ?>
					<span class="remove">&times;</span>
				</button>
			<?php endforeach; ?>
		</div>

		<button class="memdir-directory__filter-browse" data-taxonomy="<?php echo esc_attr( $tax ); ?>">Browse all</button>

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
