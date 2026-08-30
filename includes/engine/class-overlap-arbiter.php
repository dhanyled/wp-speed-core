<?php
declare(strict_types=1);

namespace WPSpeedCore\Engine;

if (!defined('ABSPATH')) {
    exit;
}

class OverlapArbiter {
    private EnvironmentScanner $scanner;

    public function __construct(EnvironmentScanner $scanner) {
        $this->scanner = $scanner;
    }

    public function audit_overlaps(): array {
        $env = $this->scanner->get();
        $active = $env['active_plugins'] ?? [];
        $results = [];

        $known = [
            'litespeed-cache/litespeed-cache.php' => [
                'name' => 'LiteSpeed Cache',
                'features' => ['Page Cache', 'Lazy Load', 'Minify'],
                'tip' => 'Jika Anda di LiteSpeed Server, pertahankan LSCache untuk cache, gunakan WP Speed Core untuk Delay JS & INP Shield.',
            ],
            'wp-rocket/wp-rocket.php' => [
                'name' => 'WP Rocket',
                'features' => ['Page Cache', 'Delay JS', 'Lazy Load'],
                'tip' => 'Hindari menjalankan dua full-cache plugin bersamaan untuk mencegah double-buffering & beban CPU tinggi.',
            ],
            'autoptimize/autoptimize.php' => [
                'name' => 'Autoptimize',
                'features' => ['Minify CSS/JS', 'Async CSS'],
                'tip' => 'WP Speed Core sudah memiliki native loading strategy (WP 6.3+). Autoptimize dapat dinonaktifkan.',
            ],
            'wp-smushit/wp-smush.php' => [
                'name' => 'Smush',
                'features' => ['Lazy Load'],
                'tip' => 'Matikan JS lazy-load di Smush dan gunakan Native HTML5 Engine WP Speed Core untuk Zero CLS.',
            ],
            'w3-total-cache/w3-total-cache.php' => [
                'name' => 'W3 Total Cache',
                'features' => ['Page Cache', 'Minify'],
                'tip' => 'Nonaktifkan Page Cache di salah satu plugin untuk mencegah konflik stale page.',
            ],
        ];

        $active_map = array_flip((array) $active);

        foreach ($known as $plugin_file => $info) {
            if (isset($active_map[$plugin_file])) {
                $results[] = [
                    'plugin'   => $info['name'],
                    'features' => $info['features'],
                    'tip'      => $info['tip'],
                ];
            }
        }

        return $results;
    }

    /**
     * Alias for audit_overlaps to match dashboard calling convention.
     *
     * @return array
     */
    public function get_conflicts(): array {
        return $this->audit_overlaps();
    }
}
