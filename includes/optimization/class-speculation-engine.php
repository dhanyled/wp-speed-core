<?php
declare(strict_types=1);

namespace WPSpeedCore\Optimization;

if (!defined('ABSPATH')) {
    exit;
}

class SpeculationEngine {
    public function __construct() {
        if (!is_admin()) {
            $s = (array) get_option('wpsc_settings', []);
            if (!empty($s['preload']['speculation_rules'])) {
                add_action('wp_footer', [$this, 'inject_rules'], 999);
            }
            if (!empty($s['preload']['resource_hints'])) {
                add_action('wp_head', [$this, 'inject_resource_hints'], 2);
            }
        }
    }

    public function inject_resource_hints(): void {
        if (is_user_logged_in() || \WPSpeedCore\Kernel::is_bypassed()) {
            return;
        }

        $s = (array) get_option('wpsc_settings', []);
        $raw = (string) ($s['preload']['resource_hints'] ?? '');
        if (empty(trim($raw))) {
            return;
        }

        $lines = array_filter(array_map('trim', explode("\n", $raw)));
        if (empty($lines)) {
            return;
        }

        echo "\n<!-- WP Speed Core Resource Hints -->\n";
        foreach ($lines as $line) {
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            // Support optional explicit hint prefix: "dns-prefetch https://..." or "preconnect https://..."
            $type = 'preconnect';
            $url  = $line;
            if (preg_match('/^(preconnect|dns-prefetch)\s+(.+)$/i', $line, $m)) {
                $type = strtolower($m[1]);
                $url  = trim($m[2]);
            }

            if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://') && !str_starts_with($url, '//')) {
                $url = 'https://' . ltrim($url, '/');
            }

            $parsed = wp_parse_url($url);
            if (empty($parsed['host'])) {
                continue;
            }

            $origin = ($parsed['scheme'] ?? 'https') . '://' . $parsed['host'];
            if (!empty($parsed['port'])) {
                $origin .= ':' . $parsed['port'];
            }

            $escaped = esc_url($origin);
            if ($type === 'preconnect') {
                echo '<link rel="preconnect" href="' . $escaped . '" crossorigin>' . "\n";
                echo '<link rel="dns-prefetch" href="' . $escaped . '">' . "\n";
            } else {
                echo '<link rel="dns-prefetch" href="' . $escaped . '">' . "\n";
            }
        }
    }

    public function inject_rules(): void {
        if (is_user_logged_in() || \WPSpeedCore\Kernel::is_bypassed()) {
            return;
        }

        $s = (array) get_option('wpsc_settings', []);
        $level = $s['preload']['speculation_level'] ?? 'moderate';

        $rules = [
            'prerender' => [
                [
                    'source' => 'document',
                    'where' => [
                        'and' => [
                            ['href_matches' => '/*'],
                            [
                                'not' => [
                                    'or' => [
                                        ['href_matches' => '/wp-admin/*'],
                                        ['href_matches' => '/wp-login.php*'],
                                        ['href_matches' => '*/cart/*'],
                                        ['href_matches' => '*/checkout/*'],
                                        ['href_matches' => '*/my-account/*'],
                                        ['href_matches' => '*logout*'],
                                        ['href_matches' => '*.pdf'],
                                        ['href_matches' => '*.zip'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'eagerness' => $level,
                ],
            ],
        ];

        echo "\n<!-- WP Speed Core Speculation Rules Engine -->\n";
        echo '<script type="speculationrules">' . wp_json_encode($rules, JSON_UNESCAPED_SLASHES) . "</script>\n";
    }
}
