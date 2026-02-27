<?php
/**
 * Plugin bootstrap.
 *
 * Responsibilities:
 *   - Register the member-directory CPT.
 *   - Initialise SectionRegistry (loads sections from the DB and registers
 *     ACF field groups on every page load).
 *   - Hook TemplateLoader into the single_template filter.
 *   - Enqueue front-end assets on member-directory CPT pages only.
 *
 * Files loaded here as they are created (incremental build):
 *   ✅ SectionRegistry.php  — available
 *   ✅ TemplateLoader.php   — available
 *   🔜 FieldRenderer.php    — coming next
 *   🔜 PmpResolver.php      — coming next
 *   🔜 AcfFormHelper.php    — coming next
 *   🔜 DirectoryQuery.php   — coming next
 */

namespace MemberDirectory;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/SectionRegistry.php';
require_once __DIR__ . '/TemplateLoader.php';

// Require additional classes as they are added to includes/:
// require_once __DIR__ . '/FieldRenderer.php';
// require_once __DIR__ . '/PmpResolver.php';
// require_once __DIR__ . '/AcfFormHelper.php';
// require_once __DIR__ . '/DirectoryQuery.php';

class Plugin {

	/** Absolute path to the plugin root directory (with trailing slash). */
	private string $plugin_dir;

	/** Full URL to the plugin root directory (with trailing slash). */
	private string $plugin_url;

	public function __construct( string $plugin_file ) {
		$this->plugin_dir = plugin_dir_path( $plugin_file );
		$this->plugin_url = plugin_dir_url( $plugin_file );
	}

	/**
	 * Wire up all WordPress hooks.
	 * Called once from the plugins_loaded action in member-directory.php.
	 */
	public function init(): void {
		add_action( 'init', [ $this, 'register_cpt' ] );
		add_action( 'init', [ $this, 'init_section_registry' ], 20 ); // after CPT so flush_rewrite_rules works correctly
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		TemplateLoader::init( $this->plugin_dir );
	}

	// -----------------------------------------------------------------------
	// CPT Registration
	// -----------------------------------------------------------------------

	/**
	 * Register the member-directory custom post type.
	 *
	 * Deliberately minimal labels — enough for WP admin to function.
	 * has_archive is false: the archive is handled by our own template,
	 * not a standard WP archive URL.
	 */
	public function register_cpt(): void {
		register_post_type( 'member-directory', [
			'public'       => true,
			'has_archive'  => false,
			'supports'     => [ 'title', 'custom-fields' ],
			'rewrite'      => [ 'slug' => 'member-directory' ],
			'labels'       => [
				'name'               => 'Members',
				'singular_name'      => 'Member',
				'add_new'            => 'Add New Member',
				'add_new_item'       => 'Add New Member',
				'edit_item'          => 'Edit Member',
				'new_item'           => 'New Member',
				'view_item'          => 'View Member',
				'search_items'       => 'Search Members',
				'not_found'          => 'No members found',
				'not_found_in_trash' => 'No members found in trash',
				'menu_name'          => 'Members',
			],
			'show_in_rest' => false, // No block editor; ACF form handles editing.
		] );
	}

	// -----------------------------------------------------------------------
	// SectionRegistry initialisation
	// -----------------------------------------------------------------------

	/**
	 * Tell SectionRegistry to load section data from the database and register
	 * each section's ACF field group with ACF.
	 *
	 * This runs on every page load. It reads from the member_directory_sections
	 * option — never directly from the filesystem. The filesystem is only read
	 * during an explicit admin sync (SectionRegistry::sync()).
	 */
	public function init_section_registry(): void {
		SectionRegistry::load_from_db();
	}

	// -----------------------------------------------------------------------
	// Asset enqueuing
	// -----------------------------------------------------------------------

	/**
	 * Enqueue front-end CSS and JS, but only on pages that belong to this plugin:
	 *   - Single member profile pages  (is_singular)
	 *   - The member directory archive  (is_post_type_archive)
	 *
	 * The archive check is here for future-proofing even though has_archive is
	 * currently false — the archive template may be served via a custom page
	 * with a shortcode, and this condition is harmless when false.
	 */
	public function enqueue_assets(): void {
		if ( ! is_singular( 'member-directory' ) && ! is_post_type_archive( 'member-directory' ) ) {
			return;
		}

		wp_enqueue_style(
			'member-directory',
			$this->plugin_url . 'assets/css/memdir.css',
			[],
			'0.1.0'
		);

		wp_enqueue_script(
			'member-directory',
			$this->plugin_url . 'assets/js/memdir.js',
			[],       // No jQuery dependency — vanilla JS.
			'0.1.0',
			true      // Load in footer.
		);
	}
}
