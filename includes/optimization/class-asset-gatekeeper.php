<?php
declare(strict_types=1);

namespace WPSpeedCore\Optimization;

if (!defined('ABSPATH')) {
    exit;
}

class AssetGatekeeper {
    private array $rules;

    public function __construct() {
        $this->rules = (array) get_option('wpsc_disabled_assets', []);
        if (!is_admin()) {
            add_action('wp_enqueue_scripts', [$this, 'enforce_rules'], 9999);
        }
    }

    public function enforce_rules(): void {
        $current_id = get_queried_object_id();
        $uri        = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? '/'));

        foreach ($this->rules as $handle => $config) {
            $disabled = false;
            if (!empty($config['everywhere'])) {
                $disabled = true;
            } elseif (!empty($config['posts']) && in_array($current_id, (array) $config['posts'], true)) {
                $disabled = true;
            } elseif (!empty($config['url_match'])) {
                $pattern = trim((string) $config['url_match']);
                if ($pattern !== '') {
                    $pattern = '#' . str_replace('#', '\#', $pattern) . '#i';
                    if (@preg_match($pattern, $uri)) {
                        $disabled = true;
                    }
                }
            }

            if ($disabled) {
                if (($config['type'] ?? 'script') === 'script') {
                    wp_dequeue_script($handle);
                    wp_deregister_script($handle);
                } else {
                    wp_dequeue_style($handle);
                    wp_deregister_style($handle);
                }
            }
        }
    }
}
