<?php
declare(strict_types=1);

namespace WPSpeedCore\Optimization;

if (!defined('ABSPATH')) {
    exit;
}

class SpeculationEngine {
    public function __construct() {
        $s = (array) get_option('wpsc_settings', []);
        if (!empty($s['preload']['speculation_rules']) && !is_admin()) {
            add_action('wp_footer', [$this, 'inject_rules'], 999);
        }
    }

    public function inject_rules(): void {
        if (is_user_logged_in()) {
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
