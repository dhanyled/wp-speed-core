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
            $src   = $p->get_attribute('src');
            $class = $p->get_attribute('class') ?? '';

            if (!$src || strpos($src, 'data:image/svg+xml') === 0 || strpos($src, 'data:image/gif') === 0) {
                continue;
            }

            if (!empty($this->opts['auto_dimensions']) && $p->get_attribute('width') === null && $p->get_attribute('height') === null) {
                $site_url = site_url();
                if (strpos($src, $site_url) === 0 || strpos($src, '/') === 0) {
                    $rel_path = ltrim((string) parse_url($src, PHP_URL_PATH), '/');
                    $abs_path = ABSPATH . $rel_path;
                    if (file_exists($abs_path) && is_file($abs_path)) {
                        $size = @getimagesize($abs_path);
                        if (!empty($size[0]) && !empty($size[1])) {
                            $p->set_attribute('width', (string) $size[0]);
                            $p->set_attribute('height', (string) $size[1]);
                        }
                    }
                }
            }

            if (
                stripos($src, 'logo') !== false ||
                stripos($src, 'avatar') !== false ||
                stripos($src, 'gravatar') !== false ||
                stripos($src, 'icon') !== false ||
                stripos($class, 'logo') !== false ||
                stripos($class, 'avatar') !== false
            ) {
                continue;
            }

            $count++;

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

        $html = $p->get_updated_html();

        if (!empty($this->opts['lazy_iframes'])) {
            $html = preg_replace_callback('/<iframe\s+([^>]*?)src=["\']([^"\']+)["\']([^>]*?)>/i', function ($matches) {
                $full    = $matches[0];
                $src     = $matches[2];
                if (strpos($full, 'loading=') === false) {
                    $full = str_replace('<iframe ', '<iframe loading="lazy" ', $full);
                }
                return $full;
            }, $html);
        }

        return $html;
    }
}
