<?php
declare(strict_types=1);

namespace WPSpeedCore;

use WPSpeedCore\Engine\Logger;

if (!defined('ABSPATH')) {
    exit;
}

final class Bootstrap {
    private const DEFAULTS = [
        'general' => [
            'mode'               => 'smart',
            'strip_emojis'       => 1,
            'strip_embeds'       => 1,
            'strip_dashicons'    => 1,
            'block_xmlrpc'       => 1,
            'heartbeat_mode'     => 'throttle',
            'heartbeat_interval' => 60,
        ],
        'cache' => [
            'html_cache'          => 1,
            'cache_ttl'           => 86400,
            'cache_authenticated' => 0,
            'cache_mobile'        => 1,
        ],
        'script' => [],
        'style' => [
            'below_fold_skip'    => 1,
        ],
        'media' => [
            'native_lazy'        => 1,
            'auto_hero_priority' => 1,
            'video_placeholder'  => 1,
        ],
        'preload' => [
            'speculation_rules' => 1,
            'speculation_level' => 'moderate',
        ],
        'audit' => [
            'deduplicate_tags'   => 1,
            'overlap_check'      => 1,
        ],
    ];

    public static function activate(): void {
        foreach (['', 'html/', 'css/', 'fonts/', 'logs/'] as $sub) {
            $dir = WPSC_CACHE_DIR . $sub;
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }
        }

        if (!get_option('wpsc_settings')) {
            $defaults = self::DEFAULTS;
            $defaults['script'] = [
                'delayed_execution'  => 1,
                'execution_strategy' => 'chunked',
                'use_native_defer'   => 1,
                'exclusion_list'     => "gtag\ngtag.js\ngoogle-analytics\nanalytics.js\nrecaptcha\nturnstile\nhcaptcha\njquery.min.js",
            ];
            update_option('wpsc_settings', $defaults);
        }

        if (!wp_next_scheduled('wpsc_maintenance')) {
            wp_schedule_event(time(), 'daily', 'wpsc_maintenance');
        }

        $logger = new Logger();
        $logger->info('WP Speed Core v' . WPSC_VERSION . ' activated.');
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook('wpsc_maintenance');
        $logger = new Logger();
        $logger->info('WP Speed Core deactivated.');
    }
}
