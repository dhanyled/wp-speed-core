<?php
declare(strict_types=1);

namespace WPSpeedCore\Optimization;

if (!defined('ABSPATH')) {
    exit;
}

class ScriptController {
    private array $opts;

    public function __construct() {
        $s = (array) get_option('wpsc_settings', []);
        $this->opts = $s['script'] ?? [];
        $this->init();
    }

    private function init(): void {
        if (!empty($this->opts['use_native_defer'])) {
            add_filter('script_loader_tag', [$this, 'apply_defer'], 10, 3);
        }
        if (!empty($this->opts['delayed_execution']) && !is_admin()) {
            add_action('template_redirect', [$this, 'start_delay_buffer'], 2);
            add_action('wp_enqueue_scripts', [$this, 'enqueue_runner']);
        }
    }

    public function apply_defer(string $tag, string $handle, string $src): string {
        if (is_admin() || empty($src) || \WPSpeedCore\Kernel::is_bypassed() || strpos($tag, 'defer') !== false || strpos($tag, 'async') !== false) {
            return $tag;
        }
        if (in_array($handle, ['jquery', 'jquery-core', 'wpsc-delay-runner'], true)) {
            return $tag;
        }
        return str_replace('<script ', '<script defer ', $tag);
    }

    public function enqueue_runner(): void {
        if (\WPSpeedCore\Kernel::is_bypassed()) {
            return;
        }

        wp_enqueue_script(
            'wpsc-delay-runner',
            WPSC_URL . 'assets/js/delay-js.js',
            [],
            WPSC_VERSION,
            ['strategy' => 'async', 'in_footer' => false]
        );
    }

    public function start_delay_buffer(): void {
        if (is_feed() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || wp_is_json_request() || \WPSpeedCore\Kernel::is_bypassed()) {
            return;
        }
        ob_start([$this, 'queue_scripts']);
    }

    public function queue_scripts(string $html): string {
        if (strlen($html) < 200 || stripos($html, '<html') === false) {
            return $html;
        }

        $ex_raw  = $this->opts['exclusion_list'] ?? '';
        $ex_list = array_filter(array_map('trim', explode(chr(10), $ex_raw)));

        // Remove tracking keywords and heavy libraries from exclusions so they are safely delayed by default
        $tracking_keywords = [
            'gtag', 'gtag.js', 'google-analytics', 'analytics.js',
            'taggateway', 'taggatewayhaji', 'fupi', 'clarity',
            'helpgent', 'helpgent-pro', 'full-picture', 'fp-',
            '/wp-includes/js/dist/', 'wp-includes/js/dist',
            'react', 'react-dom', 'lodash', 'moment', 'recordrtc'
        ];
        $clean_ex_list = [];
        foreach ($ex_list as $ex) {
            $ex_trim = strtolower(trim($ex));
            if (!in_array($ex_trim, $tracking_keywords, true)) {
                $clean_ex_list[] = $ex_trim;
            }
        }

        // Auto-delay patterns for known heavy third-party & tracking scripts (600ms+ mobile CPU saving)
        $auto_delay_patterns = [
            'taggateway',
            'fupi',
            'clarity.js',
            'googletagmanager.com',
            'google-analytics.com',
            'connect.facebook.net',
            'static.cloudflareinsights.com',
            'RecordRTC',
            'helpgent',
            'react.min.js',
            'react-dom.min.js',
            'lodash.min.js',
            'moment.min.js',
            'wp-data.min.js',
            'wp-element.min.js',
            'wp-includes/js/dist/',
        ];

        if (!class_exists('\WP_HTML_Tag_Processor')) {
            return $html;
        }

        $p = new \WP_HTML_Tag_Processor($html);

        while ($p->next_tag(['tag_name' => 'script'])) {
            $src  = $p->get_attribute('src');
            $type = $p->get_attribute('type');

            if ($type) {
                $type_clean = strtolower(trim($type));
                if (in_array($type_clean, [
                    'application/ld+json',
                    'application/json',
                    'speculationrules',
                    'text/wpsc-queued',
                    'module',
                    'importmap',
                    'text/template',
                    'template',
                    'text/html',
                    'text/x-handlebars-template'
                ], true)) {
                    continue;
                }
            }

            $skip = false;
            $force_delay = false;

            if ($src) {
                if (strpos($src, 'delay-js') !== false || strpos($src, 'wpsc-delay-runner') !== false || $p->get_attribute('id') === 'wpsc-delay-runner-js') {
                    continue;
                }

                foreach ($auto_delay_patterns as $pattern) {
                    if (stripos($src, $pattern) !== false) {
                        $force_delay = true;
                        break;
                    }
                }

                if (!$force_delay) {
                    foreach ($clean_ex_list as $ex) {
                        if (!empty($ex) && strlen($ex) >= 3 && stripos($src, $ex) !== false) {
                            $skip = true;
                            break;
                        }
                    }
                }
            } else {
                $skip = true;
            }

            if (!$skip || $force_delay) {
                $p->set_attribute('type', 'text/wpsc-queued');
                if ($src) {
                    $p->remove_attribute('src');
                    $p->set_attribute('data-wpsc-src', $src);
                }
                $p->remove_attribute('data-no-defer');
                $p->remove_attribute('data-no-optimize');
                $p->remove_attribute('nowprocket');
            } else {
                // Dual-Layer Safety Fallback: If external script was skipped from delayed queue,
                // ensure it does not render-block by injecting 'defer' if missing.
                if (
                    $src &&
                    !$p->get_attribute('defer') &&
                    !$p->get_attribute('async') &&
                    !$p->get_attribute('data-no-defer')
                ) {
                    if (strpos($src, 'jquery.min.js') === false && strpos($src, 'jquery.js') === false) {
                        $p->set_attribute('defer', '');
                    }
                }
            }
        }

        return $p->get_updated_html();
    }
}