<?php
declare(strict_types=1);

namespace WPSpeedCore\Engine;

if (!defined('ABSPATH')) {
    exit;
}

class EnvironmentScanner {
    private array $data = [];

    public function __construct() {
        $this->scan();
    }

    public function scan(): array {
        global $wp_version;

        $opcache = function_exists('opcache_get_status') && !empty(opcache_get_status(false)['opcache_enabled']);
        $jit = false;
        if ($opcache && function_exists('opcache_get_status')) {
            $st = opcache_get_status(false);
            $jit = !empty($st['jit']['enabled']) && !empty($st['jit']['on']);
        }

        $mem = ini_get('memory_limit') ?: '128M';
        $server = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';

        $active_plugins = (array) get_option('active_plugins', []);
        if (is_multisite()) {
            $active_plugins = array_merge($active_plugins, array_keys((array) get_site_option('active_sitewide_plugins', [])));
        }

        $this->data = [
            'php' => [
                'version' => PHP_VERSION,
                'opcache' => $opcache,
                'jit' => $jit,
                'memory_limit' => $mem,
                'memory_bytes' => $this->to_bytes($mem),
            ],
            'server' => [
                'software' => $server,
                'is_litespeed' => stripos($server, 'litespeed') !== false,
                'is_nginx' => stripos($server, 'nginx') !== false,
                'is_apache' => stripos($server, 'apache') !== false,
            ],
            'wordpress' => [
                'version' => $wp_version,
                'is_block_theme' => function_exists('wp_is_block_theme') && wp_is_block_theme(),
                'has_tag_processor' => class_exists('\WP_HTML_Tag_Processor'),
                'has_script_strategies' => function_exists('wp_script_add_data'),
                'has_font_library' => function_exists('wp_get_font_dir'),
            ],
            'page_builder' => [
                'elementor' => did_action('elementor/loaded') || class_exists('\Elementor\Plugin'),
                'divi' => defined('ET_BUILDER_VERSION'),
                'bricks' => defined('BRICKS_VERSION'),
            ],
            'ecommerce' => [
                'woocommerce' => class_exists('WooCommerce'),
            ],
            'active_plugins' => $active_plugins,
        ];

        return $this->data;
    }

    public function get(): array {
        return $this->data;
    }

    private function to_bytes(string $val): int {
        $val = trim($val);
        $last = strtolower(substr($val, -1));
        $num = (int) $val;
        switch ($last) {
            case 'g': $num *= 1024 * 1024 * 1024; break;
            case 'm': $num *= 1024 * 1024; break;
            case 'k': $num *= 1024; break;
        }
        return $num;
    }
}
