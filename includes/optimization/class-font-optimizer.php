<?php
declare(strict_types=1);

namespace WPSpeedCore\Optimization;

use WP_HTML_Tag_Processor;

if (!defined('ABSPATH')) {
    return;
}

final class FontOptimizer {
    public function __construct() {
        if (!is_admin()) {
            add_action('wp_head', [$this, 'inject_resource_hints'], 1);
            add_action('template_redirect', [$this, 'start_buffer'], 2);
        }
    }

    public function inject_resource_hints(): void {
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    }

    public function start_buffer(): void {
        ob_start([$this, 'optimize_fonts']);
    }

    public function optimize_fonts(string $html): string {
        if (empty($html) || strpos($html, 'fonts.googleapis.com') === false) {
            return $html;
        }

        $processor = new WP_HTML_Tag_Processor($html);

        while ($processor->next_tag(['tag_name' => 'LINK'])) {
            $href = $processor->get_attribute('href');
            if (!$href || strpos($href, 'fonts.googleapis.com') === false) {
                continue;
            }

            if (strpos($href, 'display=') === false) {
                $new_href = add_query_arg('display', 'swap', $href);
                $processor->set_attribute('href', $new_href);
            }
        }

        return $processor->get_updated_html();
    }
}
