<?php
declare(strict_types=1);

namespace WPSpeedCore\Optimization;

if (!defined('ABSPATH')) {
    exit;
}

class BloatSuppressor {
    private array $opts;

    public function __construct() {
        $s = (array) get_option('wpsc_settings', []);
        $this->opts = $s['general'] ?? [];
        $this->init();
    }

    private function init(): void {
        if (!empty($this->opts['strip_emojis'])) {
            add_action('init', [$this, 'strip_emojis']);
        }
        if (!empty($this->opts['strip_embeds'])) {
            add_action('init', [$this, 'strip_embeds'], 9999);
        }
        if (!empty($this->opts['strip_dashicons'])) {
            add_action('wp_enqueue_scripts', [$this, 'strip_dashicons'], 99);
        }
        if (!empty($this->opts['block_xmlrpc'])) {
            add_filter('xmlrpc_enabled', '__return_false');
            add_filter('wp_headers', [$this, 'strip_pingback']);
        }
        add_action('init', [$this, 'cleanup_meta']);
        if (!empty($this->opts['disable_cart_fragments_non_shop'])) {
            add_action('wp_enqueue_scripts', [$this, 'strip_cart_fragments'], 99);
        }
        $this->init_heartbeat();
        $this->init_security_hardening();
    }

    private function init_security_hardening(): void {
        add_action('init', [$this, 'block_author_enumeration']);
        add_filter('rest_endpoints', [$this, 'disable_user_rest_endpoint']);
        add_action('send_headers', [$this, 'inject_security_headers']);
    }

    public function block_author_enumeration(): void {
        if (!is_admin() && isset($_GET['author']) && is_numeric($_GET['author'])) {
            wp_die(esc_html__('Akses tidak diizinkan.', 'wp-speed-core'), 'Akses Ditolak', ['response' => 403]);
        }
    }

    public function disable_user_rest_endpoint(array $endpoints): array {
        if (!is_user_logged_in()) {
            if (isset($endpoints['/wp/v2/users'])) {
                unset($endpoints['/wp/v2/users']);
            }
            if (isset($endpoints['/wp/v2/users/(?P<id>[\d]+)'])) {
                unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);
            }
        }
        return $endpoints;
    }

    public function inject_security_headers(): void {
        if (!is_admin() && !headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
        }
    }

    private function init_heartbeat(): void {
        $mode = $this->opts['heartbeat_mode'] ?? 'throttle';
        if ($mode === 'disable') {
            add_action('init', [$this, 'disable_heartbeat'], 1);
        } elseif ($mode === 'throttle') {
            add_filter('heartbeat_settings', [$this, 'throttle_heartbeat']);
        }
    }

    public function disable_heartbeat(): void {
        wp_deregister_script('heartbeat');
    }

    public function throttle_heartbeat(array $settings): array {
        $interval = (int) ($this->opts['heartbeat_interval'] ?? 60);
        $settings['interval'] = max(15, min(120, $interval));
        return $settings;
    }

    public function strip_emojis(): void {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        add_filter('tiny_mce_plugins', static fn(array $p) => array_diff($p, ['wpemoji']));
    }

    public function strip_embeds(): void {
        remove_action('rest_api_init', 'wp_oembed_register_route');
        add_filter('embed_oembed_discover', '__return_false');
        remove_filter('oembed_dataparse', 'wp_filter_oembed_result', 10);
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
    }

    public function strip_dashicons(): void {
        if (!is_user_logged_in() && !is_admin()) {
            wp_deregister_style('dashicons');
        }
    }

    public function strip_pingback(array $headers): array {
        unset($headers['X-Pingback']);
        return $headers;
    }

    public function cleanup_meta(): void {
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'wp_shortlink_wp_head');
    }

    public function strip_cart_fragments(): void {
        if (function_exists('is_woocommerce') && !is_woocommerce() && !is_cart() && !is_checkout()) {
            wp_dequeue_script('wc-cart-fragments');
        }
    }
}
