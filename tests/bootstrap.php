<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

// Global state tracking for mocked WordPress functions
$GLOBALS['_wp_die_calls'] = [];
$GLOBALS['_is_admin'] = false;
$GLOBALS['_options'] = [];

if (!function_exists('is_admin')) {
    function is_admin(): bool {
        return $GLOBALS['_is_admin'] ?? false;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string {
        return $text;
    }
}

if (!function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = []): void {
        $GLOBALS['_wp_die_calls'][] = [
            'message' => $message,
            'title' => $title,
            'args' => $args,
        ];
        throw new \RuntimeException("wp_die called: " . (is_string($message) ? $message : ''));
    }
}

if (!function_exists('get_option')) {
    function get_option(string $option, $default = false) {
        return $GLOBALS['_options'][$option] ?? $default;
    }
}

if (!function_exists('add_action')) {
    function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1): bool {
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1): bool {
        return true;
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool {
        return false;
    }
}

require_once ABSPATH . 'includes/optimization/class-bloat-suppressor.php';
