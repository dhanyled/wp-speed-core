<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

if (!defined('WPSC_PLUGIN_DIR')) {
    define('WPSC_PLUGIN_DIR', __DIR__ . '/../');
}

if (!defined('WPSC_CACHE_DIR')) {
    define('WPSC_CACHE_DIR', __DIR__ . '/../wp-content/cache/wp-speed-core/');
}

require_once __DIR__ . '/../vendor/autoload.php';

// WordPress helper stubs for isolation testing
if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null) {
        return true;
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

if (!function_exists('get_plugins')) {
    function get_plugins() {
        return [];
    }
}

if (!function_exists('is_plugin_active')) {
    function is_plugin_active($plugin) {
        return false;
    }
}

if (!function_exists('wp_get_theme')) {
    function wp_get_theme() {
        return new class {
            public function get($key) {
                return '';
            }
            public function is_block_theme() {
                return false;
            }
        };
    }
}

if (!function_exists('add_action')) {
    function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook, $args = array()) {
        return false;
    }
}

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook, $args = array(), $wp_error = false) {
        return true;
    }
}

// Recursively require plugin files to ensure all classes are loaded for tests
$includesDir = __DIR__ . '/../includes';
$directory = new RecursiveDirectoryIterator($includesDir);
$iterator = new RecursiveIteratorIterator($directory);
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        require_once $file->getPathname();
    }
}

// Mock WP_CLI class if not present
if (!class_exists('WP_CLI')) {
    class WP_CLI {
        public static array $success_logs = [];
        public static array $error_logs = [];
        public static array $line_logs = [];

        public static function success(string $message): void {
            self::$success_logs[] = $message;
        }

        public static function error(string $message): void {
            self::$error_logs[] = $message;
        }

        public static function line(string $message): void {
            self::$line_logs[] = $message;
        }

        public static function reset_logs(): void {
            self::$success_logs = [];
            self::$error_logs   = [];
            self::$line_logs    = [];
        }
    }
}

// Global actions tracker
$GLOBALS['wpsc_actions_triggered'] = [];

if (!function_exists('do_action')) {
    function do_action($tag, ...$arg): void {
        $GLOBALS['wpsc_actions_triggered'][] = [
            'tag'  => $tag,
            'args' => $arg,
        ];
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool {
        return false;
    }
}
