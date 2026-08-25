<?php
declare(strict_types=1);

namespace WPSpeedCore\Admin;

if (!defined('ABSPATH')) {
    exit;
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
            'WPSC Asset Manager',
            'WPSC Asset Unloader',
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
            $rules_input = $_POST['wpsc_assets'] ?? [];
            $clean_rules = [];

            if (is_array($rules_input)) {
                foreach ($rules_input as $handle => $config) {
                    $handle_clean = preg_replace('/[^a-zA-Z0-9_\.-]/', '', (string) $handle);
                    if ($handle_clean === '') {
                        continue;
                    }

                    $type       = sanitize_text_field($config['type'] ?? 'script');
                    $everywhere = !empty($config['everywhere']) ? 1 : 0;
                    $url_match  = sanitize_text_field($config['url_match'] ?? '');

                    if ($everywhere || $url_match !== '') {
                        $clean_rules[$handle_clean] = [
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
                    $clean_rules[$custom_handle] = [
                        'type'       => $custom_type,
                        'everywhere' => $custom_everywhere,
                        'url_match'  => $custom_url_match,
                    ];
                }
            }

            update_option('wpsc_disabled_assets', $clean_rules);
            wp_safe_redirect(add_query_arg(['page' => 'wpsc-asset-manager', 'updated' => '1'], admin_url('options-general.php')));
            exit;
        }
    }

    public function render_panel(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Akses ditolak.', 'wp-speed-core'));
        }

        $rules = (array) get_option('wpsc_disabled_assets', []);
        ?>
        <div class="wrap">
            <h1>⚡ WP Speed Core - Asset Unloader Manager</h1>
            <p>Kelola penonaktifan CSS/JS yang tidak terpakai secara global atau berdasarkan pencocokan URL untuk mempercepat loading halaman.</p>

            <?php if (isset($_GET['updated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>Aturan penonaktifan aset berhasil disimpan!</strong></p>
                </div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field('wpsc_asset_manager_nonce'); ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 200px;">Handle Aset (CSS / JS)</th>
                            <th style="width: 100px;">Tipe</th>
                            <th style="width: 150px;">Nonaktifkan Global</th>
                            <th>Target URL Match (Regex / Path)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sample_handles = array_unique(array_merge(
                            array_keys($rules),
                            ['jquery-migrate', 'wp-block-library', 'classic-theme-styles', 'global-styles', 'contact-form-7']
                        ));

                        foreach ($sample_handles as $handle):
                            $curr = $rules[$handle] ?? [];
                            $type = $curr['type'] ?? (strpos($handle, 'style') !== false || strpos($handle, 'css') !== false ? 'style' : 'script');
                            $everywhere = !empty($curr['everywhere']);
                            $url_match  = $curr['url_match'] ?? '';
                        ?>
                            <tr>
                                <td><strong><?php echo esc_html($handle); ?></strong></td>
                                <td>
                                    <select name="wpsc_assets[<?php echo esc_attr($handle); ?>][type]">
                                        <option value="script" <?php selected($type, 'script'); ?>>Script (JS)</option>
                                        <option value="style" <?php selected($type, 'style'); ?>>Style (CSS)</option>
                                    </select>
                                </td>
                                <td>
                                    <label>
                                        <input type="checkbox" name="wpsc_assets[<?php echo esc_attr($handle); ?>][everywhere]" value="1" <?php checked($everywhere); ?>>
                                        Matikan Semua
                                    </label>
                                </td>
                                <td>
                                    <input type="text" name="wpsc_assets[<?php echo esc_attr($handle); ?>][url_match]" value="<?php echo esc_attr($url_match); ?>" class="regular-text" placeholder="misal: /blog/ atau ^/contact">
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <tr style="background: rgba(0,242,254,0.05);">
                            <td>
                                <strong>Tambah Custom Handle:</strong><br>
                                <input type="text" name="wpsc_custom_handle" placeholder="misal: font-awesome.css" class="regular-text">
                            </td>
                            <td>
                                <select name="wpsc_custom_type">
                                    <option value="script">Script (JS)</option>
                                    <option value="style">Style (CSS)</option>
                                </select>
                            </td>
                            <td>
                                <label>
                                    <input type="checkbox" name="wpsc_custom_everywhere" value="1">
                                    Matikan Semua
                                </label>
                            </td>
                            <td>
                                <input type="text" name="wpsc_custom_url_match" class="regular-text" placeholder="misal: /blog/ atau ^/contact">
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <button type="submit" name="wpsc_save_assets" class="button button-primary">Simpan Aturan Asset Unloader</button>
                </p>
            </form>
        </div>
        <?php
    }
}
