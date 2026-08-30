<?php
declare(strict_types=1);

namespace WPSpeedCore\Optimization;

use WPSpeedCore\Kernel;

if (!defined('ABSPATH')) {
    exit;
}

class AssetGatekeeper {
    private array $rules;
    private array $prepared_rules = [];

    public function __construct() {
        $this->rules = (array) get_option('wpsc_disabled_assets', []);
        $this->prepare_rules();
        if (!is_admin()) {
            add_action('wp_enqueue_scripts', [$this, 'enforce_rules'], 9999);
            add_action('wp_print_styles', [$this, 'enforce_rules'], 9999);
            add_action('wp_print_scripts', [$this, 'enforce_rules'], 9999);
        }
    }

    private function prepare_rules(): void {
        $this->prepared_rules = [];

        foreach ($this->rules as $key => $config) {
            if (!is_array($config)) {
                continue;
            }

            $handle = $config['handle'] ?? (is_string($key) ? $key : '');
            if (empty($handle)) {
                continue;
            }

            $exclude_pattern = null;
            $exclude_url     = trim((string) ($config['exclude_url'] ?? ''));
            if ($exclude_url !== '') {
                $exclude_pattern = '#' . str_replace('#', '\#', $exclude_url) . '#i';
            }

            $exclude_ids     = [];
            $raw_exclude_ids = (string) ($config['exclude_ids'] ?? '');
            if ($raw_exclude_ids !== '') {
                $parsed_ids = array_filter(array_map('intval', explode(',', $raw_exclude_ids)));
                if (!empty($parsed_ids)) {
                    $exclude_ids = array_flip($parsed_ids);
                }
            }

            $target    = $config['target'] ?? (!empty($config['everywhere']) ? 'everywhere' : 'url_match');
            $url_regex = null;
            if ($target === 'url_match') {
                $pattern = trim((string) ($config['url_match'] ?? ''));
                if ($pattern !== '') {
                    $url_regex = '#' . str_replace('#', '\#', $pattern) . '#i';
                }
            }

            $type = $config['type'] ?? 'script';

            $this->prepared_rules[] = [
                'handle'          => $handle,
                'exclude_pattern' => $exclude_pattern,
                'exclude_ids'     => $exclude_ids,
                'target'          => $target,
                'url_regex'       => $url_regex,
                'type'            => $type,
            ];
        }
    }

    public function enforce_rules(): void {
        if (Kernel::is_bypassed()) {
            return;
        }

        if (empty($this->prepared_rules)) {
            return;
        }

        $current_id   = (int) get_queried_object_id();
        $uri          = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? '/'));
        $is_front     = is_front_page() || is_home();
        $is_single    = is_single();
        $is_page      = is_page();
        $is_shop_page = function_exists('is_woocommerce') && (is_woocommerce() || is_cart() || is_checkout() || is_account_page());

        foreach ($this->prepared_rules as $rule) {
            // Check Exceptions (Exclude URLs / Exclude Post IDs)
            if ($rule['exclude_pattern'] !== null && @preg_match($rule['exclude_pattern'], $uri)) {
                continue; // Skip unloading, keep asset loaded
            }

            if (!empty($rule['exclude_ids']) && isset($rule['exclude_ids'][$current_id])) {
                continue; // Skip unloading for this specific post/page ID
            }

            $disabled = false;

            switch ($rule['target']) {
                case 'everywhere':
                    $disabled = true;
                    break;
                case 'frontpage':
                    $disabled = $is_front;
                    break;
                case 'posts':
                    $disabled = $is_single;
                    break;
                case 'pages':
                    $disabled = $is_page && !$is_front;
                    break;
                case 'shop_only':
                    $disabled = $is_shop_page;
                    break;
                case 'non_shop':
                    $disabled = !$is_shop_page;
                    break;
                case 'url_match':
                    if ($rule['url_regex'] !== null && @preg_match($rule['url_regex'], $uri)) {
                        $disabled = true;
                    }
                    break;
            }

            if ($disabled) {
                $type   = $rule['type'];
                $handle = $rule['handle'];
                if ($type === 'script' || $type === 'both') {
                    wp_dequeue_script($handle);
                    wp_deregister_script($handle);
                }
                if ($type === 'style' || $type === 'both') {
                    wp_dequeue_style($handle);
                    wp_deregister_style($handle);
                }
            }
        }
    }
}