<?php
declare(strict_types=1);

namespace WPSpeedCore\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Dashboard {
    private array $modules;

    public function __construct(array $modules) {
        $this->modules = $modules;
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_init', [$this, 'actions']);
        add_action('admin_notices', [$this, 'notices']);
    }

    public function enqueue_scripts(string $hook): void {
        if ($hook !== 'settings_page_wp-speed-core') {
            return;
        }
        wp_enqueue_style('wpsc-psi-css', plugins_url('../../assets/css/pagespeed-gauge.css', __FILE__), [], WPSC_VERSION);
        wp_enqueue_script('wpsc-psi-js', plugins_url('../../assets/js/pagespeed-dashboard.js', __FILE__), [], WPSC_VERSION, true);
        wp_localize_script('wpsc-psi-js', 'wpscPsiSettings', [
            'rest_url' => rest_url('wp-speed-core/v1/pagespeed'),
            'nonce'    => wp_create_nonce('wp_rest'),
        ]);
    }

    public function menu(): void {
        add_options_page(
            'WP Speed Core',
            'WP Speed Core ⚡',
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
            wp_safe_redirect(add_query_arg(['page' => 'wp-speed-core', 'tuned' => '1'], admin_url('options-general.php')));
            exit;
        }

        if (isset($_POST['wpsc_purge_cache']) && check_admin_referer('wpsc_purge_nonce')) {
            do_action('wpsc_purge_all');
            wp_safe_redirect(add_query_arg(['page' => 'wp-speed-core', 'purged' => '1'], admin_url('options-general.php')));
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
            wp_safe_redirect(add_query_arg(['page' => 'wp-speed-core', 'db_cleaned' => '1'], admin_url('options-general.php')));
            exit;
        }
        if (isset($_POST['wpsc_warm_cache']) && check_admin_referer('wpsc_warm_nonce')) {
            do_action('wpsc_warm_cache');
            wp_safe_redirect(add_query_arg(['page' => 'wp-speed-core', 'warmed' => '1'], admin_url('options-general.php')));
            exit;
        }

        if (isset($_POST['wpsc_save_cdn']) && check_admin_referer('wpsc_cdn_nonce')) {
            $curr = (array) get_option('wpsc_settings', []);
            $curr['cdn']['enable_cdn'] = !empty($_POST['wpsc_enable_cdn']) ? 1 : 0;
            $curr['cdn']['cdn_url']    = esc_url_raw($_POST['wpsc_cdn_url'] ?? '');
            update_option('wpsc_settings', $curr);
            wp_safe_redirect(add_query_arg(['page' => 'wp-speed-core', 'cdn_saved' => '1'], admin_url('options-general.php')));
            exit;
        }

        if (isset($_POST['wpsc_save_cache_exclusions']) && check_admin_referer('wpsc_exclusions_nonce')) {
            $curr = (array) get_option('wpsc_settings', []);
            $curr['cache']['cache_exclusions'] = sanitize_textarea_field($_POST['wpsc_cache_exclusions'] ?? '');
            update_option('wpsc_settings', $curr);
            wp_safe_redirect(add_query_arg(['page' => 'wp-speed-core', 'exclusions_saved' => '1'], admin_url('options-general.php')));
            exit;
        }

        if (isset($_POST['wpsc_save_psi_key']) && check_admin_referer('wpsc_psi_key_nonce')) {
            $curr = (array) get_option('wpsc_settings', []);
            $curr['pagespeed']['api_key'] = sanitize_text_field($_POST['wpsc_psi_api_key'] ?? '');
            update_option('wpsc_settings', $curr);
            wp_safe_redirect(add_query_arg(['page' => 'wp-speed-core', 'psi_key_saved' => '1'], admin_url('options-general.php')));
            exit;
        }
        if (isset($_POST['wpsc_clear_logs']) && check_admin_referer('wpsc_clear_logs_nonce')) {
            $logger = $this->modules['logger'] ?? null;
            if ($logger) {
                $logger->clear();
            }
            wp_safe_redirect(add_query_arg(['page' => 'wp-speed-core', 'log_cleared' => '1'], admin_url('options-general.php')));
            exit;
        }
    }

    public function notices(): void {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'settings_page_wp-speed-core') {
            return;
        }

        // Notices are rendered directly inside our futuristic dashboard banner
    }

    public function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Akses ditolak.', 'wp-speed-core'));
        }

        $env            = isset($this->modules['env']) ? $this->modules['env']->get() : [];
        $conflicts      = isset($this->modules['arbiter']) ? $this->modules['arbiter']->audit_overlaps() : [];
        $tracking_audit = get_transient('wpsc_tag_audit') ?: [];
        $logger         = $this->modules['logger'] ?? null;

        if ($logger && isset($this->modules['env'])) {
            static $logged = false;
            if (!$logged) {
                $logger->log_system_snapshot($this->modules['env']);
                $logged = true;
            }
        }

        $raw_logs = $logger ? $logger->get_logs(120) : 'Logger module inactive.';
        $settings = (array) get_option('wpsc_settings', []);
        ?>

        <!-- Custom Futuristic CSS Framework -->
        <style>
            :root {
                --wpsc-bg-dark: #070a13;
                --wpsc-card-bg: rgba(15, 23, 42, 0.78);
                --wpsc-card-border: rgba(255, 255, 255, 0.08);
                --wpsc-card-hover: rgba(30, 41, 59, 0.85);
                --wpsc-cyan: #00f2fe;
                --wpsc-blue: #3b82f6;
                --wpsc-emerald: #10b981;
                --wpsc-amber: #f59e0b;
                --wpsc-crimson: #ef4444;
                --wpsc-purple: #8b5cf6;
                --wpsc-text-main: #f8fafc;
                --wpsc-text-muted: #94a3b8;
            }

            #wpcontent {
                background: var(--wpsc-bg-dark) !important;
                color: var(--wpsc-text-main) !important;
            }

            .wpsc-shell {
                max-width: 1240px;
                width: 100%;
                margin: clamp(10px, 2vw, 20px) clamp(10px, 2vw, 20px) 40px 0;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Inter", sans-serif;
                color: var(--wpsc-text-main);
            }

            .wpsc-shell * {
                box-sizing: border-box;
            }

            /* Glassmorphism Cards */
            .wpsc-glass {
                background: var(--wpsc-card-bg);
                backdrop-filter: blur(16px);
                -webkit-backdrop-filter: blur(16px);
                border: 1px solid var(--wpsc-card-border);
                border-radius: clamp(12px, 1.8vw, 16px);
                box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
                transition: all 0.25s ease-in-out;
            }

            .wpsc-glass:hover {
                border-color: rgba(0, 242, 254, 0.25);
                box-shadow: 0 12px 40px 0 rgba(0, 242, 254, 0.08);
            }

            /* Futuristic Header HUD */
            .wpsc-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: clamp(14px, 2vw, 20px);
                padding: clamp(16px, 2.5vw, 26px) clamp(18px, 3vw, 30px);
                margin-bottom: clamp(16px, 2vw, 24px);
                position: relative;
                overflow: hidden;
            }

            .wpsc-header::before {
                content: '';
                position: absolute;
                top: -50%;
                left: -20%;
                width: 400px;
                height: 400px;
                background: radial-gradient(circle, rgba(0, 242, 254, 0.12) 0%, rgba(0,0,0,0) 70%);
                pointer-events: none;
            }

            .wpsc-logo-wrap {
                display: flex;
                align-items: center;
                gap: clamp(10px, 1.5vw, 16px);
            }

            .wpsc-logo-icon {
                width: clamp(38px, 5vw, 48px);
                height: clamp(38px, 5vw, 48px);
                background: linear-gradient(135deg, #00f2fe 0%, #4facfe 100%);
                border-radius: clamp(10px, 1.5vw, 14px);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: clamp(20px, 3vw, 24px);
                box-shadow: 0 0 20px rgba(0, 242, 254, 0.4);
                flex-shrink: 0;
            }

            .wpsc-title {
                font-size: clamp(18px, 3.5vw, 24px);
                font-weight: 800;
                letter-spacing: -0.5px;
                margin: 0;
                background: linear-gradient(135deg, #ffffff 0%, #cbd5e1 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                line-height: 1.2;
            }

            .wpsc-subtitle {
                font-size: clamp(11px, 1.5vw, 13px);
                color: var(--wpsc-text-muted);
                margin-top: 4px;
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 8px;
            }

            .wpsc-badge-live {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 3px 10px;
                background: rgba(16, 185, 129, 0.15);
                border: 1px solid rgba(16, 185, 129, 0.3);
                border-radius: 20px;
                font-size: 11px;
                font-weight: 600;
                color: #34d399;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .wpsc-radar-dot {
                width: 7px;
                height: 7px;
                background: #10b981;
                border-radius: 50%;
                box-shadow: 0 0 10px #10b981;
                animation: wpsc-pulse 2s infinite;
            }

            @keyframes wpsc-pulse {
                0% { transform: scale(0.95); opacity: 0.8; }
                50% { transform: scale(1.3); opacity: 1; box-shadow: 0 0 14px #34d399; }
                100% { transform: scale(0.95); opacity: 0.8; }
            }

            /* Buttons */
            .wpsc-btn-primary {
                background: linear-gradient(135deg, #00f2fe 0%, #2563eb 100%);
                color: #ffffff !important;
                font-weight: 700;
                font-size: 13px;
                padding: 11px 22px;
                border-radius: 10px;
                border: none;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                box-shadow: 0 4px 15px rgba(0, 242, 254, 0.3);
                transition: all 0.2s ease;
                text-decoration: none;
            }

            .wpsc-btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 22px rgba(0, 242, 254, 0.45);
            }

            .wpsc-btn-ghost {
                background: rgba(255, 255, 255, 0.05);
                color: #cbd5e1 !important;
                font-weight: 600;
                font-size: 13px;
                padding: 10px 18px;
                border-radius: 10px;
                border: 1px solid rgba(255, 255, 255, 0.1);
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                transition: all 0.2s ease;
            }

            .wpsc-btn-ghost:hover {
                background: rgba(255, 255, 255, 0.1);
                color: #fff !important;
                border-color: rgba(255, 255, 255, 0.2);
            }

            /* Alerts */
            .wpsc-alert {
                padding: 16px 20px;
                border-radius: 12px;
                margin-bottom: 24px;
                display: flex;
                align-items: flex-start;
                gap: 14px;
                font-size: 13px;
                line-height: 1.5;
            }

            .wpsc-alert-success {
                background: rgba(16, 185, 129, 0.12);
                border: 1px solid rgba(16, 185, 129, 0.3);
                color: #a7f3d0;
            }

            .wpsc-alert-warning {
                background: rgba(245, 158, 11, 0.12);
                border: 1px solid rgba(245, 158, 11, 0.3);
                color: #fde68a;
            }

            .wpsc-alert-info {
                background: rgba(59, 130, 246, 0.12);
                border: 1px solid rgba(59, 130, 246, 0.3);
                color: #bfdbfe;
            }

            /* Telemetry Grid */
            .wpsc-telemetry-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(min(100%, 160px), 1fr));
                gap: clamp(10px, 1.5vw, 16px);
                margin-bottom: clamp(16px, 2vw, 24px);
            }

            .wpsc-telemetry-card {
                padding: clamp(12px, 2vw, 18px);
                position: relative;
                overflow: hidden;
            }

            .wpsc-tel-label {
                font-size: clamp(10px, 1.2vw, 11px);
                text-transform: uppercase;
                letter-spacing: 0.8px;
                color: var(--wpsc-text-muted);
                margin-bottom: 6px;
                font-weight: 600;
            }

            .wpsc-tel-val {
                font-size: clamp(15px, 2.5vw, 18px);
                font-weight: 700;
                color: #ffffff;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .wpsc-pill {
                font-size: 11px;
                padding: 2px 8px;
                border-radius: 6px;
                font-weight: 600;
            }

            .wpsc-pill-active {
                background: rgba(16, 185, 129, 0.2);
                color: #34d399;
                border: 1px solid rgba(16, 185, 129, 0.3);
            }

            .wpsc-pill-warn {
                background: rgba(245, 158, 11, 0.2);
                color: #fbbf24;
                border: 1px solid rgba(245, 158, 11, 0.3);
            }

            /* Optimization Modules Matrix */
            .wpsc-modules-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr));
                gap: clamp(12px, 1.8vw, 18px);
                margin-bottom: clamp(16px, 2vw, 24px);
            }

            .wpsc-module-card {
                padding: clamp(14px, 2.2vw, 22px);
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                position: relative;
            }

            .wpsc-module-card::after {
                content: '';
                position: absolute;
                bottom: 0;
                left: 20px;
                right: 20px;
                height: 2px;
                background: linear-gradient(90deg, transparent, rgba(0, 242, 254, 0.4), transparent);
            }

            .wpsc-mod-header {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 12px;
            }

            .wpsc-mod-icon {
                width: 36px;
                height: 36px;
                background: rgba(255, 255, 255, 0.05);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 18px;
            }

            .wpsc-mod-title {
                font-size: 15px;
                font-weight: 700;
                color: #fff;
                margin: 0;
            }

            .wpsc-mod-desc {
                font-size: 12px;
                color: var(--wpsc-text-muted);
                line-height: 1.6;
                margin: 0 0 16px 0;
            }

            .wpsc-mod-status {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding-top: 12px;
                border-top: 1px solid rgba(255, 255, 255, 0.06);
                font-size: 12px;
            }

            /* Terminal Console */
            .wpsc-terminal {
                border-radius: 16px;
                overflow: hidden;
                background: #0b0f19;
                border: 1px solid rgba(255, 255, 255, 0.08);
            }

            .wpsc-terminal-bar {
                background: #111827;
                padding: 12px 18px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            }

            .wpsc-window-dots {
                display: flex;
                gap: 6px;
            }

            .wpsc-dot {
                width: 11px;
                height: 11px;
                border-radius: 50%;
            }

            .wpsc-dot-red { background: #ef4444; }
            .wpsc-dot-yellow { background: #f59e0b; }
            .wpsc-dot-green { background: #10b981; }

            .wpsc-terminal-title {
                font-family: monospace;
                font-size: 12px;
                color: var(--wpsc-text-muted);
            }

            .wpsc-terminal-body {
                padding: 18px;
                margin: 0;
                font-family: "JetBrains Mono", "Fira Code", Consolas, Monaco, monospace;
                font-size: 12px;
                color: #d1d5db;
                line-height: 1.6;
                max-height: 380px;
                overflow-y: auto;
                background: #080c14;
                white-space: pre-wrap;
                word-break: break-word;
            }

            .wpsc-log-info { color: #38bdf8; }
            .wpsc-log-warn { color: #f59e0b; }
            .wpsc-log-err { color: #f87171; }
            .wpsc-log-time { color: #64748b; }

            /* Fluid Responsive Breakpoints & Device Orientations */
            @media (max-width: 768px) {
                .wpsc-shell {
                    margin: 10px 10px 30px 0;
                }
                .wpsc-header {
                    flex-direction: column;
                    align-items: stretch;
                    gap: 16px;
                }
                .wpsc-header > div:last-child {
                    display: flex;
                    flex-direction: column;
                    width: 100%;
                    gap: 8px;
                }
                .wpsc-header > div:last-child form {
                    width: 100%;
                }
                .wpsc-btn-primary, .wpsc-btn-ghost {
                    width: 100%;
                    justify-content: center;
                    text-align: center;
                }
                .wpsc-modules-grid {
                    grid-template-columns: 1fr;
                }
            }

            @media (orientation: landscape) and (max-height: 540px) {
                .wpsc-shell {
                    margin: 10px 10px 20px 0;
                }
                .wpsc-header {
                    padding: 12px 18px;
                }
                .wpsc-terminal-body {
                    max-height: 220px;
                }
            }
        </style>

        <div class="wpsc-shell">
            <!-- Header HUD -->
            <div class="wpsc-glass wpsc-header">
                <div class="wpsc-logo-wrap">
                    <div class="wpsc-logo-icon">&#x26A1;</div>
                    <div>
                        <h1 class="wpsc-title">WP Speed Core <span style="font-size: 13px; font-weight: 500; opacity: 0.6;">v<?php echo esc_html(WPSC_VERSION); ?></span></h1>
                        <div class="wpsc-subtitle">
                            <span class="wpsc-badge-live"><span class="wpsc-radar-dot"></span> Active Engine</span>
                            <span>&bull; Author: <a href="https://t.me/leddhany" target="_blank" style="color: var(--wpsc-cyan); text-decoration: none;">Dhany (@leddhany)</a></span>
                        </div>
                    </div>
                </div>
                <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                    <form method="post" style="margin: 0;">
                        <?php wp_nonce_field('wpsc_purge_nonce'); ?>
                        <button type="submit" name="wpsc_purge_cache" class="wpsc-btn-ghost">&#x1F5D1; <?php esc_html_e('Purge Local Disk Cache', 'wp-speed-core'); ?></button>
                    </form>
                    <form method="post" style="margin: 0;">
                        <?php wp_nonce_field('wpsc_warm_nonce'); ?>
                        <button type="submit" name="wpsc_warm_cache" class="wpsc-btn-ghost">&#x1F525; <?php esc_html_e('Warm Cache', 'wp-speed-core'); ?></button>
                    </form>
                    <form method="post" style="margin: 0;">
                        <?php wp_nonce_field('wpsc_db_clean_nonce'); ?>
                        <button type="submit" name="wpsc_db_clean" class="wpsc-btn-ghost">&#x1F9F9; <?php esc_html_e('Clean DB Now', 'wp-speed-core'); ?></button>
                    </form>
                    <form method="post" style="margin: 0;">
                        <?php wp_nonce_field('wpsc_autotune_nonce'); ?>
                        <button type="submit" name="wpsc_auto_tune" class="wpsc-btn-primary">&#x1F680; <?php esc_html_e('1-Click Auto-Tune', 'wp-speed-core'); ?></button>
                    </form>
                </div>
            </div>

            <!-- Flash Action Notices -->
            <?php if (isset($_GET['tuned'])): ?>
                <div class="wpsc-alert wpsc-alert-success">
                    <div style="font-size: 18px;">&#x1F680;</div>
                    <div>
                        <strong><?php esc_html_e('Smart Auto-Tune Berhasil Diterapkan!', 'wp-speed-core'); ?></strong><br>
                        <?php esc_html_e('Profil akselerasi server, JS delay, LCP hero priority, dan cache telah dioptimasi otomatis sesuai stack sistem Anda.', 'wp-speed-core'); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['exclusions_saved'])): ?>
                <div class="wpsc-alert wpsc-alert-info">
                    <div style="font-size: 18px;">&#x1F687;</div>
                    <div>
                        <strong><?php esc_html_e('Pengecualian Cache Berhasil Disimpan!', 'wp-speed-core'); ?></strong><br>
                        <?php esc_html_e('Path/URL yang terdaftar tidak akan disimpan ke dalam cache statis.', 'wp-speed-core'); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['warmed'])): ?>
                <div class="wpsc-alert wpsc-alert-success">
                    <div style="font-size: 18px;">&#x1F525;</div>
                    <div>
                        <strong><?php esc_html_e('Cache Warmer Berhasil Dijalankan!', 'wp-speed-core'); ?></strong><br>
                        <?php esc_html_e('Halaman utama dan feed telah dimuat ulang ke dalam cache statis.', 'wp-speed-core'); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['db_cleaned'])): ?>
                <div class="wpsc-alert wpsc-alert-success">
                    <div style="font-size: 18px;">&#x1F9F9;</div>
                    <div>
                        <strong><?php esc_html_e('Pembersihan & Defragmentasi Database Selesai!', 'wp-speed-core'); ?></strong><br>
                        <?php esc_html_e('Revisi usang, sampah, transient, dan tabel database telah di-optimasi.', 'wp-speed-core'); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['cdn_saved'])): ?>
                <div class="wpsc-alert wpsc-alert-info">
                    <div style="font-size: 18px;">&#x1F310;</div>
                    <div>
                        <strong><?php esc_html_e('Pengaturan CDN Berhasil Disimpan!', 'wp-speed-core'); ?></strong><br>
                        <?php esc_html_e('URL aset statis (wp-content/wp-includes) akan rewrite otomatis ke CNAME CDN Anda.', 'wp-speed-core'); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['purged'])): ?>
                <div class="wpsc-alert wpsc-alert-success">
                    <div style="font-size: 18px;">&#x26A1;</div>
                    <div>
                        <strong><?php esc_html_e('Local Disk HTML Cache Berhasil Dikosongkan!', 'wp-speed-core'); ?></strong><br>
                        <?php esc_html_e('Seluruh cache HTML statis lokal pada direktori server hosting telah dibersihkan secara instan (di luar cache CDN global).', 'wp-speed-core'); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['log_cleared'])): ?>
                <div class="wpsc-alert wpsc-alert-info">
                    <div style="font-size: 18px;">&#x1F4DD;</div>
                    <div>
                        <strong><?php esc_html_e('Log Diagnosa Telah Direset', 'wp-speed-core'); ?></strong><br>
                        <?php esc_html_e('Berkas log sistem telah dibersihkan dan siap merekam aktivitas performa baru.', 'wp-speed-core'); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['psi_key_saved'])): ?>
                <div class="wpsc-alert wpsc-alert-success">
                    <div style="font-size: 18px;">&#x1F511;</div>
                    <div>
                        <strong><?php esc_html_e('Google PageSpeed API Key Berhasil Disimpan!', 'wp-speed-core'); ?></strong><br>
                        <?php esc_html_e('Audit PageSpeed kini menggunakan kuota API pribadi Anda (25.000 per hari).', 'wp-speed-core'); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tracking Tag Conflicts Radar -->
            <?php if (!empty($tracking_audit)): ?>
                <div class="wpsc-alert wpsc-alert-warning">
                    <div style="font-size: 20px;">&#x26A0;&#xFE0F;</div>
                    <div>
                        <strong style="font-size: 14px;"><?php esc_html_e('Smart Tag Auditor: Duplikasi Tag Tracking Terdeteksi', 'wp-speed-core'); ?></strong>
                        <ul style="margin: 6px 0 0; padding-left: 18px;">
                            <?php foreach ($tracking_audit as $dup): ?>
                                <li><strong><?php echo esc_html($dup['type']); ?>:</strong> <?php echo esc_html($dup['msg']); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Overlap Arbiter Alerts -->
            <?php if (!empty($conflicts)): ?>
                <div class="wpsc-alert wpsc-alert-info">
                    <div style="font-size: 20px;">&#x1F50D;</div>
                    <div>
                        <strong style="font-size: 14px;"><?php esc_html_e('Smart Arbiter: Deteksi Plugin Tumpang Tindih', 'wp-speed-core'); ?></strong>
                        <div style="margin-top: 6px;">
                            <?php foreach ($conflicts as $c): ?>
                                <p style="margin: 4px 0;"><strong><?php echo esc_html($c['plugin']); ?></strong> (<?php echo esc_html(implode(', ', $c['features'])); ?>) &rarr; <em><?php echo esc_html($c['tip']); ?></em></p>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Telemetry HUD -->
            <div class="wpsc-telemetry-grid">
                <div class="wpsc-glass wpsc-telemetry-card">
                    <div class="wpsc-tel-label">&#x1F4BB; WordPress Core</div>
                    <div class="wpsc-tel-val">
                        v<?php echo esc_html($env['wordpress']['version'] ?? get_bloginfo('version')); ?>
                        <span class="wpsc-pill wpsc-pill-active"><?php echo !empty($env['wordpress']['is_block_theme']) ? 'FSE Block' : 'Classic'; ?></span>
                    </div>
                </div>

                <div class="wpsc-glass wpsc-telemetry-card">
                    <div class="wpsc-tel-label">&#x1F5A5;&#xFE0F; Web Server</div>
                    <div class="wpsc-tel-val" style="font-size: 15px; word-break: break-all;">
                        <?php echo esc_html($env['server']['software'] ?? 'Unknown'); ?>
                    </div>
                </div>

                <div class="wpsc-glass wpsc-telemetry-card">
                    <div class="wpsc-tel-label">&#x2699;&#xFE0F; PHP Engine</div>
                    <div class="wpsc-tel-val">
                        v<?php echo esc_html($env['php']['version'] ?? PHP_VERSION); ?>
                    </div>
                </div>

                <div class="wpsc-glass wpsc-telemetry-card">
                    <div class="wpsc-tel-label">&#x26A1; OPcache & JIT</div>
                    <div class="wpsc-tel-val">
                        <?php if (!empty($env['php']['opcache'])): ?>
                            <span class="wpsc-pill wpsc-pill-active">OPcache ON</span>
                            <?php if (!empty($env['php']['jit'])): ?>
                                <span class="wpsc-pill wpsc-pill-active">JIT</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="wpsc-pill wpsc-pill-warn">Disabled</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="wpsc-glass wpsc-telemetry-card">
                    <div class="wpsc-tel-label">&#x1F9E0; Memory Limit</div>
                    <div class="wpsc-tel-val">
                        <?php echo esc_html($env['php']['memory_limit'] ?? '128M'); ?>
                    </div>
                </div>

                <div class="wpsc-glass wpsc-telemetry-card">
                    <div class="wpsc-tel-label">&#x1F3AF; Tag Processor</div>
                    <div class="wpsc-tel-val">
                        <?php if (!empty($env['wordpress']['has_tag_processor'])): ?>
                            <span class="wpsc-pill wpsc-pill-active">WP Native</span>
                        <?php else: ?>
                            <span class="wpsc-pill wpsc-pill-warn">DOM Fallback</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- CDN & Cache Exclusion Panels Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 18px; margin-bottom: 24px;">
                <!-- CDN Panel -->
                <div class="wpsc-glass" style="padding: 22px;">
                    <h3 style="margin: 0 0 12px 0; font-size: 16px; font-weight: 700; color: #fff;">&#x1F310; <?php esc_html_e('Content Delivery Network (CDN)', 'wp-speed-core'); ?></h3>
                    <p style="margin: 0 0 16px 0; font-size: 13px; color: var(--wpsc-text-muted);"><?php esc_html_e('Arahkan seluruh aset statis (gambar, CSS, JS, font) ke domain CNAME CDN kustom Anda.', 'wp-speed-core'); ?></p>
                    <form method="post">
                        <?php wp_nonce_field('wpsc_cdn_nonce'); ?>
                        <div style="margin-bottom: 12px;">
                            <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600;">
                                <input type="checkbox" name="wpsc_enable_cdn" value="1" <?php checked(!empty($settings['cdn']['enable_cdn'])); ?>>
                                <?php esc_html_e('Aktifkan CDN Rewriter', 'wp-speed-core'); ?>
                            </label>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <input type="url" name="wpsc_cdn_url" value="<?php echo esc_url($settings['cdn']['cdn_url'] ?? ''); ?>" placeholder="https://cdn.domainanda.com" style="background: #0f172a; border: 1px solid rgba(255,255,255,0.15); color: #fff; padding: 8px 14px; border-radius: 8px; flex: 1; font-size: 13px;">
                            <button type="submit" name="wpsc_save_cdn" class="wpsc-btn-ghost" style="padding: 8px 16px; font-size: 12px;"><?php esc_html_e('Simpan CDN', 'wp-speed-core'); ?></button>
                        </div>
                    </form>
                </div>

                <!-- Cache Exclusions Panel -->
                <div class="wpsc-glass" style="padding: 22px;">
                    <h3 style="margin: 0 0 12px 0; font-size: 16px; font-weight: 700; color: #fff;">&#x1F687; <?php esc_html_e('Never Cache URLs (Cache Exclusions)', 'wp-speed-core'); ?></h3>
                    <p style="margin: 0 0 12px 0; font-size: 13px; color: var(--wpsc-text-muted);"><?php esc_html_e('Masukkan URL/Path per baris yang tidak boleh di-cache (misal: /dynamic-page/ atau /custom-checkout/*).', 'wp-speed-core'); ?></p>
                    <form method="post">
                        <?php wp_nonce_field('wpsc_exclusions_nonce'); ?>
                        <textarea name="wpsc_cache_exclusions" rows="3" style="width: 100%; background: #0f172a; border: 1px solid rgba(255,255,255,0.15); color: #fff; padding: 8px 12px; border-radius: 8px; font-family: monospace; font-size: 12px; margin-bottom: 10px;" placeholder="/dynamic-page/&#10;/custom-checkout/*"><?php echo esc_textarea($settings['cache']['cache_exclusions'] ?? ''); ?></textarea>
                        <button type="submit" name="wpsc_save_cache_exclusions" class="wpsc-btn-ghost" style="padding: 8px 16px; font-size: 12px;"><?php esc_html_e('Simpan Pengecualian', 'wp-speed-core'); ?></button>
                    </form>
                </div>
            </div>

            <!-- Google PageSpeed Insights Dashboard Section -->
            <?php
            $ps_service = $this->modules['pagespeed_service'] ?? null;
            $psi_key    = $settings['pagespeed']['api_key'] ?? '';
            $psi_strat  = isset($_GET['psi_strategy']) && $_GET['psi_strategy'] === 'desktop' ? 'desktop' : 'mobile';
            $psi_data   = $ps_service ? $ps_service->get_audit_results(home_url('/'), $psi_strat, false) : [];
            $score      = $psi_data['score'] ?? 0;
            $metrics    = $psi_data['metrics'] ?? [];
            $stroke_color = $score >= 90 ? '#22c55e' : ($score >= 50 ? '#eab308' : '#ef4444');
            $dash_offset  = 339 - (339 * $score / 100);
            ?>
            <div class="wpsc-glass" style="margin-bottom: 24px; padding: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
                    <div>
                        <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #fff;">&#x26A1; Google PageSpeed Insights (<?php echo esc_html(ucfirst($psi_strat)); ?> Lab)</h3>
                        <p style="margin: 4px 0 0 0; font-size: 13px; color: var(--wpsc-text-muted);">Real-time Lighthouse v5 Performance &amp; Core Web Vitals audit.</p>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <a href="<?php echo esc_url(add_query_arg(['page' => 'wp-speed-core', 'psi_strategy' => 'mobile'], admin_url('options-general.php'))); ?>" class="wpsc-btn-ghost" style="<?php echo $psi_strat === 'mobile' ? 'background: rgba(0, 242, 254, 0.2); border-color: var(--wpsc-cyan); color: #fff !important;' : ''; ?> padding: 6px 12px; font-size: 12px;">📱 Mobile</a>
                        <a href="<?php echo esc_url(add_query_arg(['page' => 'wp-speed-core', 'psi_strategy' => 'desktop'], admin_url('options-general.php'))); ?>" class="wpsc-btn-ghost" style="<?php echo $psi_strat === 'desktop' ? 'background: rgba(0, 242, 254, 0.2); border-color: var(--wpsc-cyan); color: #fff !important;' : ''; ?> padding: 6px 12px; font-size: 12px;">💻 Desktop</a>
                        <button type="button" id="wpsc-run-psi-btn" data-strategy="<?php echo esc_attr($psi_strat); ?>" class="wpsc-btn-primary" style="font-size: 12px; padding: 8px 16px;">
                            &#x26A1; Run PageSpeed Audit
                        </button>
                    </div>
                </div>

                <?php if (empty($psi_key)): ?>
                    <div class="wpsc-alert" style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.3); color: #fde68a; margin-bottom: 18px; padding: 12px 16px;">
                        <div style="font-size: 18px;">&#x26A0;&#xFE0F;</div>
                        <div style="font-size: 12px; line-height: 1.5; flex: 1;">
                            <strong>Tips Kuota API:</strong> Google membatasi audit publik tanpa API key berdasarkan IP shared hosting (status 429). Masukkan <strong>Google PSI API Key gratis</strong> (25.000 request/hari) di bawah agar audit selalu instan &amp; lancar tanpa hambatan kuota IP.
                        </div>
                    </div>
                <?php endif; ?>

                <div class="wpsc-psi-grid">
                    <div class="wpsc-gauge-card">
                        <div class="wpsc-gauge-circle">
                            <svg viewBox="0 0 120 120">
                                <circle class="wpsc-gauge-bg" cx="60" cy="60" r="54"></circle>
                                <circle class="wpsc-gauge-val" cx="60" cy="60" r="54" stroke="<?php echo esc_attr($stroke_color); ?>" stroke-dasharray="339" stroke-dashoffset="<?php echo esc_attr($dash_offset); ?>"></circle>
                            </svg>
                            <div class="wpsc-score-text"><?php echo esc_html($score); ?></div>
                        </div>
                        <div style="font-size: 14px; font-weight: 700; color: #fff;">Performance Score</div>
                        <div style="font-size: 11px; color: var(--wpsc-text-muted); margin-top: 4px;">
                            <?php echo !empty($psi_data['from_cache']) ? 'Cached (12h TTL)' : 'Live Audit'; ?>
                        </div>
                    </div>

                    <div style="flex: 1;">
                        <div style="font-size: 13px; font-weight: 700; color: #fff; margin-bottom: 8px;">Core Web Vitals &amp; Key Metrics (<?php echo esc_html(ucfirst($psi_strat)); ?>)</div>
                        <div class="wpsc-metrics-list">
                            <div class="wpsc-metric-item">
                                <div class="wpsc-metric-label">LCP (Hero)</div>
                                <div class="wpsc-metric-value"><?php echo esc_html($metrics['lcp'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="wpsc-metric-item">
                                <div class="wpsc-metric-label">FCP (First Paint)</div>
                                <div class="wpsc-metric-value"><?php echo esc_html($metrics['fcp'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="wpsc-metric-item">
                                <div class="wpsc-metric-label">CLS (Shift)</div>
                                <div class="wpsc-metric-value"><?php echo esc_html($metrics['cls'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="wpsc-metric-item">
                                <div class="wpsc-metric-label">INP / TBT (Delay)</div>
                                <div class="wpsc-metric-value"><?php echo esc_html(($metrics['inp'] ?? 'N/A') !== 'N/A' ? ($metrics['inp'] ?? 'N/A') : ($metrics['tbt'] ?? 'N/A')); ?></div>
                            </div>
                        </div>

                        <!-- API Key Input Field -->
                        <form method="post" style="margin-top: 16px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                            <?php wp_nonce_field('wpsc_psi_key_nonce'); ?>
                            <input type="text" name="wpsc_psi_api_key" value="<?php echo esc_attr($psi_key); ?>" placeholder="Google PageSpeed API Key (Opsional / Gratis 25.000 per hari)" style="flex: 1; min-width: 240px; background: #0f172a; border: 1px solid rgba(255,255,255,0.15); color: #fff; padding: 7px 12px; border-radius: 8px; font-size: 12px;">
                            <button type="submit" name="wpsc_save_psi_key" class="wpsc-btn-ghost" style="padding: 7px 14px; font-size: 12px; white-space: nowrap;">Simpan Key</button>
                        </form>
                    </div>
                </div>
            </div>
            <!-- Optimization Modules Matrix -->
            <div style="margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #fff;">&#x1F6E1;&#xFE0F; <?php esc_html_e('Core Acceleration Matrix', 'wp-speed-core'); ?></h3>
                <span style="font-size: 12px; color: var(--wpsc-text-muted);"><?php esc_html_e('All systems active & calibrated for sub-second Core Web Vitals', 'wp-speed-core'); ?></span>
            </div>

            <div class="wpsc-modules-grid">
                <!-- Mod 1: INP Shield -->
                <div class="wpsc-glass wpsc-module-card">
                    <div>
                        <div class="wpsc-mod-header">
                            <div class="wpsc-mod-icon">&#x1F6E1;&#xFE0F;</div>
                            <h4 class="wpsc-mod-title">INP Shield Engine</h4>
                        </div>
                        <p class="wpsc-mod-desc">Chunked JavaScript execution with <code>scheduler.yield()</code> fallback to keep Interaction to Next Paint &lt; 50ms.</p>
                    </div>
                    <div class="wpsc-mod-status">
                        <span style="color: var(--wpsc-text-muted);">Strategy:</span>
                        <span class="wpsc-pill wpsc-pill-active">&#x2714; Chunked Delay</span>
                    </div>
                </div>

                <!-- Mod 2: Auto LCP Priority -->
                <div class="wpsc-glass wpsc-module-card">
                    <div>
                        <div class="wpsc-mod-header">
                            <div class="wpsc-mod-icon">&#x1F3AF;</div>
                            <h4 class="wpsc-mod-title">Auto-LCP Hero Preload</h4>
                        </div>
                        <p class="wpsc-mod-desc">Otomatis menandai hero image pertama dengan <code>fetchpriority="high"</code> &amp; <code>loading="eager"</code> untuk LCP instan.</p>
                    </div>
                    <div class="wpsc-mod-status">
                        <span style="color: var(--wpsc-text-muted);">LCP Target:</span>
                        <span class="wpsc-pill wpsc-pill-active">&#x2714; Sub-1.2s Fast</span>
                    </div>
                </div>

                <!-- Mod 3: Speculation Rules -->
                <div class="wpsc-glass wpsc-module-card">
                    <div>
                        <div class="wpsc-mod-header">
                            <div class="wpsc-mod-icon">&#x26A1;</div>
                            <h4 class="wpsc-mod-title">W3C Speculation Rules</h4>
                        </div>
                        <p class="wpsc-mod-desc">Prerender halaman berikutnya di latar belakang browser (Chrome/Edge) untuk navigasi 0ms TTFB.</p>
                    </div>
                    <div class="wpsc-mod-status">
                        <span style="color: var(--wpsc-text-muted);">Prerender:</span>
                        <span class="wpsc-pill wpsc-pill-active">&#x2714; <?php echo esc_html(ucfirst($settings['preload']['speculation_level'] ?? 'Moderate')); ?></span>
                    </div>
                </div>

                <!-- Mod 4: Content Visibility -->
                <div class="wpsc-glass wpsc-module-card">
                    <div>
                        <div class="wpsc-mod-header">
                            <div class="wpsc-mod-icon">&#x1F4E6;</div>
                            <h4 class="wpsc-mod-title">CSS content-visibility</h4>
                        </div>
                        <p class="wpsc-mod-desc">Menunda rendering elemen below-the-fold (footer, komentar) hingga dibutuhkan, memangkas beban CPU render.</p>
                    </div>
                    <div class="wpsc-mod-status">
                        <span style="color: var(--wpsc-text-muted);">Offloading:</span>
                        <span class="wpsc-pill wpsc-pill-active">&#x2714; Below-Fold Skip</span>
                    </div>
                </div>

                <!-- Mod 5: Static Disk HTML Cache -->
                <div class="wpsc-glass wpsc-module-card">
                    <div>
                        <div class="wpsc-mod-header">
                            <div class="wpsc-mod-icon">&#x1F5C4;&#xFE0F;</div>
                            <h4 class="wpsc-mod-title">Static Disk HTML Cache</h4>
                        </div>
                        <p class="wpsc-mod-desc">Menyimpan dan menyajikan HTML statis langsung dari disk diskret tanpa membebani MySQL query.</p>
                    </div>
                    <div class="wpsc-mod-status">
                        <span style="color: var(--wpsc-text-muted);">Storage:</span>
                        <span class="wpsc-pill wpsc-pill-active">&#x2714; Disk Flash</span>
                    </div>
                </div>

                <!-- Mod 6: Database Housekeeper -->
                <div class="wpsc-glass wpsc-module-card">
                    <div>
                        <div class="wpsc-mod-header">
                            <div class="wpsc-mod-icon">&#x1F9F9;</div>
                            <h4 class="wpsc-mod-title">Database Housekeeper</h4>
                        </div>
                        <p class="wpsc-mod-desc">Pembersihan berkala harian otomatis untuk revisi pos usang, transient timeout, draf sampah, dan komentar spam.</p>
                    </div>
                    <div class="wpsc-mod-status">
                        <span style="color: var(--wpsc-text-muted);">Cron Schedule:</span>
                        <span class="wpsc-pill wpsc-pill-active">&#x2714; Daily Auto</span>
                    </div>
                </div>
            </div>

            <!-- Diagnostics & Terminal Console -->
            <div class="wpsc-terminal">
                <div class="wpsc-terminal-bar">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div class="wpsc-window-dots">
                            <span class="wpsc-dot wpsc-dot-red"></span>
                            <span class="wpsc-dot wpsc-dot-yellow"></span>
                            <span class="wpsc-dot wpsc-dot-green"></span>
                        </div>
                        <span class="wpsc-terminal-title">&#x1F4DD; system@wpspeedcore: ~ /var/log/debug.log</span>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button type="button" onclick="wpscCopyLog()" class="wpsc-btn-ghost" style="padding: 4px 10px; font-size: 11px;">
                            &#x1F4CB; <?php esc_html_e('Copy Log', 'wp-speed-core'); ?>
                        </button>
                        <form method="post" style="margin: 0;" onsubmit="return confirm('<?php esc_attr_e('Yakin ingin mengosongkan log?', 'wp-speed-core'); ?>');">
                            <?php wp_nonce_field('wpsc_clear_logs_nonce'); ?>
                            <button type="submit" name="wpsc_clear_logs" class="wpsc-btn-ghost" style="padding: 4px 10px; font-size: 11px; color: #f87171 !important;">
                                &#x1F5D1; <?php esc_html_e('Clear Log', 'wp-speed-core'); ?>
                            </button>
                        </form>
                    </div>
                </div>
                <pre id="wpsc-log-output" class="wpsc-terminal-body"><?php
                    $lines = explode("\n", $raw_logs);
                    foreach ($lines as $line) {
                        $esc = esc_html($line);
                        if (strpos($esc, '[INFO]') !== false) {
                            $esc = str_replace('[INFO]', '<span class="wpsc-log-info">[INFO]</span>', $esc);
                        } elseif (strpos($esc, '[WARNING]') !== false) {
                            $esc = str_replace('[WARNING]', '<span class="wpsc-log-warn">[WARNING]</span>', $esc);
                        } elseif (strpos($esc, '[ERROR]') !== false) {
                            $esc = str_replace('[ERROR]', '<span class="wpsc-log-err">[ERROR]</span>', $esc);
                        }
                        echo $esc . "\n";
                    }
                ?></pre>
            </div>
        </div>

        <script>
            function wpscCopyLog() {
                const el = document.getElementById('wpsc-log-output');
                if (!el) return;
                const text = el.innerText || el.textContent;
                navigator.clipboard.writeText(text).then(function() {
                    alert('Log berhasil disalin ke clipboard!');
                }).catch(function() {
                    alert('Gagal menyalin log.');
                });
            }
        </script>

        <?php
    }
}
