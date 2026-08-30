<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Custom autoloader for WordPress class naming conventions (e.g. FontController -> class-font-controller.php)
spl_autoload_register(function (string $class) {
    $prefix = 'WPSpeedCore\\';
    $base_dir = __DIR__ . '/../includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $parts = explode('\\', $relative_class);

    $class_name = array_pop($parts);
    // Convert CamelCase to kebab-case (e.g. FontController -> font-controller)
    $kebab = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $class_name));
    $file_name = 'class-' . str_replace('_', '-', $kebab) . '.php';

    $sub_dir = !empty($parts) ? strtolower(implode('/', $parts)) . '/' : '';

    $file = $base_dir . $sub_dir . $file_name;

    if (file_exists($file)) {
        require_once $file;
    }
});

WP_Mock::setUsePatchwork(false);
WP_Mock::bootstrap();

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}
