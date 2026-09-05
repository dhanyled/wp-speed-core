<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}
if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}
if (!defined('WPSC_PATH')) {
    define('WPSC_PATH', ABSPATH);
}
if (!defined('WPSC_VERSION')) {
    define('WPSC_VERSION', '1.6.2');
}
if (!defined('WPSC_BASENAME')) {
    define('WPSC_BASENAME', 'wp-speed-core/wp-speed-core.php');
}
if (!defined('WPSC_CACHE_DIR')) {
    define('WPSC_CACHE_DIR', WP_CONTENT_DIR . '/cache/wp-speed-core/');
}
if (!defined('WPSC_URL')) {
    define('WPSC_URL', 'http://example.org/wp-content/plugins/wp-speed-core/');
}
if (!defined('WPSC_FILE')) {
    define('WPSC_FILE', ABSPATH . 'wp-speed-core.php');
}

if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}
if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}
if (!defined('WEEK_IN_SECONDS')) {
    define('WEEK_IN_SECONDS', 7 * 86400);
}
if (!defined('YEAR_IN_SECONDS')) {
    define('YEAR_IN_SECONDS', 365 * 86400);
}

if (!function_exists('is_admin')) {
    function is_admin(): bool {
        return false;
    }
}
if (!function_exists('is_multisite')) {
    function is_multisite(): bool {
        return false;
    }
}
if (!function_exists('did_action')) {
    function did_action($tag): int {
        return 0;
    }
}
if (!function_exists('get_site_option')) {
    function get_site_option($option, $default = false) {
        return function_exists('get_option') ? get_option($option, $default) : $default;
    }
}
if (!function_exists('update_site_option')) {
    function update_site_option($option, $value) {
        return function_exists('update_option') ? update_option($option, $value) : true;
    }
}
if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '', $filter = 'raw') {
        return '6.7';
    }
}
if (!function_exists('admin_url')) {
    function admin_url($path = '', $scheme = 'admin') {
        return 'http://example.org/wp-admin/' . ltrim($path, '/');
    }
}
if (!function_exists('self_admin_url')) {
    function self_admin_url($path = '', $scheme = 'admin') {
        return 'http://example.org/wp-admin/' . ltrim($path, '/');
    }
}
if (!function_exists('wp_nonce_url')) {
    function wp_nonce_url($actionurl, $action = -1, $name = '_wpnonce') {
        return $actionurl . '&_wpnonce=test';
    }
}

// Register WPSpeedCore custom autoloader
spl_autoload_register(static function (string $fqcn) {
    $ns = 'WPSpeedCore\\';
    if (strpos($fqcn, $ns) !== 0) {
        return;
    }
    $rel = substr($fqcn, strlen($ns));
    $parts = explode('\\', $rel);
    $class_name = array_pop($parts);
    $kebab = strtolower((string) preg_replace('/(?<=[a-z0-9])([A-Z])|(?<=[A-Z])([A-Z][a-z])/', '-$1$2', $class_name));
    $file = 'class-' . str_replace('_', '-', $kebab) . '.php';
    $sub = $parts ? strtolower(implode(DIRECTORY_SEPARATOR, $parts)) . DIRECTORY_SEPARATOR : '';
    $path = WPSC_PATH . 'includes/' . $sub . $file;
    if (file_exists($path)) {
        require_once $path;
        return;
    }
    $alt_file = 'class-' . str_replace(['page-speed', 'git-hub'], ['pagespeed', 'github'], $kebab) . '.php';
    $alt_path = WPSC_PATH . 'includes/' . $sub . $alt_file;
    if (file_exists($alt_path)) {
        require_once $alt_path;
        return;
    }
});

WP_Mock::setUsePatchwork(false);
WP_Mock::bootstrap();
