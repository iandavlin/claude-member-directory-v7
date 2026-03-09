<?php
/**
 * Plugin bootstrap.
 *
 * Responsibilities:
 *   - Register the member-directory CPT.
 *   - Initialise SectionRegistry (loads section metadata from the DB).
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
 *   ✅ TrustNetwork.php     — available
 *   ✅ Onboarding.php       — available
 *   ✅ Messaging.php        — available
 *   ✅ Directory.php        — available
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
require_once __DIR__ . '/TrustNetwork.php';
require_once __DIR__ . '/Onboarding.php';
require_once __DIR__ . '/Messaging.php';
require_once __DIR__ . '/Directory.php';

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
		TrustNetwork::init();
		Onboarding::init();
		Messaging::init();
		Directory::init();
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
	 * Tell SectionRegistry to load section metadata from the database.
	 *
	 * Called either:
	 *   a) directly from Plugin::init() when did_action('acf/init') is already
	 *      true (ACF fired acf/init before our plugins_loaded callback ran), or
	 *   b) via the acf/init hook at priority 5 — before GlobalFields::register()
	 *      at priority 10 — when acf/init has not yet fired.
	 *
	 * This runs on every page load. It reads from the member_directory_sections
	 * option — never directly from the filesystem. ACF owns all field groups
	 * in its own database; the plugin never registers or overrides them.
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
		// Detect pages with the [memdir_directory] shortcode.
		global $post;
		$has_directory = is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'memdir_directory' );

		if ( ! is_singular( 'member-directory' ) && ! is_post_type_archive( 'member-directory' ) && ! $has_directory ) {
			return;
		}

		// Enqueue directory-specific assets (separate files, no CRLF issues).
		if ( $has_directory ) {
			wp_enqueue_style(
				'memdir-directory',
				$this->plugin_url . 'assets/css/memdir-directory.css',
				[],
				'0.1.0'
			);
			wp_enqueue_script(
				'memdir-directory',
				$this->plugin_url . 'assets/js/memdir-directory.js',
				[],
				'0.1.0',
				true
			);
			wp_localize_script( 'memdir-directory', 'mdDirectory', [
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'memdir_directory_nonce' ),
			] );
		}

		// Dequeue scripts with beforeunload handlers on member-directory pages.
		if ( get_post_type() === 'member-directory' ) {
			wp_dequeue_script( 'elementor-common' );
			wp_dequeue_script( 'buddyboss-theme-common-modules' );
			wp_dequeue_script( 'buddypress-activity-post-form' );
		}

		// GLightbox — lightweight image lightbox (~10KB, zero deps).
		wp_enqueue_style(
			'glightbox',
			'https://cdn.jsdelivr.net/npm/glightbox@3.3.0/dist/css/glightbox.min.css',
			[],
			'3.3.0'
		);
		wp_enqueue_script(
			'glightbox',
			'https://cdn.jsdelivr.net/npm/glightbox@3.3.0/dist/js/glightbox.min.js',
			[],
			'3.3.0',
			true
		);

		wp_enqueue_style(
			'member-directory',
			$this->plugin_url . 'assets/css/memdir.css',
			[ 'glightbox' ],
			'0.1.0'
		);

		wp_enqueue_script(
			'member-directory',
			$this->plugin_url . 'assets/js/memdir.js',
			[ 'glightbox' ],  // GLightbox must load first.
			'0.1.0',
			true              // Load in footer.
		);

		// Build social import sources: other primary-capable sections that have
		// filled-in social URL fields in their header tab.
		$social_sources = [];
		if ( is_singular( 'member-directory' ) ) {
			$md_post_id = get_queried_object_id();
			$viewer     = PmpResolver::resolve_viewer( $md_post_id );
			if ( $md_post_id && AcfFormHelper::is_edit_mode( $md_post_id, $viewer ) ) {
				foreach ( SectionRegistry::get_sections() as $sec ) {
					if ( empty( $sec['can_be_primary'] ) ) {
						continue;
					}
					if ( AcfFormHelper::section_has_social_data( $sec['acf_group_key'], $md_post_id ) ) {
						$social_sources[ $sec['key'] ] = $sec['label'] ?? ucfirst( $sec['key'] );
					}
				}
			}
		}

		// Pass AJAX URL and nonce to JS so the save handler can POST securely.
		wp_localize_script(
			'member-directory',
			'mdAjax',
			[
				'ajaxurl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'md_save_nonce' ),
				'search_nonce'     => wp_create_nonce( 'memdir_search_terms' ),
				'socialSources'    => (object) $social_sources,
				'currentUserId'    => get_current_user_id(),
				'messagingEnabled' => Messaging::is_available(),
				'messagingAccess'  => is_singular( 'member-directory' )
					? Messaging::get_access( get_queried_object_id() )
					: 'off',
				'profileAuthorId'  => is_singular( 'member-directory' )
					? (int) get_post_field( 'post_author', get_queried_object_id() )
					: 0,
			]
		);
	}
}
