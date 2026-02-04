<?php
/**
 * Plugin Name: Smart Auto Interlinker
 * Description: Create per-post keyword → URL mappings and automatically interlink occurrences site-wide.
 * Version: 0.1.0
 * Author: Smart Auto Interlinker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SAI_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

define( 'SAI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SAI_PLUGIN_PATH . 'admin/class-sai-settings.php';
require_once SAI_PLUGIN_PATH . 'admin/class-sai-metabox.php';

add_action( 'plugins_loaded', function () {
	if ( is_admin() ) {
		( new SAI_Settings() )->register();
		( new SAI_Metabox() )->register();
	}
} );
