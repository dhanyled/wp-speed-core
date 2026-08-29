<?php
declare(strict_types=1);

namespace WPSpeedCore\Optimization;

use WPSpeedCore\Kernel;

if (!defined('ABSPATH')) {
    exit;
}

class AssetGatekeeper {
    private array $rules;

    public function __construct() {
        $this->rules = (array) get_option('wpsc_disabled_assets', []);
        if (!is_admin()) {
            add_action('wp_enqueue_scripts', [$this, 'enforce_rules'], 9999);
            add_action('wp_print_styles', [$this, 'enforce_rules'], 9999);
            add_action('wp_print_scripts', [$this, 'enforce_rules'], 9999);
        }
    }

    public function enforce_rules(): void {
        if (Kernel::is_bypassed()) {
            return;
        }

        if (empty($this->rules)) {
            return;
        }

        $current_id   = (int) get_queried_object_id();
        $uri          = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? '/'));
        $is_front     = is_front_page() || is_home();
        $is_single    = is_single();
        $is_page      = is_page();
        $is_shop_page = function_exists('is_woocommerce') && (is_woocommerce() || is_cart() || is_checkout() || is_account_page());

        foreach ($this->rules as $key => $config) {
            if (!is_array($config)) {
                continue;
            }

            $handle = $config['handle'] ?? (is_string($key) ? $key : '');
            if (empty($handle)) {
                continue;
            }

            // Check Exceptions (Exclude URLs / Exclude Post IDs)
            $exclude_url = trim((string) ($config['exclude_url'] ?? ''));
            if ($exclude_url !== '') {
                $exclude_pattern = '#' . str_replace('#', '\#', $exclude_url) . '#i';
                if (@preg_match($exclude_pattern, $uri)) {
                    continue; // Skip unloading, keep asset loaded
                }
            }

            $exclude_ids = array_filter(array_map('intval', explode(',', (string) ($config['exclude_ids'] ?? ''))));
            if (!empty($exclude_ids) && in_array($current_id, $exclude_ids, true)) {
                continue; // Skip unloading for this specific post/page ID
            }

            $target   = $config['target'] ?? (!empty($config['everywhere']) ? 'everywhere' : 'url_match');
            $disabled = false;

            switch ($target) {
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
                    $pattern = trim((string) ($config['url_match'] ?? ''));
                    if ($pattern !== '') {
                        $regex = '#' . str_replace('#', '\#', $pattern) . '#i';
                        if (@preg_match($regex, $uri)) {
                            $disabled = true;
                        }
                    }
                    break;
            }

            if ($disabled) {
                $type = $config['type'] ?? 'script';
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