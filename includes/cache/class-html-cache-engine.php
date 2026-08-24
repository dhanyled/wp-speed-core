<?php
declare(strict_types=1);

namespace WPSpeedCore\Cache;

use WPSpeedCore\Engine\Logger;

if (!defined('ABSPATH')) {
    exit;
}

class HtmlCacheEngine {
    private array $opts;
    private string $dir;
    private ?Logger $logger;

    public function __construct(?Logger $logger = null) {
        $s            = (array) get_option('wpsc_settings', []);
        $this->opts   = $s['cache'] ?? [];
        $this->dir    = WPSC_CACHE_DIR . 'html/';
        $this->logger = $logger;

        $this->init();
    }

    private function init(): void {
        if (empty($this->opts['html_cache'])) {
            return;
        }

        if (!is_admin()) {
            add_action('init', [$this, 'serve'], 1);
            add_action('template_redirect', [$this, 'capture'], 9999);
        }

        add_action('save_post', [$this, 'purge_post']);
        add_action('comment_post', [$this, 'purge_by_comment']);
        add_action('wp_trash_post', [$this, 'purge_post']);
        add_action('wpsc_purge_all', [$this, 'purge_all']);
    }

    private function cache_file(): string {
        $host = sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'] ?? 'default'));
        $uri  = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? '/'));
        $path = strtok($uri, '?');
        return $this->dir . md5($host . $path) . '.html';
    }

    private function is_cacheable(): bool {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            return false;
        }
        if (wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || is_feed()) {
            return false;
        }
        if (empty($this->opts['cache_authenticated']) && is_user_logged_in()) {
            return false;
        }
        if (function_exists('is_cart') && is_cart()) {
            return false;
        }
        if (function_exists('is_checkout') && is_checkout()) {
            return false;
        }
        if (function_exists('is_account_page') && is_account_page()) {
            return false;
        }
        return true;
    }

    public function serve(): void {
        if (!$this->is_cacheable()) {
            return;
        }
        $file = $this->cache_file();
        $ttl  = (int) ($this->opts['cache_ttl'] ?? 86400);

        if (file_exists($file) && (time() - filemtime($file)) < $ttl) {
            header('X-WPSC-Cache: HIT');
            header('Content-Type: text/html; charset=UTF-8');
            readfile($file);
            exit;
        }
    }

    public function capture(): void {
        if (!$this->is_cacheable()) {
            return;
        }
        ob_start([$this, 'save']);
    }

    public function save(string $html): string {
        if (strlen($html) < 300 || http_response_code() !== 200) {
            return $html;
        }
        $file = $this->cache_file();
        if (!file_exists(dirname($file))) {
            wp_mkdir_p(dirname($file));
        }
        $content = $html . "\n/* WP Speed Core Cached: " . gmdate('Y-m-d H:i:s') . " UTC */";
        file_put_contents($file, $content, LOCK_EX);
        header('X-WPSC-Cache: MISS');
        return $html;
    }

    public function purge_post(int $post_id): void {
        $url = get_permalink($post_id);
        if (!$url) {
            return;
        }
        $p = wp_parse_url($url);
        $file = $this->dir . md5(($p['host'] ?? '') . ($p['path'] ?? '/')) . '.html';
        if (file_exists($file)) {
            wp_delete_file($file);
            if ($this->logger) {
                $this->logger->info('Cache purged for post ID: ' . $post_id, ['url' => $url]);
            }
        }
    }

    public function purge_by_comment(int $comment_id): void {
        $comment = get_comment($comment_id);
        if ($comment && !empty($comment->comment_post_ID)) {
            $this->purge_post((int) $comment->comment_post_ID);
        }
    }

    public function purge_all(): void {
        $files = glob($this->dir . '*.html');
        $count = 0;
        if ($files) {
            foreach ($files as $f) {
                if (is_file($f)) {
                    wp_delete_file($f);
                    $count++;
                }
            }
        }
        if ($this->logger) {
            $this->logger->info('Full cache purge completed.', ['purged_files_count' => $count]);
        }
    }
}
