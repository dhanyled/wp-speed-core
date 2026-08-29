<?php
declare(strict_types=1);

namespace {
    if (!defined("ABSPATH")) {
        define("ABSPATH", __DIR__ . "/../");
    }

    if (!function_exists("get_option")) {
        function get_option($option, $default = false) {
            global $test_rules;
            return $test_rules ?? $default;
        }
    }
    if (!function_exists("is_admin")) {
        function is_admin() { return false; }
    }
    if (!function_exists("add_action")) {
        function add_action($hook, $callback, $priority = 10) {}
    }
    if (!function_exists("wp_unslash")) {
        function wp_unslash($val) { return $val; }
    }
    if (!function_exists("sanitize_text_field")) {
        function sanitize_text_field($val) { return is_string($val) ? trim($val) : ""; }
    }

    global $queried_id, $is_front, $is_single, $is_page, $is_shop, $dequeued_scripts, $dequeued_styles;
    $queried_id = 0;
    $is_front = false;
    $is_single = false;
    $is_page = false;
    $is_shop = false;
    $dequeued_scripts = [];
    $dequeued_styles = [];

    if (!function_exists("get_queried_object_id")) {
        function get_queried_object_id() { global $queried_id; return $queried_id; }
    }
    if (!function_exists("is_front_page")) {
        function is_front_page() { global $is_front; return $is_front; }
    }
    if (!function_exists("is_home")) {
        function is_home() { return false; }
    }
    if (!function_exists("is_single")) {
        function is_single() { global $is_single; return $is_single; }
    }
    if (!function_exists("is_page")) {
        function is_page() { global $is_page; return $is_page; }
    }
    if (!function_exists("is_woocommerce")) {
        function is_woocommerce() { global $is_shop; return $is_shop; }
    }
    if (!function_exists("is_cart")) {
        function is_cart() { return false; }
    }
    if (!function_exists("is_checkout")) {
        function is_checkout() { return false; }
    }
    if (!function_exists("is_account_page")) {
        function is_account_page() { return false; }
    }
    if (!function_exists("wp_dequeue_script")) {
        function wp_dequeue_script($handle) { global $dequeued_scripts; $dequeued_scripts[] = $handle; }
    }
    if (!function_exists("wp_deregister_script")) {
        function wp_deregister_script($handle) {}
    }
    if (!function_exists("wp_dequeue_style")) {
        function wp_dequeue_style($handle) { global $dequeued_styles; $dequeued_styles[] = $handle; }
    }
    if (!function_exists("wp_deregister_style")) {
        function wp_deregister_style($handle) {}
    }
}

namespace WPSpeedCore {
    class Kernel {
        public static function is_bypassed(): bool { return false; }
    }
}

namespace WPSpeedCore\Optimization {
    require_once __DIR__ . "/../includes/optimization/class-asset-gatekeeper.php";

    global $test_rules, $queried_id, $is_front, $is_single, $is_page, $is_shop, $dequeued_scripts, $dequeued_styles;

    $test_rules = [
        "rule1" => [
            "handle" => "script-everywhere",
            "type" => "script",
            "target" => "everywhere",
        ],
        "rule2" => [
            "handle" => "style-front",
            "type" => "style",
            "target" => "frontpage",
        ],
        "rule3" => [
            "handle" => "both-posts",
            "type" => "both",
            "target" => "posts",
        ],
        "rule4" => [
            "handle" => "script-url-match",
            "type" => "script",
            "target" => "url_match",
            "url_match" => "^/products/",
        ],
        "rule5" => [
            "handle" => "script-excluded-url",
            "type" => "script",
            "target" => "everywhere",
            "exclude_url" => "/checkout/",
        ],
        "rule6" => [
            "handle" => "script-excluded-id",
            "type" => "script",
            "target" => "everywhere",
            "exclude_ids" => "10, 20, 30",
        ],
    ];

    $gk = new AssetGatekeeper();

    // Test Case 1: Standard URL matching /products/
    $_SERVER["REQUEST_URI"] = "/products/shoes";
    $queried_id = 5;
    $is_front = false;
    $is_single = false;
    $dequeued_scripts = [];
    $dequeued_styles = [];

    $gk->enforce_rules();

    assert(in_array("script-everywhere", $dequeued_scripts), "script-everywhere should be dequeued");
    assert(in_array("script-url-match", $dequeued_scripts), "script-url-match should be dequeued on /products/");
    assert(in_array("script-excluded-url", $dequeued_scripts), "script-excluded-url should be dequeued when URL is not /checkout/");
    assert(in_array("script-excluded-id", $dequeued_scripts), "script-excluded-id should be dequeued when ID 5 is not excluded");
    assert(!in_array("style-front", $dequeued_styles), "style-front should NOT be dequeued when not frontpage");

    // Test Case 2: Checkout URL (exclude_url triggers)
    $_SERVER["REQUEST_URI"] = "/checkout/";
    $dequeued_scripts = [];
    $dequeued_styles = [];
    $gk->enforce_rules();
    assert(!in_array("script-excluded-url", $dequeued_scripts), "script-excluded-url should NOT be dequeued on /checkout/");

    // Test Case 3: Excluded ID 20
    $_SERVER["REQUEST_URI"] = "/some-page";
    $queried_id = 20;
    $dequeued_scripts = [];
    $dequeued_styles = [];
    $gk->enforce_rules();
    assert(!in_array("script-excluded-id", $dequeued_scripts), "script-excluded-id should NOT be dequeued for ID 20");

    // Test Case 4: Single post
    $is_single = true;
    $dequeued_scripts = [];
    $dequeued_styles = [];
    $gk->enforce_rules();
    assert(in_array("both-posts", $dequeued_scripts), "both-posts script should be dequeued on single post");
    assert(in_array("both-posts", $dequeued_styles), "both-posts style should be dequeued on single post");

    echo "All functional assertion tests passed successfully!
";
}
