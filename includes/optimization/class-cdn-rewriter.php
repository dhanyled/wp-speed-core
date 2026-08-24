<?php
declare(strict_types=1);

namespace WPSpeedCore\Optimization;

if (!defined('ABSPATH')) {
    exit;
}

class CdnRewriter {
    private array $opts;

    public function __construct() {
        $s = (array) get_option('wpsc_settings', []);
        $this->opts = $s['cdn'] ?? [];

        if (!empty($this->opts['enable_cdn']) && !empty($this->opts['cdn_url']) && !is_admin()) {
            add_action('template_redirect', [$this, 'start_cdn_buffer'], 1);
        }
    }

    public function start_cdn_buffer(): void {
        if (is_feed() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || wp_is_json_request()) {
            return;
        }
        ob_start([$this, 'rewrite_urls']);
    }

    public function rewrite_urls(string $html): string {
        if (strlen($html) < 200 || stripos($html, '<html') === false) {
            return $html;
        }

        $cdn_url  = rtrim(trim($this->opts['cdn_url']), '/');
        $site_url = rtrim(site_url(), '/');

        if (empty($cdn_url) || $cdn_url === $site_url) {
            return $html;
        }

        $escaped_site = preg_quote($site_url, '#');
        $extensions   = 'jpg|jpeg|png|gif|webp|avif|svg|css|js|woff|woff2|ttf|eot';

        $pattern = '#(' . $escaped_site . ')/(wp-content|wp-includes)/([^"\':\s\?\#]+\.(' . $extensions . '))([\?\#][^"\':\s]*)?#i';

        return (string) preg_replace($pattern, $cdn_url . '/$2/$3$5', $html);
    }
}
