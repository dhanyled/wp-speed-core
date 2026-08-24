<?php
declare(strict_types=1);

namespace WPSpeedCore\Optimization;

if (!defined('ABSPATH')) {
    exit;
}

class MediaController {
    private array $opts;

    public function __construct() {
        $s = (array) get_option('wpsc_settings', []);
        $this->opts = $s['media'] ?? [];

        if (!is_admin()) {
            add_action('template_redirect', [$this, 'start_media_buffer'], 3);
        }
    }

    public function start_media_buffer(): void {
        if (is_feed() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || wp_is_json_request()) {
            return;
        }
        ob_start([$this, 'process_images']);
    }

    public function process_images(string $html): string {
        if (strlen($html) < 200 || stripos($html, '<html') === false || !class_exists('\WP_HTML_Tag_Processor')) {
            return $html;
        }

        $p = new \WP_HTML_Tag_Processor($html);
        $count = 0;

        while ($p->next_tag(['tag_name' => 'img'])) {
            $count++;
            $src = $p->get_attribute('src');
            if (!$src) {
                continue;
            }

            if ($count === 1 && !empty($this->opts['auto_hero_priority'])) {
                $p->set_attribute('fetchpriority', 'high');
                $p->set_attribute('loading', 'eager');
                $p->set_attribute('decoding', 'async');
            } elseif (!empty($this->opts['native_lazy'])) {
                if ($p->get_attribute('loading') === null) {
                    $p->set_attribute('loading', 'lazy');
                    $p->set_attribute('decoding', 'async');
                }
            }
        }

        return $p->get_updated_html();
    }
}
