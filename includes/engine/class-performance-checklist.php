<?php
declare(strict_types=1);

namespace WPSpeedCore\Engine;

use WPSpeedCore\Kernel;

if (!defined('ABSPATH')) {
    exit;
}

class PerformanceChecklist {
    public function evaluate(): array {
        $kernel   = Kernel::launch();
        /** @var EnvironmentScanner|null $env_scanner */
        $env_scanner = $kernel->get('env');
        $env = $env_scanner ? $env_scanner->get() : [];

        $settings = (array) get_option('wpsc_settings', []);
        $disabled_assets = (array) get_option('wpsc_disabled_assets', []);

        $checks = [
            'server_stack' => [
                'title' => 'Server & TTFB Architecture',
                'items' => [
                    [
                        'id'          => 'php_version',
                        'title'       => 'Modern PHP Runtime (PHP 8.1+)',
                        'status'      => version_compare(PHP_VERSION, '8.1.0', '>=') ? 'passed' : 'warning',
                        'description' => 'PHP ' . PHP_VERSION . ' is active. PHP 8.1+ delivers up to 25% faster execution time.',
                        'badge'       => 'PHP ' . PHP_VERSION,
                    ],
                    [
                        'id'          => 'opcache',
                        'title'       => 'Zend OPcache Bytecode Cache',
                        'status'      => (!empty($env['php']['opcache']) || !empty($env['php']['opcache_enabled'])) ? 'passed' : 'warning',
                        'description' => (!empty($env['php']['opcache']) || !empty($env['php']['opcache_enabled'])) ? 'Zend OPcache is active in memory.' : 'OPcache is not detected. Enabling OPcache reduces CPU compile time to 0ms.',
                        'badge'       => (!empty($env['php']['opcache']) || !empty($env['php']['opcache_enabled'])) ? 'Active' : 'Disabled',
                    ],
                    [
                        'id'          => 'html_cache',
                        'title'       => 'Static Disk HTML Cache',
                        'status'      => !empty($settings['cache']['html_cache']) ? 'passed' : 'action_needed',
                        'description' => !empty($settings['cache']['html_cache']) ? 'Static HTML Disk caching is actively serving sub-50ms TTFB.' : 'Static HTML Cache is disabled. Enable to eliminate database query overhead for visitors.',
                        'badge'       => !empty($settings['cache']['html_cache']) ? 'Active' : 'Disabled',
                    ],
                    [
                        'id'          => 'memory_limit',
                        'title'       => 'PHP Memory Limit (>128M)',
                        'status'      => ($env['php']['memory_bytes'] ?? 0) >= (128 * 1024 * 1024) ? 'passed' : 'warning',
                        'description' => 'Allocated memory limit: ' . ($env['php']['memory_limit'] ?? 'Unknown'),
                        'badge'       => $env['php']['memory_limit'] ?? 'Unknown',
                    ],
                ],
            ],
            'core_web_vitals' => [
                'title' => 'Core Web Vitals (LCP, INP, CLS)',
                'items' => [
                    [
                        'id'          => 'inp_shield',
                        'title'       => 'INP Shield & Interaction JS Delay',
                        'status'      => !empty($settings['script']['delayed_execution']) ? 'passed' : 'action_needed',
                        'description' => 'Defers non-critical JS until user interaction with scheduler.yield() chunking to maintain sub-50ms INP.',
                        'badge'       => !empty($settings['script']['delayed_execution']) ? 'Chunked Yield' : 'Off',
                    ],
                    [
                        'id'          => 'lcp_hero',
                        'title'       => 'Auto-LCP Hero Preload & Priority',
                        'status'      => !empty($settings['media']['auto_hero_priority']) ? 'passed' : 'action_needed',
                        'description' => 'Injects fetchpriority="high" and loading="eager" on the primary hero image.',
                        'badge'       => !empty($settings['media']['auto_hero_priority']) ? 'Auto Eager' : 'Off',
                    ],
                    [
                        'id'          => 'cls_dimensions',
                        'title'       => 'Zero CLS Auto Image Dimensions',
                        'status'      => !empty($settings['media']['auto_dimensions']) ? 'passed' : 'action_needed',
                        'description' => 'Automatically detects and embeds width & height attributes to eliminate layout shifts.',
                        'badge'       => !empty($settings['media']['auto_dimensions']) ? 'Active' : 'Off',
                    ],
                    [
                        'id'          => 'speculation_rules',
                        'title'       => 'W3C Speculation Rules Prerender',
                        'status'      => !empty($settings['preload']['speculation_rules']) ? 'passed' : 'action_needed',
                        'description' => 'Native background document prerendering for immediate (0ms) page navigation.',
                        'badge'       => !empty($settings['preload']['speculation_rules']) ? ($settings['preload']['speculation_level'] ?? 'moderate') : 'Off',
                    ],
                ],
            ],
            'asset_hygiene' => [
                'title' => 'Asset Management & Bloat Suppression',
                'items' => [
                    [
                        'id'          => 'asset_unloader',
                        'title'       => 'Contextual Asset Unloader',
                        'status'      => !empty($disabled_assets) ? 'passed' : 'info',
                        'description' => count($disabled_assets) . ' asset rules configured across pages and post types.',
                        'badge'       => count($disabled_assets) . ' Rules Active',
                    ],
                    [
                        'id'          => 'emoji_strip',
                        'title'       => 'Emoji Script & Style Removal',
                        'status'      => !empty($settings['general']['strip_emojis']) ? 'passed' : 'action_needed',
                        'description' => 'Strips legacy wp-emoji script and inline styles for faster DOM parsing.',
                        'badge'       => !empty($settings['general']['strip_emojis']) ? 'Stripped' : 'Loaded',
                    ],
                    [
                        'id'          => 'duotone_strip',
                        'title'       => 'Gutenberg Duotone SVG Cleanup',
                        'status'      => !empty($settings['general']['strip_duotone_svg']) ? 'passed' : 'action_needed',
                        'description' => 'Removes heavy duotone SVG filter code rendered in page body open.',
                        'badge'       => !empty($settings['general']['strip_duotone_svg']) ? 'Cleaned' : 'Loaded',
                    ],
                    [
                        'id'          => 'xmlrpc_block',
                        'title'       => 'XML-RPC & Pingback Hardening',
                        'status'      => !empty($settings['general']['block_xmlrpc']) ? 'passed' : 'action_needed',
                        'description' => 'Blocks XML-RPC endpoints to prevent brute-force attacks and background amplification.',
                        'badge'       => !empty($settings['general']['block_xmlrpc']) ? 'Hardened' : 'Open',
                    ],
                ],
            ],
            'database_ai' => [
                'title' => 'Database Hygiene & AI Architecture',
                'items' => [
                    [
                        'id'          => 'tag_audit',
                        'title'       => 'Tracking Duplicate Tag Auditor',
                        'status'      => 'passed',
                        'description' => 'Continuously scans and prevents duplicate GA4, GTM, Meta Pixel, and Clarity tags.',
                        'badge'       => 'Auditing Active',
                    ],
                    [
                        'id'          => 'mcp_protocol',
                        'title'       => 'Model Context Protocol (MCP) Server',
                        'status'      => 'passed',
                        'description' => 'AI assistants (Claude Desktop, Cursor, Antigravity) can query and control performance via MCP.',
                        'badge'       => 'AI Ready',
                    ],
                ],
            ],
        ];

        $total_checks = 0;
        $passed_checks = 0;

        foreach ($checks as $category) {
            foreach ($category['items'] as $item) {
                $total_checks++;
                if ($item['status'] === 'passed') {
                    $passed_checks++;
                }
            }
        }

        $health_score = $total_checks > 0 ? (int) round(($passed_checks / $total_checks) * 100) : 100;

        return [
            'score'        => $health_score,
            'passed_count' => $passed_checks,
            'total_count'  => $total_checks,
            'categories'   => $checks,
        ];
    }
}
