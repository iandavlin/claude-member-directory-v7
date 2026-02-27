<?php
/**
 * Plugin Name:  Member Directory
 * Description:  A section-based member profile and directory system powered by ACF Pro.
 * Version:      0.1.0
 * Requires PHP: 8.0
 * Author:       Ian Davlin
 *
 * Requires ACF Pro to be installed and active.
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// ACF Pro dependency check.
// Runs on admin_notices so the notice appears in the WordPress admin.
// The plugin returns early (never loads its own code) if ACF is absent.
// ---------------------------------------------------------------------------
add_action( 'admin_notices', function () {
	if ( ! class_exists( 'ACF' ) ) {
		echo '<div class="notice notice-error"><p>'
			. '<strong>Member Directory</strong> requires '
			. '<strong>Advanced Custom Fields Pro</strong> to be installed and active.'
			. '</p></div>';
	}
} );

if ( ! class_exists( 'ACF' ) ) {
	return;
}

// ---------------------------------------------------------------------------
// Bootstrap.
// ---------------------------------------------------------------------------
require_once plugin_dir_path( __FILE__ ) . 'includes/Plugin.php';

add_action( 'plugins_loaded', function () {
	$plugin = new MemberDirectory\Plugin( __FILE__ );
	$plugin->init();
} );
