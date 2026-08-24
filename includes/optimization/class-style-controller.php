<?php
declare(strict_types=1);

namespace WPSpeedCore\Optimization;

if (!defined('ABSPATH')) {
    exit;
}

class StyleController {
    public function __construct() {
        $s = (array) get_option('wpsc_settings', []);
        if (!empty($s['style']['below_fold_skip']) && !is_admin()) {
            add_action('wp_head', [$this, 'inject_content_visibility'], 99);
        }
    }

    public function inject_content_visibility(): void {
        echo "\n<style id=\"wpsc-content-visibility\">
            footer, #footer, .site-footer, #colophon, .comments-area, .related-posts {
                content-visibility: auto;
                contain-intrinsic-size: 1px 500px;
            }
        </style>\n";
    }
}
