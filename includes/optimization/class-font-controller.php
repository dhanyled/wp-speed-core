<?php
declare(strict_types=1);

namespace WPSpeedCore\Optimization;

if (!defined('ABSPATH')) {
    exit;
}

class FontController {
    public function __construct() {
        if (!is_admin()) {
            add_filter('style_loader_tag', [$this, 'add_font_display_swap'], 10, 4);
            add_filter('wp_resource_hints', [$this, 'add_font_hints'], 10, 2);
        }
    }

    public function add_font_display_swap(string $html, string $handle, string $href, string $media): string {
        if (strpos($href, 'fonts.googleapis.com/css') !== false && strpos($href, 'display=') === false) {
            $sep = (strpos($href, '?') !== false) ? '&amp;' : '?';
            $html = str_replace($href, $href . $sep . 'display=swap', $html);
        }
        return $html;
    }

    public function add_font_hints(array $urls, string $relation_type): array {
        if ($relation_type === 'dns-prefetch') {
            $urls[] = '//fonts.googleapis.com';
            $urls[] = '//fonts.gstatic.com';
        }
        return $urls;
    }
}
