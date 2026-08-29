<?php
declare(strict_types=1);

namespace WPSpeedCore\Admin;

use WPSpeedCore\Kernel;

if (!defined('ABSPATH')) {
    exit;
}

class AdminBar {
    private array $modules;

    public function __construct(array $modules) {
        $this->modules = $modules;
        add_action('admin_bar_menu', [$this, 'add_nodes'], 999);
    }

    public function add_nodes(\WP_Admin_Bar $wp_admin_bar): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $bypassed     = Kernel::is_bypassed();
        $title_status = $bypassed ? ' [Bypass Mode]' : '';

        // Main Node
        $wp_admin_bar->add_node([
            'id'    => 'wpsc_admin_bar',
            'title' => '<span class="ab-icon" style="color:#38bdf8; font-weight:bold;">&bull;</span><span class="ab-label">WP Speed Core' . esc_html($title_status) . '</span>',
            'href'  => admin_url('options-general.php?page=wp-speed-core'),
            'meta'  => ['title' => 'WP Speed Core Optimizer HUD'],
        ]);

        $host        = sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'] ?? ''));
        $uri         = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? '/'));
        $current_url = (is_ssl() ? 'https://' : 'http://') . $host . $uri;
        $clean_url   = strtok($current_url, '?');

        // Test with Bypass (?nowpsc=1) or Normal
        if ($bypassed) {
            $normal_url = remove_query_arg(['nowpsc', 'wpsc_bypass'], $current_url);
            $wp_admin_bar->add_node([
                'parent' => 'wpsc_admin_bar',
                'id'     => 'wpsc_toggle_bypass',
                'title'  => 'Aktifkan Optimasi (Mode Normal)',
                'href'   => esc_url($normal_url),
            ]);
        } else {
            $bypass_url = add_query_arg('nowpsc', '1', $current_url);
            $wp_admin_bar->add_node([
                'parent' => 'wpsc_admin_bar',
                'id'     => 'wpsc_toggle_bypass',
                'title'  => 'Test Tanpa Optimasi (?nowpsc=1)',
                'href'   => esc_url($bypass_url),
                'meta'   => ['title' => 'Bypass cache & JS delay untuk membandingkan kecepatan atau troubleshooting'],
            ]);
        }

        // Quick Purge Current Page Cache
        if (!is_admin() && $clean_url) {
            $purge_url = wp_nonce_url(
                add_query_arg(['wpsc_purge_url' => urlencode((string) $clean_url)], admin_url('options-general.php?page=wp-speed-core')),
                'wpsc_purge_single_url'
            );
            $wp_admin_bar->add_node([
                'parent' => 'wpsc_admin_bar',
                'id'     => 'wpsc_purge_current',
                'title'  => 'Purge Cache Halaman Ini',
                'href'   => esc_url($purge_url),
            ]);
        }

        // Performance Checklist Link
        $wp_admin_bar->add_node([
            'parent' => 'wpsc_admin_bar',
            'id'     => 'wpsc_checklist_link',
            'title'  => 'Performance Checklist',
            'href'   => admin_url('options-general.php?page=wp-speed-core&tab=checklist'),
        ]);

        // Asset Manager Link
        $wp_admin_bar->add_node([
            'parent' => 'wpsc_admin_bar',
            'id'     => 'wpsc_asset_manager_link',
            'title'  => 'Asset Unloader Manager',
            'href'   => admin_url('options-general.php?page=wpsc-asset-manager'),
        ]);

        // Dashboard Settings
        $wp_admin_bar->add_node([
            'parent' => 'wpsc_admin_bar',
            'id'     => 'wpsc_settings_link',
            'title'  => 'Pengaturan WP Speed Core',
            'href'   => admin_url('options-general.php?page=wp-speed-core'),
        ]);
    }
}
