<?php
/**
 * Plugin Name: Smart Auto Interlinker
 * Description: Automatically adds smart internal links based on configured keywords.
 * Version: 0.1.0
 * Author: Smart Auto Interlinker Team
 * License: GPL-2.0-or-later
 * Text Domain: smart-auto-interlinker
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SAI_PLUGIN_VERSION', '0.1.0' );
define( 'SAI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SAI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SAI_PLUGIN_DIR . 'includes/class-smart-auto-interlinker.php';
require_once SAI_PLUGIN_DIR . 'admin/class-smart-auto-interlinker-admin.php';

/**
 * Load plugin textdomain.
 *
 * @return void
 */
function sai_load_textdomain() {
	load_plugin_textdomain( 'smart-auto-interlinker', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'sai_load_textdomain' );

/**
 * Plugin activation callback.
 *
 * @return void
 */
function sai_activate_plugin() {
	if ( false === get_option( 'sai_keyword' ) ) {
		add_option( 'sai_keyword', '' );
	}
}

/**
 * Plugin deactivation callback.
 *
 * @return void
 */
function sai_deactivate_plugin() {
	flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'sai_activate_plugin' );
register_deactivation_hook( __FILE__, 'sai_deactivate_plugin' );

/**
 * Initialize the plugin.
 *
 * @return void
 */
function sai_init_plugin() {
	new Smart_Auto_Interlinker();
	new Smart_Auto_Interlinker_Admin();
}
add_action( 'init', 'sai_init_plugin' );
