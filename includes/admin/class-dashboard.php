<?php
declare(strict_types=1);

namespace WPSpeedCore\Admin;

use WPSpeedCore\Kernel;
use WPSpeedCore\Engine\PerformanceChecklist;

if (!defined('ABSPATH')) {
    exit;
}

class Dashboard {
    private array $modules;

    public function __construct(array $modules) {
        $this->modules = $modules;
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'actions']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function enqueue_scripts(string $hook): void {
        if ($hook !== 'settings_page_wp-speed-core') {
            return;
        }
        if (file_exists(WPSC_PATH . 'assets/css/pagespeed-gauge.css')) {
            wp_enqueue_style('wpsc-psi-css', WPSC_URL . 'assets/css/pagespeed-gauge.css', [], WPSC_VERSION);
        }
        if (file_exists(WPSC_PATH . 'assets/js/pagespeed-dashboard.js')) {
            wp_enqueue_script('wpsc-psi-js', WPSC_URL . 'assets/js/pagespeed-dashboard.js', [], WPSC_VERSION, true);
            wp_localize_script('wpsc-psi-js', 'wpscPsiSettings', [
                'rest_url' => rest_url('wp-speed-core/v1/pagespeed'),
                'nonce'    => wp_create_nonce('wp_rest'),
            ]);
        }
    }

    public function menu(): void {
        add_options_page(
            'WP Speed Core',
            'WP Speed Core',
            'manage_options',
            'wp-speed-core',
            [$this, 'render']
        );
    }

    public function actions(): void {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['wpsc_auto_tune']) && check_admin_referer('wpsc_autotune_nonce')) {
            $tuner = $this->modules['tuner'] ?? null;
            if ($tuner) {
                $tuner->apply();
            }
            wp_safe_redirect(add_query_arg(['page' => 'wp-speed-core', 'tab' => 'overview', 'tuned' => '1'], admin_url('options-general.php')));
            exit;
        }

        if (isset($_POST['wpsc_purge_cache']) && check_admin_referer('wpsc_purge_nonce')) {
            do_action('wpsc_purge_all');
            wp_safe_redirect(add_query_arg(['page' => 'wp-speed-core', 'tab' => 'overview', 'purged' => '1'], admin_url('options-general.php')));
            exit;
        }

        if (isset($_POST['wpsc_warm_cache']) && check_admin_referer('wpsc_warm_nonce')) {
            do_action('wpsc_warm_cache');
            wp_safe_redirect(add_query_arg(['page' => 'wp-speed-core', 'tab' => 'overview', 'warmed' => '1'], admin_url('options-general.php')));
            exit;
        }

        if ((isset($_POST['wpsc_db_clean']) || isset($_POST['wpsc_run_db_clean'])) && check_admin_referer('wpsc_db_clean_nonce')) {
            $db_cleaner = $this->modules['db_cleaner'] ?? null;
            if ($db_cleaner && method_exists($db_cleaner, 'run_cleanup')) {
                $db_cleaner->run_cleanup();
            } else {
                $db = $this->modules['db'] ?? null;
                if ($db) {
                    $db->maintain();
                }
            }
            wp_safe_redirect(add_query_arg(['page' => 'wp-speed-core', 'tab' => 'settings', 'db_cleaned' => '1'], admin_url('options-general.php')));
            exit;
        }

        if (isset($_POST['wpsc_save_cdn']) && check_admin_referer('wpsc_cdn_nonce')) {
            $curr = (array) get_option('wpsc_settings', []);
            $curr['cdn']['enable_cdn'] = !empty($_POST['wpsc_enable_cdn']) ? 1 : 0;
            $curr['cdn']['cdn_url']    = esc_url_raw($_POST['wpsc_cdn_url'] ?? '');
            update_option('wpsc_settings', $curr);
            wp_safe_redirect(add_query_arg(['page' => 'wp-speed-core', 'tab' => 'settings', 'cdn_saved' => '1'], admin_url('options-general.php')));
            exit;
        }

        if (isset($_POST['wpsc_save_cache_exclusions']) && check_admin_referer('wpsc_exclusions_nonce')) {
            $curr = (array) get_option('wpsc_settings', []);
            $curr['cache']['cache_exclusions'] = sanitize_textarea_field($_POST['wpsc_cache_exclusions'] ?? '');
            update_option('wpsc_settings', $curr);
            wp_safe_redirect(add_query_arg(['page' => 'wp-speed-core', 'tab' => 'settings', 'exclusions_saved' => '1'], admin_url('options-general.php')));
            exit;
        }

        if (isset($_POST['wpsc_save_psi_key']) && check_admin_referer('wpsc_psi_key_nonce')) {
            $curr = (array) get_option('wpsc_settings', []);
            $curr['pagespeed']['api_key'] = sanitize_text_field($_POST['wpsc_psi_api_key'] ?? '');
            update_option('wpsc_settings', $curr);
            wp_safe_redirect(add_query_arg(['page' => 'wp-speed-core', 'tab' => 'settings', 'psi_key_saved' => '1'], admin_url('options-general.php')));
            exit;
        }

        if (isset($_POST['wpsc_generate_mcp_token']) && check_admin_referer('wpsc_mcp_token_nonce')) {
            $new_token = 'wpsc_' . bin2hex(random_bytes(24));
            update_option('wpsc_mcp_token', $new_token);
            wp_safe_redirect(add_query_arg(['page' => 'wp-speed-core', 'tab' => 'mcp', 'token_generated' => '1'], admin_url('options-general.php')));
            exit;
        }

        if (isset($_GET['wpsc_purge_url']) && check_admin_referer('wpsc_purge_single_url')) {
            $url_to_purge = esc_url_raw(urldecode((string) $_GET['wpsc_purge_url']));
            if ($url_to_purge) {
                $p = wp_parse_url($url_to_purge);
                $home_host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
                $target_host = $p['host'] ?? $home_host;

                if (!empty($home_host) && strcasecmp($target_host, $home_host) === 0) {
                    $file = WPSC_CACHE_DIR . 'html/' . md5($target_host . ($p['path'] ?? '/')) . '.html';
                    if (file_exists($file)) {
                        wp_delete_file($file);
                        if (file_exists($file . '.gz')) {
                            wp_delete_file($file . '.gz');
                        }
                    }
                }
            }
            wp_safe_redirect(add_query_arg(['page' => 'wp-speed-core', 'tab' => 'overview', 'single_purged' => '1'], admin_url('options-general.php')));
            exit;
        }

        if (isset($_POST['wpsc_clear_logs']) && check_admin_referer('wpsc_clear_logs_nonce')) {
            $logger = $this->modules['logger'] ?? null;
            if ($logger) {
                $logger->clear();
            }
            wp_safe_redirect(add_query_arg(['page' => 'wp-speed-core', 'tab' => 'logs', 'log_cleared' => '1'], admin_url('options-general.php')));
            exit;
        }
    }

    public function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Akses ditolak.', 'wp-speed-core'));
        }

        $active_tab = sanitize_key($_GET['tab'] ?? 'overview');
        $valid_tabs = ['overview', 'checklist', 'comparison', 'mcp', 'settings', 'logs'];
        if (!in_array($active_tab, $valid_tabs, true)) {
            $active_tab = 'overview';
        }

        $env_scanner = $this->modules['env'] ?? null;
        $env_data    = $env_scanner ? $env_scanner->get() : [];
        $settings    = (array) get_option('wpsc_settings', []);
        $logger      = $this->modules['logger'] ?? null;
        $logs        = $logger ? $logger->get_recent(50) : [];
        $auditor     = $this->modules['auditor'] ?? null;
        $duplicates  = $auditor ? $auditor->get_duplicates() : [];
        $arbiter     = $this->modules['arbiter'] ?? null;
        $conflicts   = $arbiter ? $arbiter->get_conflicts() : [];
        $mcp_token   = (string) get_option('wpsc_mcp_token', '');

        $checklist_evaluator = new PerformanceChecklist();
        $checklist_data      = $checklist_evaluator->evaluate();

        $cache_dir   = WPSC_CACHE_DIR . 'html/';
        $cache_files = glob($cache_dir . '*.html') ?: [];
        $cache_size  = 0;
        foreach ($cache_files as $f) {
            $cache_size += (int) filesize($f);
        }
        $cache_size_fmt = $cache_size > 1048576 ? round($cache_size / 1048576, 2) . ' MB' : round($cache_size / 1024, 1) . ' KB';
        $disabled_assets = (array) get_option('wpsc_disabled_assets', []);
        ?>
        <style>
            :root {
                --wpsc-bg-base: #070a13;
                --wpsc-bg-surface: #0f172a;
                --wpsc-bg-card: rgba(15, 23, 42, 0.75);
                --wpsc-border-subtle: rgba(255, 255, 255, 0.08);
                --wpsc-glow-cyan: rgba(0, 242, 254, 0.35);
                --wpsc-accent-cyan: #38bdf8;
                --wpsc-accent-teal: #2dd4bf;
                --wpsc-text-primary: #f8fafc;
                --wpsc-text-secondary: #94a3b8;
                --wpsc-text-muted: #64748b;
                --wpsc-success: #10b981;
                --wpsc-warning: #f59e0b;
                --wpsc-danger: #ef4444;
            }

            .wpsc-wrap {
                max-width: 1320px;
                margin: 20px 20px 40px 0;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                color: var(--wpsc-text-primary);
                box-sizing: border-box;
            }

            .wpsc-wrap * {
                box-sizing: border-box;
            }

            .wpsc-glass {
                background: var(--wpsc-bg-card);
                backdrop-filter: blur(16px);
                -webkit-backdrop-filter: blur(16px);
                border: 1px solid var(--wpsc-border-subtle);
                border-radius: 16px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.1);
            }

            .wpsc-glass-metallic {
                background: linear-gradient(135deg, rgba(30, 41, 59, 0.7) 0%, rgba(15, 23, 42, 0.85) 100%);
                border: 1px solid rgba(255, 255, 255, 0.12);
                border-radius: 16px;
                box-shadow: 0 12px 40px rgba(0, 0, 0, 0.5), inset 0 1px 1px rgba(255, 255, 255, 0.15), 0 0 20px rgba(56, 189, 248, 0.1);
            }

            .wpsc-header {
                padding: 24px 30px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 20px;
                margin-bottom: 24px;
                position: relative;
                overflow: hidden;
            }

            .wpsc-header::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 2px;
                background: linear-gradient(90deg, transparent, #38bdf8, #818cf8, transparent);
            }

            .wpsc-brand {
                display: flex;
                align-items: center;
                gap: 16px;
            }

            .wpsc-brand-icon {
                width: 46px;
                height: 46px;
                border-radius: 12px;
                background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
                border: 1px solid rgba(56, 189, 248, 0.4);
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 0 25px rgba(56, 189, 248, 0.3);
            }

            .wpsc-title-group h1 {
                font-size: 22px;
                font-weight: 800;
                letter-spacing: -0.5px;
                margin: 0;
                background: linear-gradient(135deg, #ffffff 0%, #cbd5e1 50%, #94a3b8 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .wpsc-version-badge {
                font-size: 11px;
                font-weight: 700;
                padding: 2px 8px;
                border-radius: 6px;
                background: rgba(56, 189, 248, 0.15);
                border: 1px solid rgba(56, 189, 248, 0.35);
                color: #38bdf8;
                letter-spacing: 0.5px;
                -webkit-text-fill-color: #38bdf8;
            }

            .wpsc-subtitle {
                font-size: 12px;
                color: var(--wpsc-text-muted);
                margin-top: 4px;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .wpsc-status-radar {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                color: #34d399;
                font-weight: 600;
            }

            .wpsc-radar-light {
                width: 6px;
                height: 6px;
                border-radius: 50%;
                background: #10b981;
                box-shadow: 0 0 8px #10b981;
            }

            .wpsc-tabs-bar {
                display: flex;
                gap: 6px;
                padding: 6px;
                background: rgba(15, 23, 42, 0.8);
                border: 1px solid rgba(255, 255, 255, 0.08);
                border-radius: 12px;
                margin-bottom: 24px;
                overflow-x: auto;
            }

            .wpsc-tab-link {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 10px 18px;
                font-size: 13px;
                font-weight: 600;
                color: var(--wpsc-text-secondary);
                text-decoration: none;
                border-radius: 8px;
                transition: all 0.2s ease;
                border: 1px solid transparent;
                white-space: nowrap;
            }

            .wpsc-tab-link:hover {
                color: #fff;
                background: rgba(255, 255, 255, 0.04);
            }

            .wpsc-tab-link.is-active {
                color: #fff;
                background: linear-gradient(135deg, rgba(30, 41, 59, 0.9) 0%, rgba(15, 23, 42, 0.9) 100%);
                border: 1px solid rgba(56, 189, 248, 0.4);
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3), 0 0 12px rgba(56, 189, 248, 0.15);
            }

            .wpsc-btn-primary {
                background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);
                color: #fff !important;
                font-weight: 700;
                font-size: 13px;
                padding: 10px 20px;
                border-radius: 10px;
                border: 1px solid rgba(255, 255, 255, 0.2);
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                box-shadow: 0 4px 15px rgba(14, 165, 233, 0.35);
                transition: all 0.2s ease;
                text-decoration: none;
            }

            .wpsc-btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 22px rgba(14, 165, 233, 0.55);
            }

            .wpsc-btn-metallic {
                background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
                color: #e2e8f0 !important;
                font-weight: 600;
                font-size: 13px;
                padding: 9px 16px;
                border-radius: 10px;
                border: 1px solid rgba(255, 255, 255, 0.12);
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                transition: all 0.2s ease;
                text-decoration: none;
            }

            .wpsc-btn-metallic:hover {
                background: linear-gradient(135deg, #334155 0%, #1e293b 100%);
                color: #fff !important;
                border-color: rgba(56, 189, 248, 0.35);
                box-shadow: 0 0 15px rgba(56, 189, 248, 0.15);
            }

            .wpsc-metrics-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 16px;
                margin-bottom: 24px;
            }

            .wpsc-metric-card {
                padding: 20px;
                position: relative;
            }

            .wpsc-metric-label {
                font-size: 12px;
                font-weight: 600;
                color: var(--wpsc-text-muted);
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 8px;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }

            .wpsc-metric-value {
                font-size: 24px;
                font-weight: 800;
                letter-spacing: -0.5px;
                color: #fff;
            }

            .wpsc-metric-sub {
                font-size: 11px;
                color: var(--wpsc-text-secondary);
                margin-top: 6px;
            }

            .wpsc-score-wrap {
                display: flex;
                align-items: center;
                gap: 20px;
                padding: 24px;
                margin-bottom: 24px;
            }

            .wpsc-score-dial {
                width: 90px;
                height: 90px;
                border-radius: 50%;
                background: conic-gradient(#10b981 <?php echo esc_attr((string) $checklist_data['score']); ?>%, rgba(255,255,255,0.06) 0);
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 0 25px rgba(16, 185, 129, 0.25);
                position: relative;
            }

            .wpsc-score-inner {
                width: 74px;
                height: 74px;
                border-radius: 50%;
                background: #0f172a;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
            }

            .wpsc-score-number {
                font-size: 24px;
                font-weight: 800;
                color: #34d399;
                line-height: 1;
            }

            .wpsc-score-tag {
                font-size: 9px;
                color: var(--wpsc-text-muted);
                font-weight: 700;
                text-transform: uppercase;
            }

            .wpsc-table {
                width: 100%;
                border-collapse: separate;
                border-spacing: 0;
            }

            .wpsc-table th {
                background: rgba(30, 41, 59, 0.6);
                padding: 14px 18px;
                font-size: 12px;
                font-weight: 700;
                color: var(--wpsc-text-secondary);
                text-align: left;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            }

            .wpsc-table td {
                padding: 16px 18px;
                font-size: 13px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.05);
                color: var(--wpsc-text-primary);
                vertical-align: middle;
            }

            .wpsc-table tr:last-child td {
                border-bottom: none;
            }

            .wpsc-table tr:hover td {
                background: rgba(255, 255, 255, 0.02);
            }

            .wpsc-badge {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                padding: 4px 10px;
                border-radius: 6px;
                font-size: 11px;
                font-weight: 700;
                letter-spacing: 0.3px;
            }

            .wpsc-badge-green {
                background: rgba(16, 185, 129, 0.15);
                border: 1px solid rgba(16, 185, 129, 0.35);
                color: #34d399;
            }

            .wpsc-badge-amber {
                background: rgba(245, 158, 11, 0.15);
                border: 1px solid rgba(245, 158, 11, 0.35);
                color: #fbbf24;
            }

            .wpsc-badge-cyan {
                background: rgba(56, 189, 248, 0.15);
                border: 1px solid rgba(56, 189, 248, 0.35);
                color: #38bdf8;
            }

            .wpsc-badge-slate {
                background: rgba(148, 163, 184, 0.12);
                border: 1px solid rgba(148, 163, 184, 0.25);
                color: #cbd5e1;
            }

            .wpsc-terminal {
                border-radius: 14px;
                overflow: hidden;
                background: #070b14;
                border: 1px solid rgba(255, 255, 255, 0.08);
            }

            .wpsc-terminal-header {
                background: #0f172a;
                padding: 12px 18px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            }

            .wpsc-terminal-dots {
                display: flex;
                gap: 6px;
            }

            .wpsc-terminal-dot {
                width: 10px;
                height: 10px;
                border-radius: 50%;
            }

            .wpsc-terminal-body {
                padding: 18px;
                margin: 0;
                font-family: "JetBrains Mono", Consolas, Monaco, monospace;
                font-size: 12px;
                color: #cbd5e1;
                line-height: 1.6;
                max-height: 380px;
                overflow-y: auto;
                background: #050811;
                white-space: pre-wrap;
            }

            .wpsc-code-box {
                background: #050811;
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 10px;
                padding: 14px 18px;
                font-family: monospace;
                font-size: 12px;
                color: #38bdf8;
                position: relative;
                margin: 12px 0;
                overflow-x: auto;
            }
        </style>

        <div class="wpsc-wrap">
            <!-- Header HUD -->
            <div class="wpsc-glass-metallic wpsc-header">
                <div class="wpsc-brand">
                    <div class="wpsc-brand-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                        </svg>
                    </div>
                    <div class="wpsc-title-group">
                        <h1>WP Speed Core <span class="wpsc-version-badge">v<?php echo esc_html(WPSC_VERSION); ?></span></h1>
                        <div class="wpsc-subtitle">
                            <span class="wpsc-status-radar"><span class="wpsc-radar-light"></span> High-Velocity Engine</span>
                            <span>&bull; Server: <?php echo esc_html($env_data['server']['software'] ?? 'PHP Server'); ?></span>
                            <span>&bull; Author: <a href="https://t.me/leddhany" target="_blank" style="color: #38bdf8; text-decoration: none;">Dhany (@leddhany)</a></span>
                        </div>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <form method="post" style="margin: 0;">
                        <?php wp_nonce_field('wpsc_purge_nonce'); ?>
                        <button type="submit" name="wpsc_purge_cache" class="wpsc-btn-metallic">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                            Purge Cache
                        </button>
                    </form>
                    <form method="post" style="margin: 0;">
                        <?php wp_nonce_field('wpsc_warm_nonce'); ?>
                        <button type="submit" name="wpsc_warm_cache" class="wpsc-btn-metallic">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"></path></svg>
                            Warm Cache
                        </button>
                    </form>
                    <form method="post" style="margin: 0;">
                        <?php wp_nonce_field('wpsc_autotune_nonce'); ?>
                        <button type="submit" name="wpsc_auto_tune" class="wpsc-btn-primary">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"></path><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"></path><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"></path><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"></path></svg>
                            1-Click Auto-Tune
                        </button>
                    </form>
                </div>
            </div>

            <!-- Navigation Tabs Bar -->
            <div class="wpsc-tabs-bar">
                <a href="<?php echo esc_url(add_query_arg(['page' => 'wp-speed-core', 'tab' => 'overview'], admin_url('options-general.php'))); ?>" class="wpsc-tab-link <?php echo $active_tab === 'overview' ? 'is-active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="7" height="9" x="3" y="3" rx="1"></rect><rect width="7" height="5" x="14" y="3" rx="1"></rect><rect width="7" height="9" x="14" y="12" rx="1"></rect><rect width="7" height="5" x="3" y="16" rx="1"></rect></svg>
                    Telemetry & HUD
                </a>
                <a href="<?php echo esc_url(add_query_arg(['page' => 'wp-speed-core', 'tab' => 'checklist'], admin_url('options-general.php'))); ?>" class="wpsc-tab-link <?php echo $active_tab === 'checklist' ? 'is-active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                    Performance Checklist
                </a>
                <a href="<?php echo esc_url(add_query_arg(['page' => 'wp-speed-core', 'tab' => 'comparison'], admin_url('options-general.php'))); ?>" class="wpsc-tab-link <?php echo $active_tab === 'comparison' ? 'is-active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m18 8 4 4-4 4"></path><path d="M2 12h20"></path><path d="m6 16-4-4 4-4"></path></svg>
                    Competitor Matrix
                </a>
                <a href="<?php echo esc_url(add_query_arg(['page' => 'wp-speed-core', 'tab' => 'mcp'], admin_url('options-general.php'))); ?>" class="wpsc-tab-link <?php echo $active_tab === 'mcp' ? 'is-active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8V4H8"></path><rect width="16" height="12" x="4" y="8" rx="2"></rect><path d="M2 14h2"></path><path d="M20 14h2"></path><path d="M15 13v2"></path><path d="M9 13v2"></path></svg>
                    AI & MCP Protocol
                </a>
                <a href="<?php echo esc_url(admin_url('options-general.php?page=wpsc-asset-manager')); ?>" class="wpsc-tab-link">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
                    Asset Unloader
                </a>
                <a href="<?php echo esc_url(add_query_arg(['page' => 'wp-speed-core', 'tab' => 'settings'], admin_url('options-general.php'))); ?>" class="wpsc-tab-link <?php echo $active_tab === 'settings' ? 'is-active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    Settings & CDN
                </a>
                <a href="<?php echo esc_url(add_query_arg(['page' => 'wp-speed-core', 'tab' => 'logs'], admin_url('options-general.php'))); ?>" class="wpsc-tab-link <?php echo $active_tab === 'logs' ? 'is-active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 17 10 11 4 5"></polyline><line x1="12" x2="20" y1="19" y2="19"></line></svg>
                    Diagnostics & Logs
                </a>
            </div>

            <!-- TAB 1: OVERVIEW -->
            <?php if ($active_tab === 'overview'): ?>
                <div class="wpsc-metrics-grid">
                    <div class="wpsc-glass wpsc-metric-card">
                        <div class="wpsc-metric-label">
                            <span>Compliance Score</span>
                            <span class="wpsc-badge wpsc-badge-green"><?php echo esc_html((string) $checklist_data['score']); ?>%</span>
                        </div>
                        <div class="wpsc-metric-value"><?php echo esc_html((string) $checklist_data['passed_count']); ?> / <?php echo esc_html((string) $checklist_data['total_count']); ?></div>
                        <div class="wpsc-metric-sub">Optimization criteria passed</div>
                    </div>

                    <div class="wpsc-glass wpsc-metric-card">
                        <div class="wpsc-metric-label">
                            <span>Disk HTML Cache</span>
                            <span class="wpsc-badge <?php echo !empty($settings['cache']['html_cache']) ? 'wpsc-badge-cyan' : 'wpsc-badge-slate'; ?>">
                                <?php echo !empty($settings['cache']['html_cache']) ? 'Active' : 'Disabled'; ?>
                            </span>
                        </div>
                        <div class="wpsc-metric-value"><?php echo count($cache_files); ?> <span style="font-size: 14px; font-weight: 500; color: #94a3b8;">pages</span></div>
                        <div class="wpsc-metric-sub">Total cache size: <?php echo esc_html($cache_size_fmt); ?></div>
                    </div>

                    <div class="wpsc-glass wpsc-metric-card">
                        <div class="wpsc-metric-label">
                            <span>INP Yielding</span>
                            <span class="wpsc-badge wpsc-badge-green">Sub-50ms</span>
                        </div>
                        <div class="wpsc-metric-value"><?php echo !empty($settings['script']['delayed_execution']) ? 'Protected' : 'Off'; ?></div>
                        <div class="wpsc-metric-sub">scheduler.yield() main thread shield</div>
                    </div>

                    <div class="wpsc-glass wpsc-metric-card">
                        <div class="wpsc-metric-label">
                            <span>Asset Rules</span>
                            <span class="wpsc-badge wpsc-badge-slate"><?php echo count($disabled_assets); ?> Handles</span>
                        </div>
                        <div class="wpsc-metric-value"><?php echo count($disabled_assets); ?> <span style="font-size: 14px; font-weight: 500; color: #94a3b8;">active</span></div>
                        <div class="wpsc-metric-sub">Contextual CSS/JS disablers</div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
                    <!-- Server Telemetry Card -->
                    <div class="wpsc-glass" style="padding: 24px;">
                        <h3 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 700; color: #f1f5f9; display: flex; align-items: center; gap: 8px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="2"><rect width="20" height="8" x="2" y="2" rx="2" ry="2"></rect><rect width="20" height="8" x="2" y="14" rx="2" ry="2"></rect><line x1="6" x2="6.01" y1="6" y2="6"></line><line x1="6" x2="6.01" y1="18" y2="18"></line></svg>
                            Hosting & Runtime Architecture
                        </h3>
                        <table style="width: 100%; font-size: 13px; border-collapse: collapse;">
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.06);">
                                <td style="padding: 10px 0; color: #94a3b8;">PHP Version</td>
                                <td style="padding: 10px 0; text-align: right; font-weight: 700; color: #fff;">PHP <?php echo PHP_VERSION; ?></td>
                            </tr>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.06);">
                                <td style="padding: 10px 0; color: #94a3b8;">Zend OPcache</td>
                                <td style="padding: 10px 0; text-align: right;">
                                    <span class="wpsc-badge <?php echo !empty($env_data['php']['opcache_enabled']) ? 'wpsc-badge-green' : 'wpsc-badge-amber'; ?>">
                                        <?php echo !empty($env_data['php']['opcache_enabled']) ? 'Enabled' : 'Not Detected'; ?>
                                    </span>
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.06);">
                                <td style="padding: 10px 0; color: #94a3b8;">Memory Limit</td>
                                <td style="padding: 10px 0; text-align: right; font-weight: 700; color: #fff;"><?php echo esc_html($env_data['php']['memory_limit'] ?? 'Unknown'); ?></td>
                            </tr>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.06);">
                                <td style="padding: 10px 0; color: #94a3b8;">Speculation Rules</td>
                                <td style="padding: 10px 0; text-align: right;">
                                    <span class="wpsc-badge wpsc-badge-cyan"><?php echo esc_html($settings['preload']['speculation_level'] ?? 'Moderate'); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 10px 0; color: #94a3b8;">Instant Bypass Header</td>
                                <td style="padding: 10px 0; text-align: right; font-family: monospace; color: #38bdf8;">?nowpsc=1</td>
                            </tr>
                        </table>
                    </div>

                    <!-- Conflict & Auditor Intelligence -->
                    <div class="wpsc-glass" style="padding: 24px;">
                        <h3 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 700; color: #f1f5f9; display: flex; align-items: center; gap: 8px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2dd4bf" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                            Diagnostic Arbiter & Tag Auditor
                        </h3>
                        <?php if (!empty($duplicates)): ?>
                            <div style="background: rgba(245,158,11,0.12); border: 1px solid rgba(245,158,11,0.3); border-radius: 10px; padding: 12px 16px; margin-bottom: 12px; font-size: 13px;">
                                <strong style="color: #fbbf24;">Duplicate Tracking Tags Detected:</strong><br>
                                <span style="color: #fde68a;"><?php echo esc_html(implode(', ', $duplicates)); ?> terpasang lebih dari satu kali.</span>
                            </div>
                        <?php else: ?>
                            <div style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.25); border-radius: 10px; padding: 12px 16px; margin-bottom: 12px; font-size: 13px; color: #a7f3d0;">
                                Tracking tags (GA4, GTM, Meta Pixel, Clarity) are clean with zero detected duplications.
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($conflicts)): ?>
                            <div style="background: rgba(56,189,248,0.1); border: 1px solid rgba(56,189,248,0.25); border-radius: 10px; padding: 12px 16px; font-size: 13px; color: #bfdbfe;">
                                <strong style="color: #38bdf8;">Overlap Advisory:</strong> <?php echo esc_html(implode(', ', $conflicts)); ?>
                            </div>
                        <?php else: ?>
                            <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; padding: 12px 16px; font-size: 13px; color: #94a3b8;">
                                Zero cache or optimization collisions detected with active third-party plugins.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB 2: PERFORMANCE CHECKLIST -->
            <?php if ($active_tab === 'checklist'): ?>
                <div class="wpsc-glass wpsc-score-wrap">
                    <div class="wpsc-score-dial">
                        <div class="wpsc-score-inner">
                            <span class="wpsc-score-number"><?php echo esc_html((string) $checklist_data['score']); ?>%</span>
                            <span class="wpsc-score-tag">Health</span>
                        </div>
                    </div>
                    <div>
                        <h2 style="font-size: 20px; font-weight: 800; margin: 0 0 6px 0; color: #fff;">WordPress Performance Checklist & Scorecard</h2>
                        <p style="margin: 0; font-size: 13px; color: #94a3b8; max-width: 800px;">
                            Standar kepatuhan kecepatan web modern terinspirasi dari panduan Core Web Vitals (LCP, INP, CLS) dan checklist performa industri. Seluruh kriteria di bawah diverifikasi secara real-time.
                        </p>
                    </div>
                </div>

                <?php foreach ($checklist_data['categories'] as $cat_key => $category): ?>
                    <div class="wpsc-glass" style="margin-bottom: 24px; overflow: hidden;">
                        <div style="padding: 16px 20px; background: rgba(30, 41, 59, 0.4); border-bottom: 1px solid rgba(255,255,255,0.06);">
                            <h3 style="margin: 0; font-size: 14px; font-weight: 700; color: #38bdf8; text-transform: uppercase; letter-spacing: 0.5px;">
                                <?php echo esc_html($category['title']); ?>
                            </h3>
                        </div>
                        <table class="wpsc-table">
                            <tbody>
                                <?php foreach ($category['items'] as $item): ?>
                                    <tr>
                                        <td style="width: 40px; text-align: center;">
                                            <?php if ($item['status'] === 'passed'): ?>
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                            <?php elseif ($item['status'] === 'warning'): ?>
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2.5"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" x2="12" y1="9" y2="13"></line><line x1="12" x2="12.01" y1="17" y2="17"></line></svg>
                                            <?php else: ?>
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" x2="12" y1="8" y2="12"></line><line x1="12" x2="12.01" y1="16" y2="16"></line></svg>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong style="color: #f1f5f9;"><?php echo esc_html($item['title']); ?></strong>
                                            <div style="font-size: 12px; color: #94a3b8; margin-top: 3px;"><?php echo esc_html($item['description']); ?></div>
                                        </td>
                                        <td style="text-align: right; width: 140px;">
                                            <span class="wpsc-badge <?php echo $item['status'] === 'passed' ? 'wpsc-badge-green' : ($item['status'] === 'warning' ? 'wpsc-badge-amber' : 'wpsc-badge-cyan'); ?>">
                                                <?php echo esc_html($item['badge']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- TAB 3: COMPETITOR COMPARISON MATRIX -->
            <?php if ($active_tab === 'comparison'): ?>
                <div class="wpsc-glass" style="padding: 24px; margin-bottom: 24px;">
                    <h2 style="font-size: 20px; font-weight: 800; margin: 0 0 8px 0; color: #fff;">Architectural Benchmark & Competitor Comparison</h2>
                    <p style="margin: 0; font-size: 13px; color: #94a3b8;">
                        Komparasi kapabilitas teknis antara **WP Speed Core** dengan plugin performa terkemuka lainnya di industri (WP Rocket, Perfmatters, NitroPack, WP Shifty, FlyingPress).
                    </p>
                </div>

                <div class="wpsc-glass" style="overflow: hidden; margin-bottom: 24px; overflow-x: auto;">
                    <table class="wpsc-table">
                        <thead>
                            <tr>
                                <th style="width: 250px;">Fitur & Kapabilitas Inti</th>
                                <th style="color: #38bdf8; background: rgba(56,189,248,0.1);">WP Speed Core</th>
                                <th>FlyingPress</th>
                                <th>WP Rocket</th>
                                <th>Perfmatters</th>
                                <th>WP Shifty</th>
                                <th>NitroPack</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Static Disk HTML Cache with Gzip</strong></td>
                                <td style="color: #34d399; font-weight: 700; background: rgba(56,189,248,0.05);">Yes (Zero Query)</td>
                                <td>Yes (Disk Cache)</td>
                                <td>Yes</td>
                                <td>No (Third-party)</td>
                                <td>No (Third-party)</td>
                                <td>Proprietary Cloud</td>
                            </tr>
                            <tr>
                                <td><strong>INP Shield (scheduler.yield Chunking)</strong></td>
                                <td style="color: #34d399; font-weight: 700; background: rgba(56,189,248,0.05);">Yes (Sub-50ms)</td>
                                <td>Delay JS</td>
                                <td>Basic Delay</td>
                                <td>Basic Delay</td>
                                <td>Basic Delay</td>
                                <td>Proprietary JS</td>
                            </tr>
                            <tr>
                                <td><strong>CrUX & Ad Query Normalizer (gad_source, gclid, gbraid)</strong></td>
                                <td style="color: #34d399; font-weight: 700; background: rgba(56,189,248,0.05);">Yes (Zero Cache Fragmentation)</td>
                                <td>Yes</td>
                                <td>Yes (Basic)</td>
                                <td>No (Third-party)</td>
                                <td>No (Third-party)</td>
                                <td>Cloud Proxy</td>
                            </tr>
                            <tr>
                                <td><strong>W3C Speculation Rules (Instant Prerender)</strong></td>
                                <td style="color: #34d399; font-weight: 700; background: rgba(56,189,248,0.05);">Yes (Native W3C)</td>
                                <td>Link Preload (JS)</td>
                                <td>Instant Page (JS)</td>
                                <td>Instant Page (JS)</td>
                                <td>No</td>
                                <td>No</td>
                            </tr>
                            <tr>
                                <td><strong>Smart Contextual Asset Unloader</strong></td>
                                <td style="color: #34d399; font-weight: 700; background: rgba(56,189,248,0.05);">Yes (with Exceptions)</td>
                                <td>Unused CSS</td>
                                <td>Unused CSS only</td>
                                <td>Script Manager</td>
                                <td>Scenarios Engine</td>
                                <td>Automated Blackbox</td>
                            </tr>
                            <tr>
                                <td><strong>Model Context Protocol (MCP) Server</strong></td>
                                <td style="color: #34d399; font-weight: 700; background: rgba(56,189,248,0.05);">Yes (AI-Native)</td>
                                <td>No</td>
                                <td>Yes (v3.23+)</td>
                                <td>No</td>
                                <td>No</td>
                                <td>No</td>
                            </tr>
                            <tr>
                                <td><strong>1-Click Adaptive Auto-Tune</strong></td>
                                <td style="color: #34d399; font-weight: 700; background: rgba(56,189,248,0.05);">Yes (Heuristic)</td>
                                <td>Preset Configs</td>
                                <td>Manual Setup</td>
                                <td>Manual Setup</td>
                                <td>Manual Setup</td>
                                <td>Cloud Preset</td>
                            </tr>
                            <tr>
                                <td><strong>Duplicate Tag Auditor (GA4/GTM/Pixel)</strong></td>
                                <td style="color: #34d399; font-weight: 700; background: rgba(56,189,248,0.05);">Yes (Built-in)</td>
                                <td>No</td>
                                <td>No</td>
                                <td>No</td>
                                <td>No</td>
                                <td>No</td>
                            </tr>
                            <tr>
                                <td><strong>Instant Bypass URL Parameter</strong></td>
                                <td style="color: #34d399; font-weight: 700; background: rgba(56,189,248,0.05);">?nowpsc=1</td>
                                <td>?flying_press_bypass</td>
                                <td>?nowprocket</td>
                                <td>?nowp</td>
                                <td>?noshifty</td>
                                <td>Bypass Cookie</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- TAB 4: AI & MCP PROTOCOL -->
            <?php if ($active_tab === 'mcp'): ?>
                <div class="wpsc-glass" style="padding: 24px; margin-bottom: 24px;">
                    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 20px;">
                        <div>
                            <h2 style="font-size: 20px; font-weight: 800; margin: 0 0 6px 0; color: #fff; display: flex; align-items: center; gap: 10px;">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="2"><path d="M12 8V4H8"></path><rect width="16" height="12" x="4" y="8" rx="2"></rect><path d="M2 14h2"></path><path d="M20 14h2"></path><path d="M15 13v2"></path><path d="M9 13v2"></path></svg>
                                Model Context Protocol (MCP) AI Server
                            </h2>
                            <p style="margin: 0; font-size: 13px; color: #94a3b8; max-width: 800px;">
                                Menghubungkan website Anda secara langsung dengan asisten AI (Claude Desktop, Cursor IDE, Antigravity, ChatGPT) melalui protokol standar industri MCP untuk diagnosis otomatis, eksekusi pembersihan cache, audit database, dan tuning performa dengan bahasa alami.
                            </p>
                        </div>
                        <form method="post" style="margin: 0;">
                            <?php wp_nonce_field('wpsc_mcp_token_nonce'); ?>
                            <button type="submit" name="wpsc_generate_mcp_token" class="wpsc-btn-primary">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2l-2 2m-1.5 1.5L16 7l-1.5-1.5L16 4l-2-2"></path><path d="M15.5 8.5L20 4"></path><path d="M7 15a5 5 0 1 1 7-7l5 5a2 2 0 0 1 0 3l-2 2a2 2 0 0 1-3 0l-5-5"></path></svg>
                                <?php echo $mcp_token ? 'Regenerate API Token' : 'Generate Secret Token'; ?>
                            </button>
                        </form>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
                    <!-- MCP Endpoint & Auth Details -->
                    <div class="wpsc-glass" style="padding: 24px;">
                        <h3 style="margin: 0 0 14px 0; font-size: 14px; font-weight: 700; color: #f1f5f9;">Konektivitas & Kredensial MCP</h3>
                        <div style="font-size: 12px; color: #94a3b8; margin-bottom: 6px;">Endpoint REST / JSON-RPC:</div>
                        <div class="wpsc-code-box"><?php echo esc_url(rest_url('wpsc/v1/mcp')); ?></div>

                        <div style="font-size: 12px; color: #94a3b8; margin: 12px 0 6px 0;">Secret Token / Bearer Key:</div>
                        <div class="wpsc-code-box"><?php echo $mcp_token ? esc_html($mcp_token) : '<em style="color:#94a3b8;">Klik "Generate Secret Token" di atas</em>'; ?></div>

                        <div style="font-size: 12px; color: #64748b; margin-top: 10px;">
                            * Autentikasi juga mendukung standar <em>WordPress Application Passwords</em> bawaan untuk akun Administrator.
                        </div>
                    </div>

                    <!-- Config Snippet for Claude Desktop & Cursor -->
                    <div class="wpsc-glass" style="padding: 24px;">
                        <h3 style="margin: 0 0 14px 0; font-size: 14px; font-weight: 700; color: #f1f5f9;">Konfigurasi AI Client (claude_desktop_config.json)</h3>
                        <div class="wpsc-code-box" style="font-size: 11px; max-height: 180px;">
{
  "mcpServers": {
    "wp-speed-core": {
      "command": "npx",
      "args": [
        "-y",
        "@modelcontextprotocol/server-fetch",
        "<?php echo esc_url(rest_url('wpsc/v1/mcp')); ?>"
      ],
      "env": {
        "X-WPSC-MCP-TOKEN": "<?php echo esc_html($mcp_token ?: 'YOUR_TOKEN_HERE'); ?>"
      }
    }
  }
}
                        </div>
                    </div>
                </div>

                <!-- Available Tools List -->
                <div class="wpsc-glass" style="overflow: hidden; margin-bottom: 24px;">
                    <div style="padding: 16px 20px; background: rgba(30, 41, 59, 0.4); border-bottom: 1px solid rgba(255,255,255,0.06);">
                        <h3 style="margin: 0; font-size: 14px; font-weight: 700; color: #38bdf8; text-transform: uppercase;">Daftar Kemampuan Tool AI (MCP Capability Schemas)</h3>
                    </div>
                    <table class="wpsc-table">
                        <thead>
                            <tr>
                                <th style="width: 220px;">Nama Tool MCP</th>
                                <th>Deskripsi Fungsi</th>
                                <th style="width: 120px; text-align: right;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code style="color: #38bdf8;">wpsc_get_telemetry</code></td>
                                <td>Mengambil data stack server, penggunaan memori, status OPcache, dan ukuran cache secara real-time.</td>
                                <td style="text-align: right;"><span class="wpsc-badge wpsc-badge-green">Active</span></td>
                            </tr>
                            <tr>
                                <td><code style="color: #38bdf8;">wpsc_purge_cache</code></td>
                                <td>Membersihkan seluruh cache statis HTML atau membersihkan single target URL.</td>
                                <td style="text-align: right;"><span class="wpsc-badge wpsc-badge-green">Active</span></td>
                            </tr>
                            <tr>
                                <td><code style="color: #38bdf8;">wpsc_warm_cache</code></td>
                                <td>Memanaskan cache secara otomatis untuk halaman depan dan feed.</td>
                                <td style="text-align: right;"><span class="wpsc-badge wpsc-badge-green">Active</span></td>
                            </tr>
                            <tr>
                                <td><code style="color: #38bdf8;">wpsc_autotune</code></td>
                                <td>Menerapkan profil akselerasi 1-Click Adaptive Auto-Tune secara programatik.</td>
                                <td style="text-align: right;"><span class="wpsc-badge wpsc-badge-green">Active</span></td>
                            </tr>
                            <tr>
                                <td><code style="color: #38bdf8;">wpsc_audit_conflicts</code></td>
                                <td>Mendeteksi duplikasi tag tracking (GA4/GTM/Pixel) dan konflik plugin lain.</td>
                                <td style="text-align: right;"><span class="wpsc-badge wpsc-badge-green">Active</span></td>
                            </tr>
                            <tr>
                                <td><code style="color: #38bdf8;">wpsc_optimize_db</code></td>
                                <td>Membersihkan sampah pos, draf otomatis, transient kadaluarsa, dan defragmentasi tabel DB.</td>
                                <td style="text-align: right;"><span class="wpsc-badge wpsc-badge-green">Active</span></td>
                            </tr>
                            <tr>
                                <td><code style="color: #38bdf8;">wpsc_get_checklist</code></td>
                                <td>Mengambil kepatuhan checklist performa dan skor kesehatan web.</td>
                                <td style="text-align: right;"><span class="wpsc-badge wpsc-badge-green">Active</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- TAB 5: SETTINGS & CDN -->
            <?php if ($active_tab === 'settings'): ?>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
                    <!-- CDN Configuration -->
                    <div class="wpsc-glass" style="padding: 24px;">
                        <h3 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 700; color: #f1f5f9; display: flex; align-items: center; gap: 8px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" x2="22" y1="12" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                            Content Delivery Network (CDN) CNAME
                        </h3>
                        <form method="post">
                            <?php wp_nonce_field('wpsc_cdn_nonce'); ?>
                            <label style="display: block; font-size: 13px; color: #cbd5e1; margin-bottom: 8px;">
                                <input type="checkbox" name="wpsc_enable_cdn" value="1" <?php checked(!empty($settings['cdn']['enable_cdn'])); ?>>
                                Aktifkan CDN URL Rewriter
                            </label>
                            <div style="margin: 12px 0;">
                                <label style="display: block; font-size: 12px; color: #94a3b8; margin-bottom: 4px;">CDN Hostname URL:</label>
                                <input type="url" name="wpsc_cdn_url" value="<?php echo esc_attr($settings['cdn']['cdn_url'] ?? ''); ?>" placeholder="https://cdn.yoursite.com" style="width: 100%; background: #050811; border: 1px solid rgba(255,255,255,0.1); color: #fff; padding: 8px 12px; border-radius: 8px;">
                            </div>
                            <button type="submit" name="wpsc_save_cdn" class="wpsc-btn-metallic">Simpan Pengaturan CDN</button>
                        </form>
                    </div>

                    <!-- Cache Exclusions -->
                    <div class="wpsc-glass" style="padding: 24px;">
                        <h3 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 700; color: #f1f5f9; display: flex; align-items: center; gap: 8px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2dd4bf" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" x2="19.07" y1="4.93" y2="19.07"></line></svg>
                            Pengecualian Cache URL
                        </h3>
                        <form method="post">
                            <?php wp_nonce_field('wpsc_exclusions_nonce'); ?>
                            <label style="display: block; font-size: 12px; color: #94a3b8; margin-bottom: 4px;">Daftar Path URL yang Dikecualikan (1 per baris):</label>
                            <textarea name="wpsc_cache_exclusions" rows="4" style="width: 100%; background: #050811; border: 1px solid rgba(255,255,255,0.1); color: #fff; padding: 8px 12px; border-radius: 8px; font-family: monospace; font-size: 12px;" placeholder="/custom-cart&#10;/dashboard/*"><?php echo esc_textarea($settings['cache']['cache_exclusions'] ?? ''); ?></textarea>
                            <button type="submit" name="wpsc_save_cache_exclusions" class="wpsc-btn-metallic" style="margin-top: 10px;">Simpan Pengecualian</button>
                        </form>
                    </div>
                </div>

                <!-- Google PageSpeed Insights API Key Box -->
                <div class="wpsc-glass" style="padding: 24px; margin-bottom: 24px;">
                    <h3 style="margin: 0 0 12px 0; font-size: 15px; font-weight: 700; color: #f1f5f9; display: flex; align-items: center; gap: 8px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="2"><path d="M12 2v20"></path><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                        Google PageSpeed Insights API Key (Opsional)
                    </h3>
                    <p style="margin: 0 0 12px 0; font-size: 13px; color: #94a3b8;">
                        Masukkan API Key gratis dari Google Cloud Console untuk mendapatkan kuota 25.000 audit/hari tanpa batasan rate limit shared IP hosting.
                    </p>
                    <form method="post" style="display: flex; gap: 10px; max-width: 600px;">
                        <?php wp_nonce_field('wpsc_psi_key_nonce'); ?>
                        <input type="text" name="wpsc_psi_api_key" value="<?php echo esc_attr($settings['pagespeed']['api_key'] ?? ''); ?>" placeholder="AIzaSy..." style="flex: 1; background: #050811; border: 1px solid rgba(255,255,255,0.1); color: #fff; padding: 8px 12px; border-radius: 8px; font-family: monospace;">
                        <button type="submit" name="wpsc_save_psi_key" class="wpsc-btn-metallic">Simpan Key</button>
                    </form>
                </div>

                <!-- Database Maintenance Box -->
                <div class="wpsc-glass" style="padding: 24px; margin-bottom: 24px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
                        <div>
                            <h3 style="margin: 0 0 4px 0; font-size: 15px; font-weight: 700; color: #f1f5f9;">Database Housekeeper & Defragmenter</h3>
                            <p style="margin: 0; font-size: 13px; color: #94a3b8;">
                                Membersihkan revisi artikel usang, draf otomatis, transient kadaluarsa, sampah komentar, dan mengoptimalkan tabel database.
                            </p>
                        </div>
                        <form method="post" style="margin: 0;">
                            <?php wp_nonce_field('wpsc_db_clean_nonce'); ?>
                            <button type="submit" name="wpsc_db_clean" class="wpsc-btn-primary">
                                Optimasi Database Sekarang
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB 6: DIAGNOSTICS & LOGS -->
            <?php if ($active_tab === 'logs'): ?>
                <div class="wpsc-terminal" style="margin-bottom: 24px;">
                    <div class="wpsc-terminal-header">
                        <div class="wpsc-terminal-dots">
                            <div class="wpsc-terminal-dot" style="background: #ef4444;"></div>
                            <div class="wpsc-terminal-dot" style="background: #f59e0b;"></div>
                            <div class="wpsc-terminal-dot" style="background: #10b981;"></div>
                        </div>
                        <div style="font-family: monospace; font-size: 12px; color: #64748b;">wp-speed-core/logs/diagnostic.log</div>
                        <form method="post" style="margin: 0;">
                            <?php wp_nonce_field('wpsc_clear_logs_nonce'); ?>
                            <button type="submit" name="wpsc_clear_logs" class="wpsc-btn-metallic" style="padding: 4px 12px; font-size: 11px;">Clear Logs</button>
                        </form>
                    </div>
                    <pre class="wpsc-terminal-body"><?php
                        if (empty($logs)) {
                            echo "No diagnostic log entries recorded yet. System running smoothly.";
                        } else {
                            foreach ($logs as $line) {
                                echo esc_html($line) . "\n";
                            }
                        }
                    ?></pre>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
