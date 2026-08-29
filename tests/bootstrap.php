<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}

if (!defined('WPSC_VERSION')) {
    define('WPSC_VERSION', '1.5.2');
}
if (!defined('WPSC_FILE')) {
    define('WPSC_FILE', ABSPATH . 'wp-speed-core.php');
}
if (!defined('WPSC_PATH')) {
    define('WPSC_PATH', ABSPATH);
}
if (!defined('WPSC_URL')) {
    define('WPSC_URL', 'http://example.com/wp-content/plugins/wp-speed-core/');
}
if (!defined('WPSC_BASENAME')) {
    define('WPSC_BASENAME', 'wp-speed-core/wp-speed-core.php');
}
if (!defined('WPSC_CACHE_DIR')) {
    define('WPSC_CACHE_DIR', WP_CONTENT_DIR . '/cache/wp-speed-core/');
}

// Global options mock store
$GLOBALS['_wp_options_mock'] = [];

if (!function_exists('get_option')) {
    function get_option(string $option, $default = false) {
        return $GLOBALS['_wp_options_mock'][$option] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $option, $value, $autoload = null): bool {
        $GLOBALS['_wp_options_mock'][$option] = $value;
        return true;
    }
}

if (!function_exists('is_multisite')) {
    function is_multisite(): bool {
        return false;
    }
}

if (!function_exists('did_action')) {
    function did_action(string $hook_name): int {
        return 0;
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool {
        return false;
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path(string $file): string {
        return ABSPATH;
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url(string $file): string {
        return WPSC_URL;
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename(string $file): string {
        return WPSC_BASENAME;
    }
}

if (!function_exists('add_action')) {
    function add_action(string $hook_name, $callback, int $priority = 10, int $accepted_args = 1): bool {
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $hook_name, $callback, int $priority = 10, int $accepted_args = 1): bool {
        return true;
    }
}

if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string {
        return $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string {
        return $text;
    }
}

// Register autoloader matching main plugin file logic exactly
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

if (!class_exists('PHPUnit\Framework\TestCase')) {
    class MockTestCase {
        public function setUp(): void {}
        public function tearDown(): void {}

        public static function assertIsArray($actual, string $message = ''): void {
            if (!is_array($actual)) {
                throw new \Exception($message ?: 'Failed asserting that value is array.');
            }
        }

        public static function assertIsInt($actual, string $message = ''): void {
            if (!is_int($actual)) {
                throw new \Exception($message ?: 'Failed asserting that value is int.');
            }
        }

        public static function assertArrayHasKey($key, array $array, string $message = ''): void {
            if (!array_key_exists($key, $array)) {
                throw new \Exception($message ?: "Failed asserting that array has key '{$key}'.");
            }
        }

        public static function assertEquals($expected, $actual, string $message = ''): void {
            if ($expected !== $actual) {
                throw new \Exception($message ?: "Failed asserting that " . var_export($actual, true) . " matches expected " . var_export($expected, true) . ".");
            }
        }

        public static function assertGreaterThanOrEqual($expected, $actual, string $message = ''): void {
            if ($actual < $expected) {
                throw new \Exception($message ?: "Failed asserting that {$actual} is >= {$expected}.");
            }
        }

        public static function assertLessThanOrEqual($expected, $actual, string $message = ''): void {
            if ($actual > $expected) {
                throw new \Exception($message ?: "Failed asserting that {$actual} is <= {$expected}.");
            }
        }
    }
    class_alias('MockTestCase', 'PHPUnit\Framework\TestCase');
}
