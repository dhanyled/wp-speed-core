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
        if (is_admin() || empty($src) || strpos($tag, 'defer') !== false || strpos($tag, 'async') !== false) {
            return $tag;
        }
        if (in_array($handle, ['jquery', 'jquery-core', 'wpsc-delay-runner'], true)) {
            return $tag;
        }
        return str_replace('<script ', '<script defer ', $tag);
    }

    public function enqueue_runner(): void {
        wp_enqueue_script(
            'wpsc-delay-runner',
            WPSC_URL . 'assets/js/delay-js.js',
            [],
            WPSC_VERSION,
            ['strategy' => 'async', 'in_footer' => false]
        );
    }

    public function start_delay_buffer(): void {
        if (is_feed() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || wp_is_json_request()) {
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

        if (!class_exists('\WP_HTML_Tag_Processor')) {
            return $html;
        }

        $p = new \WP_HTML_Tag_Processor($html);

        while ($p->next_tag(['tag_name' => 'script'])) {
            $src  = $p->get_attribute('src');
            $type = $p->get_attribute('type');

            if ($type && in_array($type, ['application/ld+json', 'application/json', 'speculationrules', 'text/wpsc-queued'], true)) {
                continue;
            }

            $skip = false;
            if ($src) {
                if (strpos($src, 'wpsc-delay-runner') !== false) {
                    continue;
                }
                foreach ($ex_list as $ex) {
                    if (!empty($ex) && stripos($src, $ex) !== false) {
                        $skip = true;
                        break;
                    }
                }
            } else {
                $skip = true;
            }

            if (!$skip) {
                $p->set_attribute('type', 'text/wpsc-queued');
                if ($src) {
                    $p->remove_attribute('src');
                    $p->set_attribute('data-wpsc-src', $src);
                }
            }
        }

        return $p->get_updated_html();
    }
}