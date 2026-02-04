<?php
/**
 * Plugin Name: Smart Auto Interlinker
 * Description: Create per-post keyword to URL mappings and automatically interlink occurrences site-wide.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SAI_MAPPING_META_KEY', '_sai_mappings');

define('SAI_PLUGIN_DIR', __DIR__);

require_once SAI_PLUGIN_DIR . '/includes/class-sai-index.php';
require_once SAI_PLUGIN_DIR . '/includes/class-sai-content-filter.php';

add_action('plugins_loaded', static function () {
    SAI_Index::init();
    SAI_Content_Filter::init();
});
