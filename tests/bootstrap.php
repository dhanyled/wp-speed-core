<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

// Global stubs for WordPress environment testing
$GLOBALS['wp_stubs'] = [
    'is_user_logged_in' => false,
    'is_admin' => false,
    'options' => [],
    'actions' => [],
    'filters' => [],
];

if (!function_exists('get_option')) {
    function get_option(string $option, $default = false) {
        return $GLOBALS['wp_stubs']['options'][$option] ?? $default;
    }
}

if (!function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void {
        $GLOBALS['wp_stubs']['actions'][] = compact('hook', 'callback', 'priority', 'accepted_args');
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void {
        $GLOBALS['wp_stubs']['filters'][] = compact('hook', 'callback', 'priority', 'accepted_args');
    }
}

if (!function_exists('remove_action')) {
    function remove_action(string $hook, callable $callback, int $priority = 10): void {
    }
}

if (!function_exists('remove_filter')) {
    function remove_filter(string $hook, callable $callback, int $priority = 10): void {
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool {
        return (bool) ($GLOBALS['wp_stubs']['is_user_logged_in'] ?? false);
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool {
        return (bool) ($GLOBALS['wp_stubs']['is_admin'] ?? false);
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string {
        return $text;
    }
}

if (!function_exists('wp_die')) {
    function wp_die(string $message = '', string $title = '', array $args = []): void {
        throw new \RuntimeException("wp_die: $title - $message");
    }
}

if (!function_exists('wp_deregister_script')) {
    function wp_deregister_script(string $handle): void {}
}

if (!function_exists('wp_deregister_style')) {
    function wp_deregister_style(string $handle): void {}
}

if (!function_exists('wp_dequeue_script')) {
    function wp_dequeue_script(string $handle): void {}
}

require_once __DIR__ . '/../includes/optimization/class-bloat-suppressor.php';
