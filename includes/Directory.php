<?php
/**
 * Directory — Public Member Listing.
 *
 * Provides the [memdir_directory] shortcode that renders a searchable,
 * filterable member card grid. Taxonomy filters are admin-configurable
 * through the AdminSync dashboard.
 *
 * Shortcode approach chosen over CPT archive template for BuddyBoss
 * compatibility (BB aggressively overrides archive templates).
 *
 * Public API (all static):
 *   Directory::init()              — registers shortcode + AJAX hooks
 *   Directory::get_config()        — reads DB option (cached)
 *   Directory::update_config()     — saves config
 *   Directory::detect_taxonomies() — scans ACF field groups for taxonomy fields
 *   Directory::render_shortcode()  — builds filters + card grid + pagination
 *   Directory::handle_filter()     — AJAX handler for live filtering
 *   Directory::build_query()       — constructs WP_Query from filter params
 *   Directory::extract_card_data() — returns card display data or null (PMP ghost)
 */

namespace MemberDirectory;

defined( 'ABSPATH' ) || exit;

class Directory {

	/** WordPress option key for directory configuration. */
	const CONFIG_OPTION = 'member_directory_directory_config';

	/** In-memory config cache. */
	private static ?array $config_cache = null;

	/** Suffix lists for image field slot detection (mirrors header-section.php). */
	const AVATAR_SUFFIXES = [ '_photo', '_avatar', '_headshot', '_portrait' ];
	const BANNER_SUFFIXES = [ '_banner', '_cover', '_header_image' ];

	/** Social platform suffixes (mirrors header-section.php). */
	const SOCIAL_PLATFORMS = [
		'website', 'linkedin', 'instagram', 'twitter', 'facebook',
		'youtube', 'tiktok', 'vimeo', 'linktree',
	];

	/** Social SVG icons keyed by platform suffix. */
	const SOCIAL_SVGS = [
		'website'   => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>',
		'linkedin'  => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.064 2.064 0 1 1 0-4.128 2.064 2.064 0 0 1 0 4.128zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0z"/></svg>',
		'instagram' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/></svg>',
		'twitter'   => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
		'facebook'  => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
		'youtube'   => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>',
		'tiktok'    => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>',
		'vimeo'     => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M23.977 6.416c-.105 2.338-1.739 5.543-4.894 9.609-3.268 4.247-6.026 6.37-8.29 6.37-1.409 0-2.578-1.294-3.553-3.881L5.322 11.4C4.603 8.816 3.834 7.522 3.01 7.522c-.179 0-.806.378-1.881 1.132L0 7.197c1.185-1.044 2.351-2.084 3.501-3.128C5.08 2.701 6.266 1.984 7.055 1.91c1.867-.18 3.016 1.1 3.447 3.838.465 2.953.789 4.789.971 5.507.539 2.45 1.131 3.674 1.776 3.674.502 0 1.256-.796 2.265-2.385 1.004-1.589 1.54-2.797 1.612-3.628.144-1.371-.395-2.061-1.614-2.061-.574 0-1.167.121-1.777.391 1.186-3.868 3.434-5.757 6.762-5.637 2.473.06 3.628 1.664 3.493 4.797z"/></svg>',
		'linktree'  => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M7.953 15.066l-.038.002-3.293-3.307L.915 15.46l3.721 3.72 3.33-3.293-.013-.822zm8.132 0l-.012.822 3.33 3.293 3.72-3.72-3.706-3.699-3.294 3.307-.038-.002zM11.993 0L7.88 4.112l4.113 4.113 4.112-4.112L11.993 0zm0 11.888L7.88 16l4.113 4.112L16.105 16l-4.112-4.112zm0 7.739l-1.387 1.386L11.993 22.4l1.387-1.387-1.387-1.386z"/></svg>',
	];

	// -----------------------------------------------------------------------
	// Initialization
	// -----------------------------------------------------------------------

	/**
	 * Register the shortcode and AJAX hooks.
	 */
	public static function init(): void {
		add_shortcode( 'memdir_directory', [ self::class, 'render_shortcode' ] );
		add_action( 'wp_ajax_memdir_directory_filter', [ self::class, 'handle_filter' ] );
		add_action( 'wp_ajax_nopriv_memdir_directory_filter', [ self::class, 'handle_filter' ] );
	}

	// -----------------------------------------------------------------------
	// Config
	// -----------------------------------------------------------------------

	/**
	 * Get the directory configuration, with sensible defaults.
	 *
	 * @return array
	 */
	public static function get_config(): array {
		if ( self::$config_cache !== null ) {
			return self::$config_cache;
		}

		$stored = get_option( self::CONFIG_OPTION, [] );

		$defaults = [
			'directory_page_id'  => 0,
			'per_page'           => 12,
			'default_sort'       => 'title',
			'sort_order'         => 'ASC',
			'search_enabled'     => true,
			'search_placeholder' => 'Search members...',
			'map_pin_style'      => 'circle',  // circle | pin | avatar
			'filters'            => [],
			'card'               => [
				'show_avatar'   => true,
				'show_banner'   => true,
				'show_badges'   => true,
				'show_location' => true,
				'show_social'   => false,
			],
		];

		$config = wp_parse_args( $stored, $defaults );

		// Ensure card sub-array has all keys.
		$config['card'] = wp_parse_args( $config['card'] ?? [], $defaults['card'] );

		self::$config_cache = $config;

		return $config;
	}

	/**
	 * Save the directory configuration.
	 *
	 * @param array $config
	 */
	public static function update_config( array $config ): void {
		update_option( self::CONFIG_OPTION, $config, false );
		self::$config_cache = $config;
	}

	// -----------------------------------------------------------------------
	// Taxonomy auto-detection
	// -----------------------------------------------------------------------

	/**
	 * Scan all sections' ACF field groups for taxonomy fields.
	 *
	 * @return array[] Each entry: [ 'taxonomy', 'label', 'section_key', 'field_key' ]
	 */
	public static function detect_taxonomies(): array {
		$sections = SectionRegistry::get_sections();
		$found    = [];
		$seen     = []; // Prevent duplicate taxonomy slugs.

		foreach ( $sections as $section ) {
			$group_key = $section['acf_group_key'] ?? '';
			if ( empty( $group_key ) ) {
				continue;
			}

			if ( ! function_exists( 'acf_get_fields' ) ) {
				continue;
			}

			$fields = acf_get_fields( $group_key );
			if ( empty( $fields ) || ! is_array( $fields ) ) {
				continue;
			}

			foreach ( $fields as $f ) {
				if ( ( $f['type'] ?? '' ) !== 'taxonomy' ) {
					continue;
				}
				if ( SectionRegistry::is_system_field( $f ) ) {
					continue;
				}

				$tax_slug = $f['taxonomy'] ?? '';
				if ( empty( $tax_slug ) || isset( $seen[ $tax_slug ] ) ) {
					continue;
				}

				$seen[ $tax_slug ] = true;

				// Get taxonomy label.
				$tax_obj = get_taxonomy( $tax_slug );
				$label   = $tax_obj ? $tax_obj->labels->name : ucfirst( str_replace( '_', ' ', $tax_slug ) );

				$found[] = [
					'taxonomy'    => $tax_slug,
					'label'       => $label,
					'section_key' => $section['key'] ?? '',
					'field_key'   => $f['key'] ?? '',
					'enabled'     => true,
				];
			}
		}

		return $found;
	}

	// -----------------------------------------------------------------------
	// Shortcode
	// -----------------------------------------------------------------------

	/**
	 * Render the [memdir_directory] shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_shortcode( $atts ): string {
		// Enqueue assets from inside the shortcode — page builders (Elementor)
		// store content in meta rather than post_content, so has_shortcode()
		// detection in enqueue_assets() may miss it.
		self::enqueue_assets();

		$atts = shortcode_atts( [
			'per_page' => 0,
			'sort'     => '',
			'section'  => '',
		], $atts, 'memdir_directory' );

		$config = self::get_config();

		// Shortcode overrides.
		$per_page = (int) $atts['per_page'] ?: (int) $config['per_page'];
		$sort     = $atts['sort'] ?: $config['default_sort'];

		// Parse filters from GET params.
		$active_filters = [];
		foreach ( $config['filters'] as $filter ) {
			if ( empty( $filter['enabled'] ) ) {
				continue;
			}
			$tax = $filter['taxonomy'] ?? '';
			if ( ! empty( $tax ) && ! empty( $_GET[ $tax ] ) ) {
				$active_filters[ $tax ] = array_map(
					'sanitize_text_field',
					explode( ',', sanitize_text_field( wp_unslash( $_GET[ $tax ] ) ) )
				);
			}
		}

		$search = '';
		if ( ! empty( $config['search_enabled'] ) && ! empty( $_GET['memdir_search'] ) ) {
			$search = sanitize_text_field( wp_unslash( $_GET['memdir_search'] ) );
		}

		$page = max( 1, (int) ( $_GET['memdir_page'] ?? 1 ) );

		// Build viewer context for PMP.
		$viewer     = PmpResolver::spoof_viewer( is_user_logged_in() ? 'member' : 'public' );
		$global_pmp = 'public'; // Will be overridden per-post.

		// Build the query.
		$query = self::build_query( $active_filters, $search, $page, $per_page, $sort, $config, $atts['section'] );

		ob_start();

		echo '<div class="memdir-directory" data-config=\'' . esc_attr( wp_json_encode( [
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'memdir_directory_nonce' ),
		] ) ) . '\'>';

		// ── Two-column layout: main (map + cards) | sidebar (filters) ──

		echo '<div class="memdir-directory__layout">';

		// Main column: map + filter stack + card grid.
		echo '<div class="memdir-directory__main">';

		// Map container (Leaflet will mount here).
		echo '<div id="memdir-directory__map" class="memdir-directory__map"></div>';

		// Unified filter stack: active pills from all taxonomies (rendered in main area).
		$all_active_pills = [];
		foreach ( $config['filters'] as $filter ) {
			if ( empty( $filter['enabled'] ) ) {
				continue;
			}
			$tax   = $filter['taxonomy'] ?? '';
			$terms = $active_filters[ $tax ] ?? [];
			foreach ( $terms as $term_slug ) {
				$term_obj  = get_term_by( 'slug', $term_slug, $tax );
				$term_name = $term_obj ? $term_obj->name : $term_slug;
				$all_active_pills[] = [
					'taxonomy' => $tax,
					'slug'     => $term_slug,
					'name'     => $term_name,
				];
			}
		}

		echo '<div class="memdir-directory__filter-stack" data-filter-stack>';
		if ( ! empty( $all_active_pills ) ) {
			foreach ( $all_active_pills as $pill ) {
				echo '<button class="memdir-directory__filter-pill" data-term="' . esc_attr( $pill['slug'] ) . '" data-taxonomy="' . esc_attr( $pill['taxonomy'] ) . '">';
				echo esc_html( $pill['name'] );
				echo ' <span class="remove">&times;</span>';
				echo '</button>';
			}
			echo '<button class="memdir-directory__filter-clear" data-clear-all>Clear all</button>';
		}
		echo '</div>';

		// Grid.
		echo '<div class="memdir-directory__grid">';
		$markers = self::render_cards( $query, $viewer, $config );
		echo '</div>';

		// Pagination.
		if ( $query->max_num_pages > 1 ) {
			self::render_pagination( $page, $query->max_num_pages );
		}

		// No results.
		if ( ! $query->have_posts() ) {
			echo '<p class="memdir-directory__empty">No members found.</p>';
		}

		echo '</div>'; // .memdir-directory__main

		// Sidebar: filters.
		if ( ! empty( $config['search_enabled'] ) || ! empty( $config['filters'] ) ) {
			$filter_data = [
				'config'         => $config,
				'active_filters' => $active_filters,
				'search'         => $search,
			];
			echo '<aside class="memdir-directory__sidebar">';
			include dirname( __DIR__ ) . '/templates/parts/directory-filters.php';
			echo '</aside>';
		}

		echo '</div>'; // .memdir-directory__layout

		// Initial marker data for JS (embedded as JSON).
		if ( ! empty( $markers ) ) {
			echo '<script type="application/json" id="memdir-directory__markers">';
			echo wp_json_encode( $markers );
			echo '</script>';
		}

		echo '</div>'; // .memdir-directory

		wp_reset_postdata();

		return ob_get_clean();
	}

	// -----------------------------------------------------------------------
	// AJAX handler
	// -----------------------------------------------------------------------

	/**
	 * Handle AJAX filter requests. Returns JSON with HTML + pagination data.
	 */
	public static function handle_filter(): void {
		check_ajax_referer( 'memdir_directory_nonce', 'nonce' );

		$config = self::get_config();

		$search  = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );
		$page    = max( 1, (int) ( $_POST['page'] ?? 1 ) );
		$section = sanitize_text_field( wp_unslash( $_POST['section'] ?? '' ) );

		// Parse taxonomy filters from POST.
		$active_filters = [];
		$filters_raw    = $_POST['filters'] ?? [];
		if ( is_array( $filters_raw ) ) {
			foreach ( $filters_raw as $tax => $terms ) {
				$tax = sanitize_text_field( $tax );
				if ( is_array( $terms ) ) {
					$active_filters[ $tax ] = array_map( 'sanitize_text_field', $terms );
				}
			}
		}

		$per_page = (int) $config['per_page'];
		$sort     = $config['default_sort'];

		$viewer = PmpResolver::spoof_viewer( is_user_logged_in() ? 'member' : 'public' );

		$query = self::build_query( $active_filters, $search, $page, $per_page, $sort, $config, $section );

		ob_start();
		$markers = self::render_cards( $query, $viewer, $config );
		$html = ob_get_clean();

		ob_start();
		if ( $query->max_num_pages > 1 ) {
			self::render_pagination( $page, $query->max_num_pages );
		}
		$pagination_html = ob_get_clean();

		wp_reset_postdata();

		wp_send_json_success( [
			'html'        => $html,
			'pagination'  => $pagination_html,
			'found_posts' => (int) $query->found_posts,
			'max_pages'   => (int) $query->max_num_pages,
			'page'        => $page,
			'markers'     => $markers,
		] );
	}

	// -----------------------------------------------------------------------
	// Query building
	// -----------------------------------------------------------------------

	/**
	 * Build a WP_Query for the member directory.
	 *
	 * @param array  $filters   Taxonomy filters: [ 'tax_slug' => [ 'term1', 'term2' ] ]
	 * @param string $search    Search string.
	 * @param int    $page      Current page.
	 * @param int    $per_page  Posts per page.
	 * @param string $sort      Sort field (title|date|modified|rand).
	 * @param array  $config    Directory config.
	 * @param string $section   Optional primary section filter.
	 * @return \WP_Query
	 */
	public static function build_query(
		array $filters,
		string $search,
		int $page,
		int $per_page,
		string $sort,
		array $config,
		string $section = ''
	): \WP_Query {
		$args = [
			'post_type'      => 'member-directory',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
		];

		// Sort.
		$sort_order = $config['sort_order'] ?? 'ASC';
		switch ( $sort ) {
			case 'date':
				$args['orderby'] = 'date';
				$args['order']   = $sort_order;
				break;
			case 'modified':
				$args['orderby'] = 'modified';
				$args['order']   = $sort_order;
				break;
			case 'rand':
				$args['orderby'] = 'rand';
				break;
			case 'title':
			default:
				$args['orderby'] = 'title';
				$args['order']   = $sort_order;
				break;
		}

		// Search.
		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		// Taxonomy filters.
		$tax_query = [];
		foreach ( $filters as $tax => $terms ) {
			if ( empty( $terms ) ) {
				continue;
			}
			$tax_query[] = [
				'taxonomy' => $tax,
				'field'    => 'slug',
				'terms'    => $terms,
			];
		}
		if ( ! empty( $tax_query ) ) {
			$tax_query['relation'] = 'AND';
			$args['tax_query']     = $tax_query;
		}

		// Primary section filter.
		if ( ! empty( $section ) ) {
			$args['meta_query'] = [
				[
					'key'   => 'member_directory_primary_section',
					'value' => sanitize_key( $section ),
				],
			];
		}

		return new \WP_Query( $args );
	}

	// -----------------------------------------------------------------------
	// Card data extraction
	// -----------------------------------------------------------------------

	/**
	 * Extract card display data from a member post.
	 *
	 * Mirrors header-section.php: scans the primary section's header tab,
	 * maps fields by type + suffix. Returns null if entire profile is PMP-hidden.
	 *
	 * @param int   $post_id    Member directory post ID.
	 * @param array $viewer     Viewer context from PmpResolver.
	 * @param array $config     Directory config.
	 * @return array|null       Card data or null if ghosted.
	 */
	public static function extract_card_data( int $post_id, array $viewer, array $config ): ?array {
		// Get global PMP for this post.
		$global_pmp = get_field( 'member_directory_global_pmp', $post_id ) ?: 'public';

		// Check global-level visibility first.
		$global_visible = PmpResolver::can_view( [
			'field_pmp'   => 'inherit',
			'section_pmp' => 'inherit',
			'global_pmp'  => $global_pmp,
		], $viewer );

		if ( ! $global_visible ) {
			return null;
		}

		// Determine primary section.
		$primary_key = get_field( 'member_directory_primary_section', $post_id ) ?: '';
		$section     = $primary_key ? SectionRegistry::get_section( $primary_key ) : null;

		// Fall back to the first section if no primary is set.
		if ( ! $section ) {
			$all_sections = SectionRegistry::get_sections();
			$section      = $all_sections[0] ?? null;
		}

		if ( ! $section ) {
			return null;
		}

		$acf_group_key = $section['acf_group_key'] ?? '';
		if ( empty( $acf_group_key ) || ! function_exists( 'acf_get_fields' ) ) {
			return null;
		}

		$fields = acf_get_fields( $acf_group_key );
		if ( empty( $fields ) || ! is_array( $fields ) ) {
			return null;
		}

		// Find header tab fields.
		$in_header_tab = false;
		$header_fields = [];

		foreach ( $fields as $f ) {
			if ( ( $f['type'] ?? '' ) === 'tab' ) {
				$in_header_tab = ( stripos( $f['label'] ?? '', 'header' ) !== false );
				continue;
			}
			if ( $in_header_tab ) {
				$header_fields[] = $f;
			}
		}

		// Extract card slots.
		$card = [
			'post_id'   => $post_id,
			'permalink' => get_permalink( $post_id ),
			'title'     => get_the_title( $post_id ),
			'avatar'    => '',
			'banner'    => '',
			'badges'    => [],
			'location'  => '',
			'social'    => [],
		];

		$section_pmp = get_field( 'member_directory_' . $section['key'] . '_privacy_mode', $post_id ) ?: 'inherit';

		$found_title  = false;
		$found_avatar = false;
		$found_banner = false;
		$card_display = $config['card'] ?? [];

		foreach ( $header_fields as $f ) {
			$ftype = $f['type'] ?? '';
			$fname = $f['name'] ?? '';
			$fkey  = $f['key']  ?? '';

			if ( SectionRegistry::is_system_field( $f ) || str_contains( $fkey, '_pmp_' ) ) {
				continue;
			}

			// Per-field PMP check.
			$field_name_suffix = preg_replace( '/^member_directory_/', '', $fname );
			$field_pmp         = get_field( 'member_directory_field_pmp_' . $field_name_suffix, $post_id ) ?: 'inherit';

			$visible = PmpResolver::can_view( [
				'field_pmp'   => $field_pmp,
				'section_pmp' => $section_pmp,
				'global_pmp'  => $global_pmp,
			], $viewer );

			if ( ! $visible ) {
				continue;
			}

			$value = get_field( $fname, $post_id );

			switch ( $ftype ) {
				case 'text':
					if ( ! $found_title && ! empty( $value ) ) {
						$card['title'] = (string) $value;
						$found_title   = true;
					}
					break;

				case 'image':
					if ( ! empty( $value ) ) {
						$matched = false;

						if ( ! $found_banner && ! empty( $card_display['show_banner'] ) ) {
							foreach ( self::BANNER_SUFFIXES as $sfx ) {
								if ( str_ends_with( $fname, $sfx ) ) {
									$card['banner'] = self::resolve_image_url( $value, 'medium' );
									$found_banner   = true;
									$matched        = true;
									break;
								}
							}
						}

						if ( ! $matched && ! $found_avatar && ! empty( $card_display['show_avatar'] ) ) {
							foreach ( self::AVATAR_SUFFIXES as $sfx ) {
								if ( str_ends_with( $fname, $sfx ) ) {
									$card['avatar'] = self::resolve_image_url( $value, 'thumbnail' );
									$found_avatar   = true;
									$matched        = true;
									break;
								}
							}
						}

						// Fallback: first unmatched image → avatar.
						if ( ! $matched && ! $found_avatar && ! empty( $card_display['show_avatar'] ) ) {
							$card['avatar'] = self::resolve_image_url( $value, 'thumbnail' );
							$found_avatar   = true;
						}
					}
					break;

				case 'taxonomy':
					if ( ! empty( $card_display['show_badges'] ) && ! empty( $value ) ) {
						$terms = is_array( $value ) ? $value : [ $value ];
						foreach ( $terms as $term ) {
							if ( $term instanceof \WP_Term ) {
								$card['badges'][] = $term->name;
							} elseif ( is_array( $term ) && isset( $term['name'] ) ) {
								$card['badges'][] = $term['name'];
							} elseif ( is_int( $term ) ) {
								$t = get_term( $term );
								if ( $t instanceof \WP_Term ) {
									$card['badges'][] = $t->name;
								}
							} elseif ( is_string( $term ) && ! empty( $term ) ) {
								$card['badges'][] = $term;
							}
						}
					}
					break;

				case 'url':
					if ( ! empty( $card_display['show_social'] ) && ! empty( $value ) ) {
						foreach ( self::SOCIAL_PLATFORMS as $platform ) {
							if ( str_ends_with( $fname, '_' . $platform ) ) {
								$card['social'][] = [
									'url'      => $value,
									'platform' => $platform,
									'svg'      => self::SOCIAL_SVGS[ $platform ] ?? '',
								];
								break;
							}
						}
					}
					break;
			}
		}

		// Fallback avatar from section default.
		if ( ! $found_avatar && ! empty( $card_display['show_avatar'] ) && ! empty( $section['default_avatar'] ) ) {
			$card['avatar'] = (string) wp_get_attachment_image_url( (int) $section['default_avatar'], 'thumbnail' );
		}

		// Fallback banner from section default.
		if ( ! $found_banner && ! empty( $card_display['show_banner'] ) && ! empty( $section['default_banner'] ) ) {
			$card['banner'] = (string) wp_get_attachment_image_url( (int) $section['default_banner'], 'medium' );
		}

		// Location (from location section, if show_location enabled).
		if ( ! empty( $card_display['show_location'] ) ) {
			$loc_value     = get_field( 'member_directory_location_location', $post_id );
			$loc_precision = get_field( 'member_directory_location_display_precision', $post_id ) ?: 'city';
			if ( is_array( $loc_value ) && ! empty( $loc_value['address'] ) ) {
				$card['location'] = FieldRenderer::format_location( $loc_value, $loc_precision );
				// Preserve raw coordinates for map markers.
				if ( ! empty( $loc_value['lat'] ) && ! empty( $loc_value['lng'] ) ) {
					$card['lat'] = (float) $loc_value['lat'];
					$card['lng'] = (float) $loc_value['lng'];
				}
			}
		}

		return $card;
	}

	// -----------------------------------------------------------------------
	// Render helpers
	// -----------------------------------------------------------------------

	/**
	 * Render all cards from a WP_Query and collect marker data for the map.
	 *
	 * @return array[] Marker data: [ [ lat, lng, title, permalink, avatar ], ... ]
	 */
	private static function render_cards( \WP_Query $query, array $viewer, array $config ): array {
		$markers = [];

		if ( ! $query->have_posts() ) {
			return $markers;
		}

		while ( $query->have_posts() ) {
			$query->the_post();
			$card = self::extract_card_data( get_the_ID(), $viewer, $config );

			if ( $card === null ) {
				continue; // PMP ghost.
			}

			include dirname( __DIR__ ) . '/templates/parts/directory-card.php';

			// Collect marker for map.
			if ( ! empty( $card['lat'] ) && ! empty( $card['lng'] ) ) {
				$markers[] = [
					'lat'       => $card['lat'],
					'lng'       => $card['lng'],
					'title'     => $card['title'],
					'permalink' => $card['permalink'],
					'avatar'    => $card['avatar'],
					'location'  => $card['location'] ?? '',
				];
			}
		}

		return $markers;
	}

	/**
	 * Render pagination controls.
	 */
	private static function render_pagination( int $current, int $max_pages ): void {
		echo '<div class="memdir-directory__pagination">';

		if ( $current > 1 ) {
			echo '<button class="memdir-directory__page-btn" data-page="' . ( $current - 1 ) . '">&laquo; Prev</button>';
		}

		// Show up to 5 page numbers centered on current.
		$start = max( 1, $current - 2 );
		$end   = min( $max_pages, $current + 2 );

		if ( $start > 1 ) {
			echo '<button class="memdir-directory__page-btn" data-page="1">1</button>';
			if ( $start > 2 ) {
				echo '<span class="memdir-directory__page-dots">&hellip;</span>';
			}
		}

		for ( $i = $start; $i <= $end; $i++ ) {
			$active_class = ( $i === $current ) ? ' memdir-directory__page-btn--active' : '';
			echo '<button class="memdir-directory__page-btn' . $active_class . '" data-page="' . $i . '">' . $i . '</button>';
		}

		if ( $end < $max_pages ) {
			if ( $end < $max_pages - 1 ) {
				echo '<span class="memdir-directory__page-dots">&hellip;</span>';
			}
			echo '<button class="memdir-directory__page-btn" data-page="' . $max_pages . '">' . $max_pages . '</button>';
		}

		if ( $current < $max_pages ) {
			echo '<button class="memdir-directory__page-btn" data-page="' . ( $current + 1 ) . '">Next &raquo;</button>';
		}

		echo '</div>';
	}

	/**
	 * Enqueue directory CSS and JS. Safe to call multiple times — WordPress
	 * deduplicates by handle. Called from render_shortcode() to guarantee
	 * loading even when page builders bypass has_shortcode() detection.
	 */
	public static function enqueue_assets(): void {
		$plugin_url = plugin_dir_url( dirname( __FILE__ ) );
		$config     = self::get_config();
		$pin_style  = $config['map_pin_style'] ?? 'circle';

		// Leaflet CSS + JS from CDN.
		wp_enqueue_style(
			'leaflet',
			'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
			[],
			'1.9.4'
		);
		wp_enqueue_script(
			'leaflet',
			'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
			[],
			'1.9.4',
			true
		);

		$js_deps = [ 'leaflet' ];

		// MarkerCluster for avatar pin mode.
		if ( $pin_style === 'avatar' ) {
			wp_enqueue_style(
				'leaflet-markercluster',
				'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css',
				[ 'leaflet' ],
				'1.5.3'
			);
			wp_enqueue_style(
				'leaflet-markercluster-default',
				'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css',
				[ 'leaflet-markercluster' ],
				'1.5.3'
			);
			wp_enqueue_script(
				'leaflet-markercluster',
				'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js',
				[ 'leaflet' ],
				'1.5.3',
				true
			);
			$js_deps[] = 'leaflet-markercluster';
		}

		wp_enqueue_style(
			'memdir-directory',
			$plugin_url . 'assets/css/memdir-directory.css',
			[ 'leaflet' ],
			'0.3.0'
		);
		wp_enqueue_script(
			'memdir-directory',
			$plugin_url . 'assets/js/memdir-directory.js',
			$js_deps,
			'0.3.0',
			true
		);
		wp_localize_script( 'memdir-directory', 'mdDirectory', [
			'ajaxurl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'memdir_directory_nonce' ),
			'pinStyle' => $pin_style,
		] );
	}

	// -----------------------------------------------------------------------
	// Taxonomy link helpers
	// -----------------------------------------------------------------------

	/**
	 * Get the directory page permalink, or empty string if not configured.
	 */
	public static function get_directory_url(): string {
		$config  = self::get_config();
		$page_id = (int) ( $config['directory_page_id'] ?? 0 );
		if ( $page_id < 1 ) {
			return '';
		}
		$url = get_permalink( $page_id );
		return $url ? (string) $url : '';
	}

	/**
	 * Build a URL to the directory page with a single taxonomy filter active.
	 *
	 * @param string $taxonomy   Taxonomy slug (e.g. 'mp2t_instruments').
	 * @param string $term_slug  Term slug (e.g. 'guitar').
	 * @return string Full URL or empty string if directory page not set.
	 */
	public static function get_term_filter_url( string $taxonomy, string $term_slug ): string {
		$base = self::get_directory_url();
		if ( empty( $base ) || empty( $taxonomy ) || empty( $term_slug ) ) {
			return '';
		}
		return add_query_arg( $taxonomy, $term_slug, $base );
	}

	// -----------------------------------------------------------------------
	// Private utilities
	// -----------------------------------------------------------------------

	/**
	 * Resolve an ACF image value (array or attachment ID) to a URL.
	 */
	private static function resolve_image_url( mixed $value, string $size = 'thumbnail' ): string {
		if ( is_array( $value ) ) {
			return $value['sizes'][ $size ] ?? $value['url'] ?? '';
		}
		return (string) wp_get_attachment_image_url( (int) $value, $size );
	}
}
