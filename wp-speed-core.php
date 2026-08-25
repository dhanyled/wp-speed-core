<?php
/**
 * Plugin Name:       WP Speed Core
 * Plugin URI:        https://t.me/leddhany
 * Description:       All-in-one WordPress performance & security acceleration engine with Adaptive Auto-Tuning, Disk HTML Cache, Gzip Pre-Compression, CDN Rewriter, WP-CLI commands, Asset Unloader, INP Shield script delay, auto-LCP priority, Speculation Rules prerender, and Security Hardening.
 * Version:           1.3.0
 * Requires at least: 6.2
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

if (!defined('ABSPATH')) {
    exit;
}

define('WPSC_VERSION', '1.3.0');
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
    }
});

register_activation_hook(__FILE__, static function () {
    Bootstrap::activate();
});

register_deactivation_hook(__FILE__, static function () {
    Bootstrap::deactivate();
});

add_action('plugins_loaded', static function () {
    Kernel::launch();

    if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
        WP_CLI::add_command('wpsc', \WPSpeedCore\CLI\CLICommand::class);
    }
}, 5);
