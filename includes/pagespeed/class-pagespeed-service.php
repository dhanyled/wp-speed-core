<?php
declare(strict_types=1);

namespace WPSpeedCore\PageSpeed;

if (!defined('ABSPATH')) {
    exit;
}

final class PageSpeedService {
    private const API_URL = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
    private const TRANSIENT_KEY_PREFIX = 'wpsc_psi_';
    private const TRANSIENT_TTL = 43200; // 12 Hours

    public function get_audit_results(string $target_url = '', string $strategy = 'mobile', bool $force_refresh = false): array {
        if (empty($target_url)) {
            $target_url = home_url('/');
        }

        $strategy = in_array($strategy, ['mobile', 'desktop'], true) ? $strategy : 'mobile';
        $transient_key = self::TRANSIENT_KEY_PREFIX . md5($target_url . '_' . $strategy);

        if (!$force_refresh) {
            $cached = get_transient($transient_key);
            if (is_array($cached) && !empty($cached)) {
                $cached['from_cache'] = true;
                return $cached;
            }
        }

        $endpoint = add_query_arg([
            'url'      => $target_url,
            'strategy' => $strategy,
            'category' => 'performance',
        ], self::API_URL);

        $response = wp_remote_get($endpoint, [
            'timeout'   => 45,
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

                if ($code === 429) {
            return [
                'success' => false,
                'error'   => 'Google PageSpeed API Rate Limit Exceeded (HTTP 429). Permintaan API tanpa API Key melebihi batas kuota Google. Silakan coba lagi beberapa saat atau tambahkan API Key Google PSI.',
            ];
        }

        if ($code !== 200 || empty($body)) {
            return [
                'success' => false,
                'error'   => 'Google PageSpeed API returned status code ' . $code,
            ];
        }

        $json = json_decode($body, true);
        if (!$json || !isset($json['lighthouseResult'])) {
            return [
                'success' => false,
                'error'   => 'Invalid PageSpeed API response format.',
            ];
        }

        $lh = $json['lighthouseResult'];
        $categories = $lh['categories'] ?? [];
        $audits = $lh['audits'] ?? [];

        $score = isset($categories['performance']['score']) ? (int)round($categories['performance']['score'] * 100) : 0;

        $parsed = [
            'success'     => true,
            'url'         => $target_url,
            'strategy'    => $strategy,
            'score'       => $score,
            'from_cache'  => false,
            'timestamp'   => time(),
            'metrics'     => [
                'lcp' => $audits['largest-contentful-paint']['displayValue'] ?? 'N/A',
                'fcp' => $audits['first-contentful-paint']['displayValue'] ?? 'N/A',
                'cls' => $audits['cumulative-layout-shift']['displayValue'] ?? 'N/A',
                'tbt' => $audits['total-blocking-time']['displayValue'] ?? 'N/A',
                'inp' => $audits['interaction-to-next-paint']['displayValue'] ?? ($audits['experimental-interaction-to-next-paint']['displayValue'] ?? 'N/A'),
                'si'  => $audits['speed-index']['displayValue'] ?? 'N/A',
            ],
        ];

        set_transient($transient_key, $parsed, self::TRANSIENT_TTL);

        return $parsed;
    }
}
