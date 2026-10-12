<?php
/**
 * Plugin Name:       WP Speed Core
 * Plugin URI:        https://github.com/dhanyled/wp-speed-core
 * Description:       All-in-one WordPress performance engine with AI Model Context Protocol (MCP), Adaptive Auto-Tuning, Tracking Conflict Inspector, Overlap Arbiter, Disk HTML Cache, INP-safe script delay, auto-LCP priority, Speculation Rules prerender, Contextual Asset Unloader, and DB housekeeping.
 * Version:           1.6.6
 * Requires at least: 6.2
 * Tested up to:      6.8
 * Requires PHP:      8.0
 * Author:            Dhany (@leddhany)
 * Author URI:        https://t.me/leddhany
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-speed-core
 * Domain Path:       /languages
 */

declare(strict_types=1);

namespace WPSpeedCore;

use WP_CLI;

if (!defined('ABSPATH')) {
    exit;
}

define('WPSC_VERSION', '1.6.6');
define('WPSC_FILE', __FILE__);
define('WPSC_PATH', plugin_dir_path(__FILE__));
define('WPSC_URL', plugin_dir_url(__FILE__));
define('WPSC_BASENAME', plugin_basename(__FILE__));
define('WPSC_CACHE_DIR', WP_CONTENT_DIR . '/cache/wp-speed-core/');

spl_autoload_register(static function (string $fqcn) {
    $ns = __NAMESPACE__ . '\\';
    if (strpos($fqcn, $ns) !== 0) {
        return;
    }
    $rel = substr($fqcn, strlen($ns));
    $parts = explode('\\', $rel);
    $class_name = array_pop($parts);
    $kebab = strtolower((string) preg_replace('/(?<=[a-z0-9])([A-Z])|(?<=[A-Z])([A-Z][a-z])/', '-$1$2', $class_name));
    $file = 'class-' . str_replace('_', '-', $kebab) . '.php';
    $sub = $parts ? strtolower(implode(DIRECTORY_SEPARATOR, $parts)) . DIRECTORY_SEPARATOR : '';
    $path = WPSC_PATH . 'includes/' . $sub . $file;
    if (file_exists($path)) {
        require_once $path;
        return;
    }
    $alt_file = 'class-' . str_replace('page-speed', 'pagespeed', $kebab) . '.php';
    $alt_path = WPSC_PATH . 'includes/' . $sub . $alt_file;
    if (file_exists($alt_path)) {
        require_once $alt_path;
        return;
    }
});

register_activation_hook(__FILE__, static function () {
    try {
        Bootstrap::activate();
    } catch (\Throwable $e) {
        // Safe fallback: don't white-screen WordPress during activation
        error_log('[WP Speed Core Activation Error] ' . $e->getMessage());
    }
});

register_deactivation_hook(__FILE__, static function () {
    try {
        Bootstrap::deactivate();
    } catch (\Throwable $e) {
        error_log('[WP Speed Core Deactivation Error] ' . $e->getMessage());
    }
});

add_action('plugins_loaded', static function () {
    try {
        Kernel::launch();

        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            WP_CLI::add_command('wpsc', \WPSpeedCore\CLI\CLICommand::class);
        }
    } catch (\Throwable $e) {
        error_log('[WP Speed Core Kernel Launch Error] ' . $e->getMessage());
        if (is_admin()) {
            add_action('admin_notices', static function () use ($e) {
                if (!current_user_can('manage_options')) {
                    return;
                }
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p><strong>[WP Speed Core SafeGuard]</strong> Terjadi kesalahan saat memuat modul kernel: ' . esc_html($e->getMessage()) . '</p>';
                echo '</div>';
            });
        }
    }
}, 5);

// ── Plugin page UI hooks ──────────────────────────────────────────────────────
// Registered directly in main file (not via Kernel/GitHubUpdater) to guarantee
// these links always appear on wp-admin/plugins.php regardless of Kernel state.
add_filter('plugin_action_links_' . WPSC_BASENAME, static function (array $links): array {
    $settings_url  = admin_url('admin.php?page=wp-speed-core');
    $settings_link = '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'wp-speed-core') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

add_filter('plugin_row_meta', static function (array $links, string $file): array {
    if ($file !== WPSC_BASENAME) {
        return $links;
    }

    $repo_url     = 'https://github.com/dhanyled/wp-speed-core';
    $changelog_url = $repo_url . '/blob/main/CHANGELOG.md';

    // "View details" — opens thickbox modal via plugins_api
    $details_url = add_query_arg([
        'tab'       => 'plugin-information',
        'plugin'    => 'wp-speed-core',
        'section'   => 'changelog',
        'TB_iframe' => 'true',
        'width'     => 772,
        'height'    => 490,
    ], admin_url('plugin-install.php'));

    $links[] = '<a href="' . esc_url($details_url) . '" class="thickbox open-plugin-details-modal" aria-label="'
             . esc_attr__('View details for WP Speed Core', 'wp-speed-core') . '">'
             . esc_html__('View details', 'wp-speed-core') . '</a>';

    $links[] = '<a href="' . esc_url($changelog_url) . '" target="_blank" rel="noopener noreferrer" aria-label="'
             . esc_attr__('View changelog for WP Speed Core', 'wp-speed-core') . '">'
             . esc_html__('Changelog', 'wp-speed-core') . '</a>';

    return $links;
}, 10, 2);
