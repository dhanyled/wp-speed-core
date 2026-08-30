<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

require_once __DIR__ . '/../vendor/autoload.php';

// Custom autoloader for WordPress class naming conventions (class-*.php)
spl_autoload_register(function (string $class): void {
    $prefix = 'WPSpeedCore\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative_class = substr($class, strlen($prefix));
    $parts = explode('\\', $relative_class);
    $class_name = array_pop($parts);

    $sub_path = strtolower(implode('/', $parts));
    if ($sub_path !== '') {
        $sub_path .= '/';
    }

    // Convert PascalCase ClassName to lower-kebab-case class-name
    $kebab_class = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $class_name));
    $file_name = 'class-' . $kebab_class . '.php';

    $file = __DIR__ . '/../includes/' . $sub_path . $file_name;

    if (file_exists($file)) {
        require_once $file;
    }
});

WP_Mock::setUsePatchwork(false);
WP_Mock::bootstrap();
