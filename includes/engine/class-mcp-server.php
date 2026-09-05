<?php
declare(strict_types=1);

namespace WPSpeedCore\Engine;

use WPSpeedCore\Kernel;

if (!defined('ABSPATH')) {
    exit;
}

class McpServer {
    private const REST_NAMESPACE = 'wpsc/v1';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        register_rest_route(self::REST_NAMESPACE, '/mcp', [
            'methods'             => ['GET', 'POST'],
            'callback'            => [$this, 'handle_mcp_request'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/mcp/tools', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_tool_definitions'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/mcp/execute', [
            'methods'             => 'POST',
            'callback'            => [$this, 'execute_tool'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

    public function check_permission(\WP_REST_Request $request): bool {
        if (current_user_can('manage_options')) {
            return true;
        }

        $auth_header = $request->get_header('authorization') ?? $request->get_header('x-wpsc-mcp-token');
        $token       = (string) get_option('wpsc_mcp_token', '');

        if ($token !== '' && $auth_header) {
            $clean_auth = trim(str_ireplace('Bearer', '', $auth_header));
            if (hash_equals($token, $clean_auth)) {
                return true;
            }
        }

        return false;
    }

    public function get_tool_definitions(): \WP_REST_Response {
        $tools = [
            [
                'name'        => 'wpsc_get_telemetry',
                'description' => 'Retrieve live performance telemetry, server stack (PHP, OPcache, Web Server), disk cache status, and active optimization engines.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => (object) [],
                ],
            ],
            [
                'name'        => 'wpsc_purge_cache',
                'description' => 'Purge all static HTML disk cache or purge a specific single URL.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'url' => [
                            'type'        => 'string',
                            'description' => 'Optional specific URL to purge. If omitted, purges entire site cache.',
                        ],
                    ],
                ],
            ],
            [
                'name'        => 'wpsc_warm_cache',
                'description' => 'Trigger automated cache pre-warming for homepage and RSS feed.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => (object) [],
                ],
            ],
            [
                'name'        => 'wpsc_autotune',
                'description' => 'Apply 1-Click Adaptive Auto-Tune based on current server environment scan.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => (object) [],
                ],
            ],
            [
                'name'        => 'wpsc_audit_conflicts',
                'description' => 'Audit duplicate tracking tags (GA4, GTM, Meta Pixel, Clarity) and overlapping caching plugin conflicts.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => (object) [],
                ],
            ],
            [
                'name'        => 'wpsc_optimize_db',
                'description' => 'Perform database housekeeping: purge expired transients, spam comments, post revisions, auto-drafts, and defragment database tables.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => (object) [],
                ],
            ],
            [
                'name'        => 'wpsc_get_checklist',
                'description' => 'Retrieve Core Web Vitals and site performance checklist compliance scorecard.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => (object) [],
                ],
            ],
            [
                'name'        => 'wpsc_get_logs',
                'description' => 'Retrieve latest diagnostic and execution log entries from WP Speed Core.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'limit' => [
                            'type'        => 'integer',
                            'description' => 'Number of recent log lines to retrieve (default: 50).',
                        ],
                    ],
                ],
            ],
            [
                'name'        => 'wpsc_sync_cloudflare',
                'description' => 'Purge edge cache on Cloudflare CDN via API (supports full site or single URL).',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'url' => [
                            'type'        => 'string',
                            'description' => 'Optional specific URL to purge from Cloudflare edge. If omitted, purges entire zone cache.',
                        ],
                    ],
                ],
            ],
            [
                'name'        => 'wpsc_migrate_settings',
                'description' => 'Detect and import performance settings from WP Rocket, Perfmatters, or LiteSpeed Cache.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'source' => [
                            'type'        => 'string',
                            'description' => 'Source plugin to import from: "wp_rocket", "perfmatters", or "litespeed". If "detect", lists available sources.',
                        ],
                    ],
                ],
            ],
        ];

        return new \WP_REST_Response([
            'protocolVersion' => '2024-11-05',
            'server'          => [
                'name'    => 'wp-speed-core-mcp',
                'version' => WPSC_VERSION,
            ],
            'tools'           => $tools,
        ], 200);
    }

    public function handle_mcp_request(\WP_REST_Request $request): \WP_REST_Response {
        $body = $request->get_json_params() ?? [];
        $method = $body['method'] ?? 'tools/list';

        if ($method === 'tools/list') {
            return $this->get_tool_definitions();
        }

        if ($method === 'tools/call') {
            $params   = $body['params'] ?? [];
            $tool_name = (string) ($params['name'] ?? '');
            $args      = (array) ($params['arguments'] ?? []);

            $result = $this->dispatch_tool($tool_name, $args);
            return new \WP_REST_Response([
                'jsonrpc' => '2.0',
                'id'      => $body['id'] ?? 1,
                'result'  => $result,
            ], 200);
        }

        return new \WP_REST_Response([
            'jsonrpc' => '2.0',
            'id'      => $body['id'] ?? 1,
            'error'   => [
                'code'    => -32601,
                'message' => 'Method not found',
            ],
        ], 404);
    }

    public function execute_tool(\WP_REST_Request $request): \WP_REST_Response {
        $body      = $request->get_json_params() ?: $request->get_params();
        $tool_name = sanitize_text_field((string) ($body['tool'] ?? $body['name'] ?? $request->get_param('tool') ?? $request->get_param('name') ?? ''));
        $args      = (array) ($body['arguments'] ?? $body['args'] ?? $request->get_param('arguments') ?? $request->get_param('args') ?? []);

        if ($tool_name === '') {
            return new \WP_REST_Response(['error' => 'Missing tool name'], 400);
        }

        $result = $this->dispatch_tool($tool_name, $args);
        return new \WP_REST_Response($result, 200);
    }

    private function dispatch_tool(string $tool_name, array $args): array {
        $kernel = Kernel::launch();

        switch ($tool_name) {
            case 'wpsc_get_telemetry':
                return $this->tool_get_telemetry($kernel);

            case 'wpsc_purge_cache':
                return $this->tool_purge_cache($args);

            case 'wpsc_warm_cache':
                return $this->tool_warm_cache($kernel);

            case 'wpsc_autotune':
                return $this->tool_autotune($kernel);

            case 'wpsc_audit_conflicts':
                return $this->tool_audit_conflicts($kernel);

            case 'wpsc_optimize_db':
                return $this->tool_optimize_db($kernel);

            case 'wpsc_get_checklist':
                return $this->tool_get_checklist();

            case 'wpsc_get_logs':
                return $this->tool_get_logs($kernel, $args);

            case 'wpsc_sync_cloudflare':
                return $this->tool_sync_cloudflare($kernel, $args);

            case 'wpsc_migrate_settings':
                return $this->tool_migrate_settings($kernel, $args);

            default:
                return ['error' => 'Unknown tool name: ' . $tool_name];
        }
    }

    private function tool_get_telemetry(Kernel $kernel): array {
        /** @var EnvironmentScanner|null $env */
        $env = $kernel->get('env');
        $env_data = $env ? $env->get() : [];

        $cache_dir   = WPSC_CACHE_DIR . 'html/';
        $cache_files = glob($cache_dir . '*.html') ?: [];
        $cache_size  = 0;
        foreach ($cache_files as $f) {
            $cache_size += (int) filesize($f);
        }

        $disabled_assets = (array) get_option('wpsc_disabled_assets', []);

        return [
            'version'         => WPSC_VERSION,
            'server'          => $env_data['server']['software'] ?? 'Unknown',
            'php_version'     => PHP_VERSION,
            'opcache_enabled' => (!empty($env_data['php']['opcache']) || !empty($env_data['php']['opcache_enabled'])),
            'jit_enabled'     => (!empty($env_data['php']['jit']) || !empty($env_data['php']['jit_enabled'])),
            'memory_limit'    => $env_data['php']['memory_limit'] ?? 'Unknown',
            'theme_fse'       => !empty($env_data['wordpress']['is_block_theme']),
            'woocommerce'     => !empty($env_data['ecommerce']['woocommerce']),
            'cache_count'     => count($cache_files),
            'cache_size_kb'   => round($cache_size / 1024, 2),
            'disabled_assets' => count($disabled_assets),
            'status'          => 'operational',
        ];
    }

    private function tool_purge_cache(array $args): array {
        $url = sanitize_text_field((string) ($args['url'] ?? ''));
        if ($url !== '') {
            $p = wp_parse_url($url);
            $home_host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
            $url_host  = $p['host'] ?? $home_host;

            if ($home_host !== '' && $url_host !== '' && strcasecmp($url_host, $home_host) !== 0) {
                return ['success' => false, 'action' => 'single_purge', 'url' => $url, 'error' => 'Invalid host for purge operation.'];
            }

            $file = WPSC_CACHE_DIR . 'html/' . md5($url_host . ($p['path'] ?? '/')) . '.html';
            $purged = false;
            if (file_exists($file)) {
                wp_delete_file($file);
                if (file_exists($file . '.gz')) {
                    wp_delete_file($file . '.gz');
                }
                $purged = true;
            }
            return ['success' => true, 'action' => 'single_purge', 'url' => $url, 'purged' => $purged];
        }

        do_action('wpsc_purge_all');
        return ['success' => true, 'action' => 'full_purge', 'message' => 'All HTML static cache files purged.'];
    }

    private function tool_warm_cache(Kernel $kernel): array {
        /** @var \WPSpeedCore\Cache\HtmlCacheEngine|null $cache */
        $cache = $kernel->get('cache');
        $warmed = $cache ? $cache->warm_cache() : 0;
        return ['success' => true, 'warmed_urls_count' => $warmed];
    }

    private function tool_autotune(Kernel $kernel): array {
        /** @var AdaptiveTuner|null $tuner */
        $tuner = $kernel->get('tuner');
        $applied = $tuner ? $tuner->apply() : false;
        return ['success' => $applied, 'message' => 'Adaptive Auto-Tune settings applied.'];
    }

    private function tool_audit_conflicts(Kernel $kernel): array {
        /** @var TagAuditor|null $auditor */
        $auditor = $kernel->get('auditor');
        $duplicates = $auditor ? $auditor->get_duplicates() : [];

        /** @var OverlapArbiter|null $arbiter */
        $arbiter = $kernel->get('arbiter');
        $conflicts = $arbiter ? $arbiter->get_conflicts() : [];

        return [
            'duplicate_tags'     => $duplicates,
            'plugin_overlaps'    => $conflicts,
            'overall_risk_level' => (!empty($duplicates) || !empty($conflicts)) ? 'attention_required' : 'optimal',
        ];
    }

    private function tool_optimize_db(Kernel $kernel): array {
        /** @var \WPSpeedCore\Optimization\DatabaseHousekeeper|null $db */
        $db = $kernel->get('db');
        if ($db) {
            $cleaned = $db->maintain();
            $optimized_tables = $db->optimize_tables();
            return [
                'success'          => true,
                'cleaned_records'  => $cleaned,
                'optimized_tables' => $optimized_tables,
            ];
        }
        return ['success' => false, 'error' => 'Database housekeeper module unavailable'];
    }

    private function tool_get_checklist(): array {
        $checklist_evaluator = new PerformanceChecklist();
        return $checklist_evaluator->evaluate();
    }

    private function tool_get_logs(Kernel $kernel, array $args): array {
        $limit = max(10, min(200, (int) ($args['limit'] ?? 50)));
        /** @var Logger|null $logger */
        $logger = $kernel->get('logger');
        $entries = $logger ? $logger->get_recent($limit) : [];
        return [
            'entries_count' => count($entries),
            'logs'          => $entries,
        ];
    }

    private function tool_sync_cloudflare(Kernel $kernel, array $args): array {
        /** @var \WPSpeedCore\Optimization\CloudflarePurger|null $cf */
        $cf = $kernel->get('cloudflare');
        if (!$cf || !$cf->is_enabled()) {
            return [
                'success' => false,
                'error'   => 'Cloudflare API sync is not configured or disabled in settings.',
            ];
        }

        $url = sanitize_text_field((string) ($args['url'] ?? ''));
        if ($url !== '') {
            $success = $cf->purge_url($url);
            return [
                'success' => $success,
                'action'  => 'single_url_purge',
                'url'     => $url,
            ];
        }

        $success = $cf->purge_all();
        return [
            'success' => $success,
            'action'  => 'purge_everything',
        ];
    }

    private function tool_migrate_settings(Kernel $kernel, array $args): array {
        /** @var MigrationManager|null $migration */
        $migration = $kernel->get('migration');
        if (!$migration) {
            return ['success' => false, 'error' => 'Migration manager module unavailable'];
        }

        $source = sanitize_key((string) ($args['source'] ?? 'detect'));
        if ($source === 'detect' || $source === '') {
            return [
                'success'   => true,
                'action'    => 'detect',
                'available' => $migration->get_available_migrations(),
            ];
        }

        return $migration->import_settings($source);
    }
}
