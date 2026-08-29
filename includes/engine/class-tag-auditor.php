<?php
declare(strict_types=1);

namespace WPSpeedCore\Engine;

if (!defined('ABSPATH')) {
    exit;
}

class TagAuditor {
    private ?Logger $logger;

    public function __construct(?Logger $logger = null) {
        $this->logger = $logger;
        if (!is_admin()) {
            add_action('template_redirect', [$this, 'start'], 1);
        }
    }

    public function start(): void {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || wp_is_json_request()) {
            return;
        }
        ob_start([$this, 'scan_html']);
    }

    public function scan_html(string $html): string {
        if (strlen($html) < 200 || stripos($html, '<html') === false) {
            return $html;
        }

        $duplicates = [];

        if (preg_match_all('/(GTM-[A-Z0-9]+)/i', $html, $matches)) {
            $ids = array_unique($matches[1]);
            foreach ($ids as $id) {
                $c = substr_count($html, $id);
                if ($c > 1) {
                    $duplicates[] = [
                        'type'  => 'Google Tag Manager',
                        'id'    => $id,
                        'count' => $c,
                        'msg'   => 'GTM Container ' . $id . ' terdeteksi dimuat lebih dari 1x (' . $c . ' occurrences).',
                    ];
                }
            }
        }

        if (preg_match_all('/(?:gtag\s*\(\s*[\'"]config[\'"]\s*,\s*[\'"]|googletagmanager\.com\/gtag\/js\?id=|data-ga-id=["\'])(G-[A-Z0-9]{8,12})/i', $html, $matches)) {
            $ids = array_unique($matches[1]);
            foreach ($ids as $id) {
                $c = substr_count($html, $id);
                if ($c > 1) {
                    $duplicates[] = [
                        'type'  => 'Google Analytics 4',
                        'id'    => $id,
                        'count' => $c,
                        'msg'   => 'GA4 Measurement ID ' . $id . ' terpasang ganda (' . $c . ' occurrences).',
                    ];
                }
            }
        }

        if (preg_match_all('/fbq\s*\(\s*[\'"]init[\'"]\s*,\s*[\'"]([0-9]+)[\'"]\s*\)/i', $html, $matches)) {
            $counts = array_count_values($matches[1]);
            foreach ($counts as $pid => $c) {
                if ($c > 1) {
                    $duplicates[] = [
                        'type'  => 'Meta / Facebook Pixel',
                        'id'    => (string) $pid,
                        'count' => $c,
                        'msg'   => 'Meta Pixel ID ' . $pid . ' diinisialisasi ' . $c . ' kali.',
                    ];
                }
            }
        }

        if (preg_match_all('/clarity\s*\(\s*[\'"]init[\'"]\s*,\s*[\'"]([a-z0-9]+)[\'"]\s*\)/i', $html, $matches)) {
            $counts = array_count_values($matches[1]);
            foreach ($counts as $cid => $c) {
                if ($c > 1) {
                    $duplicates[] = [
                        'type'  => 'Microsoft Clarity',
                        'id'    => (string) $cid,
                        'count' => $c,
                        'msg'   => 'Microsoft Clarity Project ID ' . $cid . ' dimuat ' . $c . ' kali.',
                    ];
                }
            }
        }

        if (!empty($duplicates)) {
            set_transient('wpsc_tag_audit', $duplicates, 12 * HOUR_IN_SECONDS);
            if ($this->logger) {
                foreach ($duplicates as $d) {
                    $this->logger->warning('Duplicate tracking tag detected: ' . $d['type'], [
                        'tag_id' => $d['id'],
                        'count'  => $d['count'],
                    ]);
                }
            }
        } else {
            delete_transient('wpsc_tag_audit');
        }

        return $html;
    }

    /**
     * Retrieve detected duplicate tracking tags from transient.
     *
     * @return array
     */
    public function get_duplicates(): array {
        return (array) (get_transient('wpsc_tag_audit') ?: []);
    }
}
