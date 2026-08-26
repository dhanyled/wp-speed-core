<?php
declare(strict_types=1);

namespace WPSpeedCore\PageSpeed;

if (!defined('ABSPATH')) {
    exit;
}

final class PageSpeedController {
    private PageSpeedService $service;

    public function __construct(PageSpeedService $service) {
        $this->service = $service;
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        register_rest_route('wp-speed-core/v1', '/pagespeed', [
            'methods'           => 'GET',
            'callback'         => [$this, 'get_results'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

    public function check_permission(): bool {
        return current_user_can('manage_options');
    }

    public function get_results(\WP_REST_Request $request): \WP_REST_Response {
        $raw_url       = sanitize_url((string) ($request->get_param('url') ?? ''));
        $strategy      = sanitize_text_field((string) ($request->get_param('strategy') ?? 'mobile'));
        $force_refresh = !empty($request->get_param('force'));

        // Prevent SSRF or external domain quota misuse: restrict target to site host
        $site_host   = (string) wp_parse_url(home_url(), PHP_URL_HOST);
        $target_host = (string) wp_parse_url($raw_url, PHP_URL_HOST);
        $target_url  = (!empty($raw_url) && $target_host === $site_host) ? $raw_url : home_url('/');

        $results = $this->service->get_audit_results($target_url, $strategy, $force_refresh);
        return new \WP_REST_Response($results, 200);
    }
}
