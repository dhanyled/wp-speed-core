<?php
declare(strict_types=1);

namespace WPSpeedCore\Optimization;

use WPSpeedCore\Engine\Logger;

if (!defined('ABSPATH')) {
    exit;
}

class CloudflarePurger {
    private const CF_API_ENDPOINT = 'https://api.cloudflare.com/client/v4/zones/%s/purge_cache';

    private array $opts;
    private ?Logger $logger;

    public function __construct(?Logger $logger = null) {
        $s            = (array) get_option('wpsc_settings', []);
        $this->opts   = $s['cloudflare'] ?? [];
        $this->logger = $logger;

        if ($this->is_enabled()) {
            add_action('wpsc_purge_all', [$this, 'purge_all']);
            add_action('wpsc_purge_single_url', [$this, 'purge_url']);
        }
    }

    public function is_enabled(): bool {
        return !empty($this->opts['enable']) && !empty($this->opts['api_token']) && !empty($this->opts['zone_id']);
    }

    public function purge_all(): bool {
        if (!$this->is_enabled()) {
            return false;
        }

        $endpoint = sprintf(self::CF_API_ENDPOINT, trim((string) $this->opts['zone_id']));
        $payload  = wp_json_encode(['purge_everything' => true]);

        $response = wp_remote_post($endpoint, [
            'timeout' => 8,
            'headers' => [
                'Authorization' => 'Bearer ' . trim((string) $this->opts['api_token']),
                'Content-Type'  => 'application/json',
            ],
            'body'    => $payload,
        ]);

        $success = false;
        if (!is_wp_error($response)) {
            $code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $success = ($code === 200 && !empty($body['success']));
        }

        if ($this->logger) {
            $this->logger->info('Cloudflare Edge Cache Purge All executed.', [
                'success' => $success,
                'zone_id' => substr((string) $this->opts['zone_id'], 0, 6) . '...',
            ]);
        }

        return $success;
    }

    public function purge_url(string $url): bool {
        if (!$this->is_enabled() || empty($url)) {
            return false;
        }

        $endpoint = sprintf(self::CF_API_ENDPOINT, trim((string) $this->opts['zone_id']));
        $payload  = wp_json_encode(['files' => [esc_url_raw($url)]]);

        $response = wp_remote_post($endpoint, [
            'timeout' => 8,
            'headers' => [
                'Authorization' => 'Bearer ' . trim((string) $this->opts['api_token']),
                'Content-Type'  => 'application/json',
            ],
            'body'    => $payload,
        ]);

        $success = false;
        if (!is_wp_error($response)) {
            $code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $success = ($code === 200 && !empty($body['success']));
        }

        if ($this->logger) {
            $this->logger->info('Cloudflare Edge Cache Single URL Purge executed.', [
                'url'     => $url,
                'success' => $success,
            ]);
        }

        return $success;
    }

    public function test_connection(): array {
        if (empty($this->opts['api_token']) || empty($this->opts['zone_id'])) {
            return ['success' => false, 'message' => __('API Token dan Zone ID wajib diisi.', 'wp-speed-core')];
        }

        $endpoint = 'https://api.cloudflare.com/client/v4/zones/' . trim((string) $this->opts['zone_id']);
        $response = wp_remote_get($endpoint, [
            'timeout' => 8,
            'headers' => [
                'Authorization' => 'Bearer ' . trim((string) $this->opts['api_token']),
                'Content-Type'  => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 200 && !empty($body['success'])) {
            $zone_name = $body['result']['name'] ?? 'Zone';
            return ['success' => true, 'message' => sprintf(__('Terhubung ke zone Cloudflare: %s', 'wp-speed-core'), $zone_name)];
        }

        $err = $body['errors'][0]['message'] ?? __('Autentikasi Cloudflare gagal. Periksa kembali Token dan Zone ID.', 'wp-speed-core');
        return ['success' => false, 'message' => $err];
    }
}
