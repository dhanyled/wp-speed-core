<?php
declare(strict_types=1);

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__ . '/../');
    }

    // Minimal WordPress function stubs for benchmark running outside full WP context
    if (!function_exists('get_option')) {
        function get_option($option, $default = false) {
            global $test_rules;
            return $test_rules ?? $default;
        }
    }
    if (!function_exists('is_admin')) {
        function is_admin() { return false; }
    }
    if (!function_exists('add_action')) {
        function add_action($hook, $callback, $priority = 10) {}
    }
    if (!function_exists('wp_unslash')) {
        function wp_unslash($val) { return $val; }
    }
    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field($val) { return is_string($val) ? trim($val) : ''; }
    }
    if (!function_exists('get_queried_object_id')) {
        function get_queried_object_id() { return 42; }
    }
    if (!function_exists('is_front_page')) {
        function is_front_page() { return false; }
    }
    if (!function_exists('is_home')) {
        function is_home() { return false; }
    }
    if (!function_exists('is_single')) {
        function is_single() { return false; }
    }
    if (!function_exists('is_page')) {
        function is_page() { return true; }
    }
    if (!function_exists('wp_dequeue_script')) {
        function wp_dequeue_script($handle) {}
    }
    if (!function_exists('wp_deregister_script')) {
        function wp_deregister_script($handle) {}
    }
    if (!function_exists('wp_dequeue_style')) {
        function wp_dequeue_style($handle) {}
    }
    if (!function_exists('wp_deregister_style')) {
        function wp_deregister_style($handle) {}
    }
}

namespace WPSpeedCore {
    class Kernel {
        public static function is_bypassed(): bool { return false; }
    }
}

namespace WPSpeedCore\Optimization {
    require_once __DIR__ . '/../includes/optimization/class-asset-gatekeeper.php';

    global $test_rules;
    $test_rules = [
        'contact-form-7' => [
            'handle'      => 'contact-form-7',
            'type'        => 'script',
            'exclude_url' => '/contact-us/',
            'exclude_ids' => '12, 15, 99, 102',
            'target'      => 'url_match',
            'url_match'   => '^/blog/',
        ],
        'woocommerce-cart' => [
            'handle'      => 'woocommerce-cart',
            'type'        => 'both',
            'exclude_url' => '/checkout/#special',
            'exclude_ids' => '5, 10',
            'target'      => 'shop_only',
        ],
        'heavy-slider' => [
            'handle'      => 'heavy-slider',
            'type'        => 'style',
            'exclude_url' => '',
            'exclude_ids' => '42',
            'target'      => 'url_match',
            'url_match'   => '/gallery/.*',
        ],
        'global-analytics' => [
            'handle'      => 'global-analytics',
            'type'        => 'script',
            'everywhere'  => 1,
            'target'      => 'everywhere',
        ],
        'page-styles' => [
            'handle'      => 'page-styles',
            'type'        => 'style',
            'target'      => 'pages',
        ],
    ];

    $_SERVER['REQUEST_URI'] = '/blog/2026/01/performance-tips/?ref=123#header';

    $gatekeeper = new AssetGatekeeper();

    $iterations = 100000;
    $start_time = microtime(true);
    $start_mem  = memory_get_usage();

    for ($i = 0; $i < $iterations; $i++) {
        $gatekeeper->enforce_rules();
    }

    $end_time = microtime(true);
    $end_mem  = memory_get_usage();

    $elapsed_ms = ($end_time - $start_time) * 1000;
    $ops_per_sec = $iterations / ($end_time - $start_time);
    $mem_used = $end_mem - $start_mem;

    echo sprintf("Iterations: %d\n", $iterations);
    echo sprintf("Execution Time: %.2f ms\n", $elapsed_ms);
    echo sprintf("Operations/sec: %.2f ops/sec\n", $ops_per_sec);
    echo sprintf("Memory Delta: %d bytes\n", $mem_used);
}
