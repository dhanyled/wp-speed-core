<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

WP_Mock::setUsePatchwork(true);
WP_Mock::bootstrap();

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'http://example.org/wp-content/plugins/' . basename(dirname($file)) . '/';
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename($file) {
        return basename($file);
    }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $function) {}
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $function) {}
}

if (!function_exists('add_action')) {
    function add_action($hook, $function, $priority = 10, $accepted_args = 1) {}
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}

require_once __DIR__ . '/../wp-speed-core.php';
