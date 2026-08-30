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
    $alt_file = 'class-' . str_replace('page-speed', 'pagespeed', $kebab) . '.php';
    $alt_path = WPSC_PATH . 'includes/' . $sub . $alt_file;
    if (file_exists($alt_path)) {
        require_once $alt_path;
        return;
    }
});

WP_Mock::setUsePatchwork(false);
WP_Mock::bootstrap();
