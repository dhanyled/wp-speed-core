<?php
declare(strict_types=1);

namespace WPSpeedCore\Optimization;

use WP_HTML_Tag_Processor;

if (!defined('ABSPATH')) {
    return;
}

final class MediaFacadeOptimizer {
    public function __construct() {
        if (!is_admin()) {
            add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
            add_action('template_redirect', [$this, 'start_buffer'], 2);
        }
    }

    public function enqueue_scripts(): void {
        wp_enqueue_script(
            'wpsc-iframe-facade',
            WPSC_URL . 'assets/js/iframe-facade.js',
            [],
            WPSC_VERSION,
            ['strategy' => 'defer', 'in_footer' => true]
        );
    }

    public function start_buffer(): void {
        ob_start([$this, 'optimize_iframes']);
    }

    public function optimize_iframes(string $html): string {
        if (empty($html) || strpos($html, '<iframe') === false) {
            return $html;
        }

        $processor = new WP_HTML_Tag_Processor($html);

        while ($processor->next_tag(['tag_name' => 'IFRAME'])) {
            $src = $processor->get_attribute('src');
            if (!$src) {
                continue;
            }

            if (strpos($src, 'youtube.com') !== false || strpos($src, 'youtu.be') !== false) {
                $video_id = $this->extract_youtube_id($src);
                if ($video_id) {
                    $processor->set_attribute('data-wpsc-facade', 'youtube');
                    $processor->set_attribute('data-wpsc-src', $src);
                    $processor->set_attribute('loading', 'lazy');
                    $srcdoc = '<style>*{padding:0;margin:0;overflow:hidden}html,body{height:100%}img,span{position:absolute;width:100%;top:0;bottom:0;margin:auto}span{height:1.5em;text-align:center;font:48px/1.5 sans-serif;color:white;text-shadow:0 0 0.5em black}</style><a href="' . esc_attr($src) . '?autoplay=1"><img src="https://img.youtube.com/vi/' . esc_attr($video_id) . '/hqdefault.jpg" alt="Video"><span>&#x25BA;</span></a>';
                    $processor->set_attribute('srcdoc', $srcdoc);
                }
            } elseif (strpos($src, 'vimeo.com') !== false) {
                $processor->set_attribute('loading', 'lazy');
                $processor->set_attribute('data-wpsc-facade', 'vimeo');
            } elseif (strpos($src, 'google.com/maps') !== false || strpos($src, 'maps.google.com') !== false) {
                $processor->set_attribute('loading', 'lazy');
                $processor->set_attribute('data-wpsc-facade', 'maps');
            }
        }

        return $processor->get_updated_html();
    }

    private function extract_youtube_id(string $url): ?string {
        if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
