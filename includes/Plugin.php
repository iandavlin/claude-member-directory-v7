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
 *   ✅ AdminSync.php        — available
 *   ✅ FieldRenderer.php    — available
 *   ✅ PmpResolver.php      — available
 *   ✅ GlobalFields.php     — available
 *   ✅ AcfFormHelper.php    — available
 *   🔜 DirectoryQuery.php   — coming next
 */

namespace MemberDirectory;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/SectionRegistry.php';
require_once __DIR__ . '/TemplateLoader.php';
require_once __DIR__ . '/AdminSync.php';

// Require additional classes as they are added to includes/:
require_once __DIR__ . '/FieldRenderer.php';
require_once __DIR__ . '/PmpResolver.php';
require_once __DIR__ . '/GlobalFields.php';
require_once __DIR__ . '/AcfFormHelper.php';
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

		// Register the plugin's acf-json/ folder as the ACF JSON save and load
		// path. ACF will auto-save field group JSON here when groups are edited in
		// the admin, and auto-load those files on every page load — so field changes
		// are live immediately without any plugin sync step.
		add_filter( 'acf/settings/save_json', [ $this, 'acf_json_save_path' ] );
		add_filter( 'acf/settings/load_json', [ $this, 'acf_json_load_path' ] );

		// ACF fires acf/init inside its own plugins_loaded callback. Because ACF
		// is loaded alphabetically before this plugin, its plugins_loaded callback
		// is registered (and fires) before ours — both at default priority 10.
		// That means acf/init may have already fired by the time we reach this
		// line. did_action() detects that case and falls back to a direct call so
		// SectionRegistry is never silently skipped.
		//
		// SectionRegistry must initialise before GlobalFields (which reads section
		// data to build the Primary Section choices), so this block runs before
		// GlobalFields::init() below.
		if ( did_action( 'acf/init' ) ) {
			$this->init_section_registry();
		} else {
			add_action( 'acf/init', [ $this, 'init_section_registry' ], 5 ); // priority 5: before GlobalFields::register at default 10
		}

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		TemplateLoader::init( $this->plugin_dir );
		AdminSync::init();
		GlobalFields::init();
		AcfFormHelper::init();
	}

	// -----------------------------------------------------------------------
	// ACF JSON path callbacks
	// -----------------------------------------------------------------------

	/**
	 * Tell ACF to save field group JSON files to the plugin's acf-json/ folder.
	 * Fires on acf/settings/save_json.
	 */
	public function acf_json_save_path(): string {
		return $this->plugin_dir . 'acf-json';
	}

	/**
	 * Tell ACF to also load field group JSON files from the plugin's acf-json/ folder.
	 * Fires on acf/settings/load_json.
	 *
	 * @param string[] $paths  Existing load paths registered by ACF or other plugins.
	 * @return string[]
	 */
	public function acf_json_load_path( array $paths ): array {
		$paths[] = $this->plugin_dir . 'acf-json';
		return $paths;
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
	 * Called either:
	 *   a) directly from Plugin::init() when did_action('acf/init') is already
	 *      true (ACF fired acf/init before our plugins_loaded callback ran), or
	 *   b) via the acf/init hook at priority 5 — before GlobalFields::register()
	 *      at priority 10 — when acf/init has not yet fired.
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

		// Dequeue scripts with beforeunload handlers on member-directory pages.
		if ( get_post_type() === 'member-directory' ) {
			wp_dequeue_script( 'elementor-common' );
			wp_dequeue_script( 'buddyboss-theme-common-modules' );
			wp_dequeue_script( 'buddypress-activity-post-form' );
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

		// Pass AJAX URL and nonce to JS so the save handler can POST securely.
		wp_localize_script(
			'member-directory',
			'mdAjax',
			[
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'md_save_nonce' ),
			]
		);
	}
}
