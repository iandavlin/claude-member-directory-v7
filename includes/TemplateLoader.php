<?php
/**
 * Template Loader.
 *
 * Intercepts WordPress's template hierarchy for the member-directory CPT and
 * redirects it to the plugin's own template files.
 *
 * Two filters are hooked:
 *
 *   single_template  — fires when WP is looking for a template for a single
 *                      post. We return templates/single-member-directory.php
 *                      when the queried post is of type member-directory.
 *
 *   archive_template — fires when WP is looking for an archive template.
 *                      We return templates/archive-member-directory.php when
 *                      the current query is a member-directory post type archive.
 *
 * In both cases the plugin template is only used if the file actually exists.
 * If it does not exist yet (e.g. during incremental development), WordPress
 * falls through to its normal theme-based lookup.
 *
 * Usage (from Plugin.php):
 *   TemplateLoader::init( $plugin_dir );
 */

namespace MemberDirectory;

defined( 'ABSPATH' ) || exit;

class TemplateLoader {

	/**
	 * Absolute path to the plugin root directory, with trailing slash.
	 * Set once by init() and shared across all static filter callbacks.
	 */
	private static string $plugin_dir;

	/**
	 * Register both template filter hooks.
	 *
	 * Called once during plugins_loaded (via Plugin::init()). Safe to call
	 * early because single_template and archive_template fire much later,
	 * during template resolution.
	 *
	 * @param string $plugin_dir  Absolute path to the plugin root (trailing slash).
	 */
	public static function init( string $plugin_dir ): void {
		self::$plugin_dir = $plugin_dir;

		add_filter( 'single_template',  [ self::class, 'single' ] );
		add_filter( 'archive_template', [ self::class, 'archive' ] );
	}

	/**
	 * single_template filter callback.
	 *
	 * Returns the plugin's single profile template when the current request
	 * is for a member-directory post. Falls back to $template (WP default)
	 * if the plugin file does not exist yet.
	 *
	 * @param  string $template  The template file WordPress resolved on its own.
	 * @return string            The plugin template path, or $template as fallback.
	 */
	public static function single( string $template ): string {
		if ( ! is_singular( 'member-directory' ) ) {
			return $template;
		}

		$plugin_template = self::$plugin_dir . 'templates/single-member-directory.php';

		return file_exists( $plugin_template ) ? $plugin_template : $template;
	}

	/**
	 * archive_template filter callback.
	 *
	 * Returns the plugin's directory archive template when the current request
	 * is for the member-directory post type archive. Falls back to $template
	 * (WP default) if the plugin file does not exist yet.
	 *
	 * @param  string $template  The template file WordPress resolved on its own.
	 * @return string            The plugin template path, or $template as fallback.
	 */
	public static function archive( string $template ): string {
		if ( ! is_post_type_archive( 'member-directory' ) ) {
			return $template;
		}

		$plugin_template = self::$plugin_dir . 'templates/archive-member-directory.php';

		return file_exists( $plugin_template ) ? $plugin_template : $template;
	}
}
