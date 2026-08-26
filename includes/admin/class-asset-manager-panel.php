<?php
declare(strict_types=1);

namespace WPSpeedCore\Admin;

if (!defined('ABSPATH')) {
    return;
}

class AssetManagerPanel {
    public function __construct() {
        if (is_admin()) {
            add_action('admin_menu', [$this, 'menu']);
            add_action('admin_init', [$this, 'save_asset_rules']);
        }
    }

    public function menu(): void {
        add_submenu_page(
            'options-general.php',
            'Asset Unloader - WP Speed Core',
            'WPSC Asset Unloader ⚡',
            'manage_options',
            'wpsc-asset-manager',
            [$this, 'render_panel']
        );
    }

    public function save_asset_rules(): void {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['wpsc_save_assets']) && check_admin_referer('wpsc_asset_manager_nonce')) {
            $rules_input = $_POST['wpsc_rules'] ?? [];
            $clean_rules = [];

            if (is_array($rules_input)) {
                foreach ($rules_input as $rule) {
                    $handle = preg_replace('/[^a-zA-Z0-9_\.-]/', '', (string) ($rule['handle'] ?? ''));
                    if ($handle === '') {
                        continue;
                    }

                    $type       = sanitize_text_field($rule['type'] ?? 'script');
                    $everywhere = !empty($rule['everywhere']) ? 1 : 0;
                    $url_match  = sanitize_text_field($rule['url_match'] ?? '');

                    if ($everywhere || $url_match !== '') {
                        $clean_rules[] = [
                            'handle'     => $handle,
                            'type'       => $type,
                            'everywhere' => $everywhere,
                            'url_match'  => $url_match,
                        ];
                    }
                }
            }

            $custom_handle = preg_replace('/[^a-zA-Z0-9_\.-]/', '', (string) ($_POST['wpsc_custom_handle'] ?? ''));
            if ($custom_handle !== '') {
                $custom_type       = sanitize_text_field($_POST['wpsc_custom_type'] ?? 'script');
                $custom_everywhere = !empty($_POST['wpsc_custom_everywhere']) ? 1 : 0;
                $custom_url_match  = sanitize_text_field($_POST['wpsc_custom_url_match'] ?? '');

                if ($custom_everywhere || $custom_url_match !== '') {
                    $clean_rules[] = [
                        'handle'     => $custom_handle,
                        'type'       => $custom_type,
                        'everywhere' => $custom_everywhere,
                        'url_match'  => $custom_url_match,
                    ];
                }
            }

            update_option('wpsc_disabled_assets', $clean_rules);
            wp_safe_redirect(add_query_arg(['page' => 'wpsc-asset-manager', 'updated' => '1'], admin_url('options-general.php')));
            die();
        }
    }

    public function render_panel(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Akses ditolak.', 'wp-speed-core'));
        }

        $raw_rules = get_option('wpsc_disabled_assets', null);
        $rules     = is_array($raw_rules) ? $raw_rules : [];

        // If no rules are set, provide sensible default sample handles
        if ($raw_rules === null || empty($rules)) {
            $default_handles = ['jquery-migrate', 'wp-block-library', 'classic-theme-styles', 'global-styles', 'contact-form-7'];
            $rules = [];
            foreach ($default_handles as $h) {
                $rules[] = [
                    'handle'     => $h,
                    'type'       => (strpos($h, 'style') !== false || strpos($h, 'css') !== false) ? 'style' : 'script',
                    'everywhere' => 0,
                    'url_match'  => '',
                ];
            }
        }
        ?>
        <style>
            .wpsc-shell {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                margin: 20px 20px 0 0;
                color: #e2e8f0;
            }

            .wpsc-glass {
                background: rgba(15, 23, 42, 0.75);
                backdrop-filter: blur(16px);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 16px;
                padding: 24px;
                margin-bottom: 24px;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);
            }

            .wpsc-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 20px 24px;
                margin-bottom: 24px;
                background: linear-gradient(135deg, rgba(15, 23, 42, 0.9) 0%, rgba(30, 41, 59, 0.9) 100%);
            }

            .wpsc-title {
                margin: 0;
                font-size: 22px;
                font-weight: 800;
                color: #ffffff;
            }

            .wpsc-btn-primary {
                background: linear-gradient(135deg, #00f2fe 0%, #4facfe 100%);
                color: #0f172a;
                border: none;
                padding: 10px 20px;
                border-radius: 10px;
                font-weight: 700;
                font-size: 13px;
                cursor: pointer;
                transition: all 0.2s ease;
            }

            .wpsc-btn-primary:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(0, 242, 254, 0.45);
            }

            .wpsc-btn-ghost {
                background: rgba(255, 255, 255, 0.05);
                color: #00f2fe;
                border: 1px solid rgba(0, 242, 254, 0.3);
                padding: 10px 18px;
                border-radius: 10px;
                cursor: pointer;
                font-weight: 700;
                font-size: 13px;
            }

            .wpsc-table {
                width: 100%;
                border-collapse: separate;
                border-spacing: 0 8px;
            }

            .wpsc-table th {
                text-align: left;
                padding: 12px 16px;
                color: #94a3b8;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.8px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            }

            .wpsc-table td {
                padding: 14px 16px;
                background: rgba(30, 41, 59, 0.4);
                border-top: 1px solid rgba(255, 255, 255, 0.05);
                border-bottom: 1px solid rgba(255, 255, 255, 0.05);
                font-size: 13px;
            }

            .wpsc-table tr td:first-child {
                border-top-left-radius: 10px;
                border-bottom-left-radius: 10px;
                border-left: 1px solid rgba(255, 255, 255, 0.05);
            }

            .wpsc-table tr td:last-child {
                border-top-right-radius: 10px;
                border-bottom-right-radius: 10px;
                border-right: 1px solid rgba(255, 255, 255, 0.05);
            }

            .wpsc-input, .wpsc-select {
                background: #0f172a;
                border: 1px solid rgba(255, 255, 255, 0.15);
                color: #ffffff;
                padding: 8px 12px;
                border-radius: 8px;
                font-size: 12px;
                width: 100%;
            }

            .wpsc-alert-success {
                background: rgba(16, 185, 129, 0.12);
                border: 1px solid rgba(16, 185, 129, 0.3);
                color: #a7f3d0;
                padding: 16px 20px;
                border-radius: 12px;
                margin-bottom: 24px;
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .wpsc-btn-del {
                background: rgba(239, 68, 68, 0.15);
                border: 1px solid rgba(239, 68, 68, 0.3);
                color: #f87171;
                padding: 6px 12px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 11px;
                font-weight: 600;
            }

            .wpsc-btn-del:hover {
                background: rgba(239, 68, 68, 0.3);
                color: #ffffff;
            }
        </style>

        <div class="wpsc-shell">
            <!-- Header HUD -->
            <div class="wpsc-glass wpsc-header">
                <div style="display: flex; align-items: center; gap: 16px;">
                    <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #00f2fe 0%, #4facfe 100%); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 24px;">📦</div>
                    <div>
                        <h1 class="wpsc-title">WP Speed Core - Asset Unloader Manager</h1>
                        <div style="font-size: 13px; color: #94a3b8; margin-top: 3px;">
                            Matikan skrip CSS, JS, atau Keduanya secara global atau berdasarkan target URL match yang berbeda untuk nama handle yang sama.
                        </div>
                    </div>
                </div>
            </div>

            <?php if (isset($_GET['updated'])): ?>
                <div class="wpsc-alert-success">
                    <div style="font-size: 20px;">✅</div>
                    <div>
                        <strong>Aturan Penonaktifan Aset Berhasil Disimpan!</strong><br>
                        Sistem akan otomatis melepaskan (dequeue) CSS/JS sesuai target yang Anda tentukan.
                    </div>
                </div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field('wpsc_asset_manager_nonce'); ?>
                <div class="wpsc-glass">
                    <table class="wpsc-table" id="wpsc-rules-table">
                        <thead>
                            <tr>
                                <th style="width: 220px;">Handle Aset (CSS / JS)</th>
                                <th style="width: 160px;">Tipe Aset</th>
                                <th style="width: 150px;">Nonaktifkan Global</th>
                                <th>Target URL Match (Regex / Path Pattern)</th>
                                <th style="width: 80px; text-align: center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rules as $idx => $rule):
                                $handle     = $rule['handle'] ?? (is_string($idx) ? $idx : '');
                                $type       = $rule['type'] ?? 'script';
                                $everywhere = !empty($rule['everywhere']);
                                $url_match  = $rule['url_match'] ?? '';
                            ?>
                                <tr>
                                    <td>
                                        <input type="text" name="wpsc_rules[<?php echo esc_attr((string)$idx); ?>][handle]" value="<?php echo esc_attr($handle); ?>" class="wpsc-input" style="color: #00f2fe; font-weight: 700;" placeholder="misal: contact-form-7">
                                    </td>
                                    <td>
                                        <select name="wpsc_rules[<?php echo esc_attr((string)$idx); ?>][type]" class="wpsc-select">
                                            <option value="script" <?php selected($type, 'script'); ?>>Script (JS)</option>
                                            <option value="style" <?php selected($type, 'style'); ?>>Style (CSS)</option>
                                            <option value="both" <?php selected($type, 'both'); ?>>Keduanya (CSS & JS)</option>
                                        </select>
                                    </td>
                                    <td>
                                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                            <input type="checkbox" name="wpsc_rules[<?php echo esc_attr((string)$idx); ?>][everywhere]" value="1" <?php checked($everywhere); ?>>
                                            Matikan Semua
                                        </label>
                                    </td>
                                    <td>
                                        <input type="text" name="wpsc_rules[<?php echo esc_attr((string)$idx); ?>][url_match]" value="<?php echo esc_attr($url_match); ?>" class="wpsc-input" placeholder="misal: /blog/ atau ^/contact">
                                    </td>
                                    <td style="text-align: center;">
                                        <button type="button" class="wpsc-btn-del" onclick="this.closest('tr').remove();">🗑️ Hapus</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                        <button type="button" class="wpsc-btn-ghost" onclick="wpscAddRuleRow()">
                            + Tambah Baris Aturan Baru
                        </button>
                        <button type="submit" name="wpsc_save_assets" class="wpsc-btn-primary">
                            💾 Simpan Seluruh Aturan Asset Unloader
                        </button>
                    </div>
                </div>
            </form>

            <script>
                function wpscAddRuleRow() {
                    const tbody = document.querySelector('#wpsc-rules-table tbody');
                    if (!tbody) return;
                    const idx = Date.now();
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>
                            <input type="text" name="wpsc_rules[${idx}][handle]" value="" class="wpsc-input" style="color: #00f2fe; font-weight: 700;" placeholder="misal: contact-form-7">
                        </td>
                        <td>
                            <select name="wpsc_rules[${idx}][type]" class="wpsc-select">
                                <option value="script">Script (JS)</option>
                                <option value="style">Style (CSS)</option>
                                <option value="both">Keduanya (CSS & JS)</option>
                            </select>
                        </td>
                        <td>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="wpsc_rules[${idx}][everywhere]" value="1">
                                Matikan Semua
                            </label>
                        </td>
                        <td>
                            <input type="text" name="wpsc_rules[${idx}][url_match]" value="" class="wpsc-input" placeholder="misal: /blog/ atau ^/contact">
                        </td>
                        <td style="text-align: center;">
                            <button type="button" class="wpsc-btn-del" onclick="this.closest('tr').remove();">🗑️ Hapus</button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                }
            </script>
        </div>
        <?php
    }
}