<?php
declare(strict_types=1);

namespace WPSpeedCore\CLI;

use WP_CLI;
use WPSpeedCore\Kernel;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WP Speed Core CLI Commands.
 */
class CLICommand {
    /**
     * Purge all static disk HTML cache.
     *
     * ## EXAMPLES
     *
     *     wp wpsc purge
     *
     * @when shutdown
     */
    public function purge(array $args, array $assoc_args): void {
        do_action('wpsc_purge_all');
        WP_CLI::success('WP Speed Core HTML Cache cleared successfully.');
    }

    /**
     * Run 1-Click Auto-Tune Engine.
     *
     * ## EXAMPLES
     *
     *     wp wpsc autotune
     *
     * @when shutdown
     */
    public function autotune(array $args, array $assoc_args): void {
        $kernel = Kernel::launch();
        $tuner  = $kernel->get('tuner');

        if ($tuner && method_exists($tuner, 'apply')) {
            $tuner->apply();
            WP_CLI::success('WP Speed Core Auto-Tune applied successfully.');
        } else {
            WP_CLI::error('Auto-Tune engine is unavailable.');
        }
    }

    /**
     * Execute Database Housekeeper and optimize database tables.
     *
     * ## EXAMPLES
     *
     *     wp wpsc db-clean
     *
     * @when shutdown
     */
    public function db_clean(array $args, array $assoc_args): void {
        $kernel = Kernel::launch();
        $db     = $kernel->get('db');

        if ($db && method_exists($db, 'maintain')) {
            $db->maintain();
            if (method_exists($db, 'optimize_tables')) {
                $db->optimize_tables();
            }
            WP_CLI::success('Database housekeeping and table optimization completed.');
        } else {
            WP_CLI::error('Database Housekeeper is unavailable.');
        }
    }

    /**
     * Display WP Speed Core engine status and server stack diagnostics.
     *
     * ## EXAMPLES
     *
     *     wp wpsc status
     *
     * @when shutdown
     */
    public function status(array $args, array $assoc_args): void {
        $kernel = Kernel::launch();
        $env    = $kernel->get('env') ? $kernel->get('env')->get() : [];

        WP_CLI::line('-----------------------------------------');
        WP_CLI::line('⚡ WP Speed Core Status & Diagnostics');
        WP_CLI::line('-----------------------------------------');
        WP_CLI::line('WordPress Version: ' . ($env['wordpress']['version'] ?? 'Unknown'));
        WP_CLI::line('PHP Version:       ' . ($env['php']['version'] ?? PHP_VERSION));
        WP_CLI::line('Web Server:        ' . ($env['server']['software'] ?? 'Unknown'));
        WP_CLI::line('OPcache Status:    ' . (!empty($env['php']['opcache']) ? 'Active' : 'Disabled'));
        WP_CLI::line('JIT Status:        ' . (!empty($env['php']['jit']) ? 'Active' : 'Disabled'));
        WP_CLI::line('Memory Limit:      ' . ($env['php']['memory_limit'] ?? 'Unknown'));
        WP_CLI::line('-----------------------------------------');
    }
}
