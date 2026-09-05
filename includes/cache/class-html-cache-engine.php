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
        add_action('wpsc_warm_cache', [$this, 'warm_cache']);

        // WooCommerce Granular Invalidation
        add_action('woocommerce_update_product', [$this, 'purge_wc_product']);
        add_action('woocommerce_product_set_stock', [$this, 'purge_wc_product']);
        add_action('woocommerce_variation_has_changed', [$this, 'purge_wc_product']);

        // FSE & Theme Invalidation
        add_action('wp_update_nav_menu', [$this, 'purge_all']);
        add_action('after_switch_theme', [$this, 'purge_all']);
        add_action('customize_save_after', [$this, 'purge_all']);
        add_action('save_post_wp_template', [$this, 'purge_all']);
        add_action('save_post_wp_template_part', [$this, 'purge_all']);
        add_action('save_post_wp_global_styles', [$this, 'purge_all']);
    }

    private function cache_file(): string {
        $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
        if (empty($host)) {
            $host = sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'] ?? 'default'));
        }
        $uri  = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? '/'));

        $parts = explode('?', $uri, 2);
        $path  = $parts[0];
        $query = $parts[1] ?? '';

        if ($query !== '') {
            parse_str($query, $params);
            $ignored = [
                // Google Ads, Campaign & Analytics
                'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id',
                'utm_source_platform', 'utm_creative_format', 'utm_marketing_tactic',
                'gad_source', 'gad_campaignid', 'gclid', 'gbraid', 'wbraid', '_gl', '_ga', 'dclid', 'srsltid',
                // Meta / Facebook & Instagram
                'fbclid', 'fb_action_ids', 'fb_action_types', 'fb_source', 'igshid',
                // Microsoft Bing, Twitter, TikTok, Pinterest, Yandex
                'msclkid', 'twclid', 'ttclid', 'epik', 'yclid',
                // Mailchimp & Hubspot
                'mc_cid', 'mc_eid', '_hsenc', '_hsmi', 'hsCtaTracking',
                // Internal Debug & Bypass
                'nowpsc', 'wpsc_bypass'
            ];

            foreach (array_keys($params) as $key) {
                if (in_array($key, $ignored, true) || strpos((string) $key, 'utm_') === 0) {
                    unset($params[$key]);
                }
            }

            ksort($params);
            $clean_query = http_build_query($params);
            if ($clean_query !== '') {
                $path .= '?' . $clean_query;
            }
        }

        $mobile_suffix = (!empty($this->opts['cache_mobile']) && function_exists('wp_is_mobile') && wp_is_mobile()) ? '.mobile' : '';
        return $this->dir . md5($host . $path) . $mobile_suffix . '.html';
    }

    private function is_cacheable(): bool {
        if (\WPSpeedCore\Kernel::is_bypassed()) {
            return false;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            return false;
        }
        if (wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || is_feed()) {
            return false;
        }
        if (empty($this->opts['cache_authenticated']) && is_user_logged_in()) {
            return false;
        }

        foreach ($_COOKIE as $cookie_name => $val) {
            if (
                strpos($cookie_name, 'wp_woocommerce_session_') === 0 ||
                strpos($cookie_name, 'woocommerce_items_in_cart') === 0 ||
                strpos($cookie_name, 'woocommerce_cart_hash') === 0 ||
                strpos($cookie_name, 'comment_author_') === 0
            ) {
                return false;
            }
        }

        $ex_raw = $this->opts['cache_exclusions'] ?? '';
        if (!empty($ex_raw)) {
            $uri     = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? '/'));
            $ex_list = array_filter(array_map('trim', explode(chr(10), $ex_raw)));
            foreach ($ex_list as $ex) {
                if (empty($ex)) {
                    continue;
                }
                $ex_clean = ltrim($ex, '/');
                $uri_clean = ltrim($uri, '/');

                if (strpos($ex, '*') !== false) {
                    $pattern = '#^' . str_replace('\*', '.*', preg_quote($ex_clean, '#')) . '#i';
                    if (preg_match($pattern, $uri_clean)) {
                        return false;
                    }
                } elseif (stripos($uri, $ex) !== false || stripos($uri_clean, $ex_clean) !== false) {
                    return false;
                }
            }
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
            // Nonce Lifetime Awareness: If page carries an active form nonce and is older than 10h, let WP re-render fresh token
            $age = time() - filemtime($file);
            if ($age > 36000) {
                $tail = file_get_contents($file, false, null, -120);
                if ($tail && strpos($tail, 'Nonce-Protected') !== false) {
                    return;
                }
            }

            header('X-WPSC-Cache: HIT');
            header('Content-Type: text/html; charset=UTF-8');

            if (!empty($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false && file_exists($file . '.gz')) {
                header('Content-Encoding: gzip');
                header('Vary: Accept-Encoding');
                readfile($file . '.gz');
                exit;
            }

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

        $has_form_nonce = (bool) preg_match('/name=["\'](?:_wpnonce|woocommerce-login-nonce|woocommerce-register-nonce|wpcf7-nonce)["\']/i', $html);
        $nonce_tag = $has_form_nonce ? ' [Nonce-Protected: Max TTL 10h]' : '';
        $content = $html . "\n/* WP Speed Core Cached" . $nonce_tag . ": " . gmdate('Y-m-d H:i:s') . " UTC */";
        file_put_contents($file, $content, LOCK_EX);

        if (function_exists('gzencode')) {
            $gz_file = $file . '.gz';
            $compressed = gzencode($content, 9);
            if ($compressed !== false) {
                file_put_contents($gz_file, $compressed, LOCK_EX);
            }
        }

        header('X-WPSC-Cache: MISS');
        return $html;
    }

    public function purge_url(string $url): void {
        if (empty($url)) {
            return;
        }
        $p         = wp_parse_url($url);
        $home_host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
        $url_host  = $p['host'] ?? $home_host;

        if (!empty($home_host) && !empty($url_host) && strcasecmp($url_host, $home_host) !== 0) {
            return;
        }

        $path = $p['path'] ?? '/';
        $base = $this->dir . md5($home_host . $path);

        foreach (['.html', '.mobile.html', '.html.gz', '.mobile.html.gz'] as $suffix) {
            $f = $base . $suffix;
            if (file_exists($f)) {
                wp_delete_file($f);
            }
        }

        // Trigger action for external edge purgers (e.g. Cloudflare API Sync)
        do_action('wpsc_purge_single_url', $url);

        // Synchronize URL purge with hosting edge cache if active (e.g. StackCache)
        if (class_exists('\WPStackCache') && method_exists('\WPStackCache', 'purge')) {
            try {
                \WPStackCache::purge($url);
            } catch (\Throwable $e) {
                // Silently ignore external CDN purge exceptions
            }
        }
    }

    public function purge_post(int $post_id): void {
        $url = get_permalink($post_id);
        if ($url) {
            $this->purge_url($url);
        }
        $this->purge_url(home_url('/'));
        $page_for_posts = (int) get_option('page_for_posts');
        if ($page_for_posts > 0) {
            $blog_url = get_permalink($page_for_posts);
            if ($blog_url) {
                $this->purge_url($blog_url);
            }
        }
        if ($this->logger) {
            $this->logger->info('Cache purged for post ID: ' . $post_id, ['url' => $url]);
        }
    }

    public function purge_by_comment(int $comment_id): void {
        $comment = get_comment($comment_id);
        if ($comment && !empty($comment->comment_post_ID)) {
            $this->purge_post((int) $comment->comment_post_ID);
        }
    }

    /**
     * Granular cache purge for WooCommerce products, category archives, and shop page.
     * Prevents cache desynchronization when prices, sales, or stock counts change.
     */
    public function purge_wc_product($product_id): void {
        $id = is_numeric($product_id) ? (int) $product_id : 0;
        if ($id <= 0) {
            return;
        }

        $url = get_permalink($id);
        if ($url) {
            $this->purge_url($url);
        }

        // If it is a variation, also purge the parent product
        $parent_id = wp_get_post_parent_id($id);
        if ($parent_id > 0) {
            $parent_url = get_permalink($parent_id);
            if ($parent_url) {
                $this->purge_url($parent_url);
            }
        }

        // Purge shop archive page
        if (function_exists('wc_get_page_id')) {
            $shop_page_id = wc_get_page_id('shop');
            if ($shop_page_id > 0) {
                $shop_url = get_permalink($shop_page_id);
                if ($shop_url) {
                    $this->purge_url($shop_url);
                }
            }
        }

        // Purge associated product category archives
        $cats = get_the_terms($id, 'product_cat');
        if (!empty($cats) && !is_wp_error($cats)) {
            foreach ($cats as $cat) {
                $term_link = get_term_link($cat);
                if (!is_wp_error($term_link) && is_string($term_link)) {
                    $this->purge_url($term_link);
                }
            }
        }

        // Purge homepage
        $this->purge_url(home_url('/'));

        if ($this->logger) {
            $this->logger->info('WooCommerce granular cache purge executed for product ID: ' . $id, ['url' => $url]);
        }
    }

    public function purge_all(): void {
        $files = glob($this->dir . '*.html*');
        $count = 0;
        if ($files) {
            foreach ($files as $f) {
                if (is_file($f)) {
                    wp_delete_file($f);
                    $count++;
                }
            }
        }

        // Synchronize full purge with hosting edge cache (e.g. StackCache, LiteSpeed, etc.)
        if (class_exists('\WPStackCache') && method_exists('\WPStackCache', 'purge')) {
            try {
                \WPStackCache::purge('all');
            } catch (\Throwable $e) {
                // Silently ignore external CDN purge exceptions
            }
        }
        if (has_action('litespeed_purge_all')) {
            do_action('litespeed_purge_all');
        }

        if ($this->logger) {
            $this->logger->info('Full cache purge completed.', ['purged_files_count' => $count]);
        }
    }

    public function warm_cache(): int {
        $home_url = home_url('/');
        $urls     = [$home_url];

        $rss_feed = get_feed_link();
        if ($rss_feed) {
            $urls[] = $rss_feed;
        }

        // Extract top URLs from XML sitemap (Core wp-sitemap.xml or Yoast/RankMath)
        $sitemap_candidates = [
            home_url('/wp-sitemap.xml'),
            home_url('/sitemap_index.xml'),
            home_url('/sitemap.xml'),
        ];

        foreach ($sitemap_candidates as $sm_url) {
            $sm_resp = wp_remote_get($sm_url, [
                'timeout'   => 4,
                'sslverify' => apply_filters('wpsc_warm_cache_sslverify', true),
                'headers'   => ['User-Agent' => 'WPSC-CacheWarmer/1.0'],
            ]);
            if (!is_wp_error($sm_resp) && wp_remote_retrieve_response_code($sm_resp) === 200) {
                $body = wp_remote_retrieve_body($sm_resp);
                if (preg_match_all('#<loc>([^<]+)</loc>#i', $body, $matches)) {
                    foreach (array_slice($matches[1], 0, 25) as $loc) {
                        $loc = esc_url_raw(trim($loc));
                        if ($loc && !in_array($loc, $urls, true) && strpos($loc, '.xml') === false) {
                            $urls[] = $loc;
                        }
                    }
                }
                break;
            }
        }

        $warmed = 0;
        foreach (array_unique($urls) as $u) {
            $response = wp_remote_get($u, [
                'timeout'   => 5,
                'sslverify' => apply_filters('wpsc_warm_cache_sslverify', true),
                'headers'   => ['User-Agent' => 'WPSC-CacheWarmer/1.0'],
            ]);
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $warmed++;
            }
        }

        if ($this->logger) {
            $this->logger->info('Cache warming process executed.', ['warmed_urls_count' => $warmed, 'sampled_urls' => array_slice($urls, 0, 8)]);
        }

        return $warmed;
    }
}
