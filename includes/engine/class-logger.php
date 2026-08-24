<?php
declare(strict_types=1);

namespace WPSpeedCore\Engine;

if (!defined('ABSPATH')) {
    exit;
}

class Logger {
    private string $log_file;
    private string $log_dir;
    private const MAX_FILE_SIZE = 1048576; // 1 MB

    public function __construct() {
        $this->log_dir  = WPSC_CACHE_DIR . 'logs/';
        $this->log_file = $this->log_dir . 'debug.log';
        $this->ensure_log_dir();
    }

    private function ensure_log_dir(): void {
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
        }

        $htaccess = $this->log_dir . '.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents(
                $htaccess,
                "Order deny,allow\nDeny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n"
            );
        }

        $index = $this->log_dir . 'index.php';
        if (!file_exists($index)) {
            @file_put_contents($index, "<?php\n// Silence is golden.\n");
        }
    }

    public function log(string $level, string $message, array $context = []): void {
        $this->rotate_if_needed();

        $timestamp = gmdate('Y-m-d H:i:s');
        $ctx_str   = !empty($context) ? ' ' . wp_json_encode($context, JSON_UNESCAPED_SLASHES) : '';
        $entry     = sprintf("[%s UTC] [%s] %s%s\n", $timestamp, strtoupper($level), $message, $ctx_str);

        @file_put_contents($this->log_file, $entry, FILE_APPEND | LOCK_EX);
    }

    public function info(string $message, array $context = []): void {
        $this->log('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void {
        $this->log('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void {
        $this->log('ERROR', $message, $context);
    }

    public function log_system_snapshot(EnvironmentScanner $scanner): void {
        $env = $scanner->get();
        $this->info('System & Server Diagnostic Snapshot', [
            'wp_version'      => $env['wordpress']['version'] ?? 'Unknown',
            'php_version'     => $env['php']['version'] ?? PHP_VERSION,
            'opcache'         => !empty($env['php']['opcache']) ? 'Active' : 'Disabled',
            'jit'             => !empty($env['php']['jit']) ? 'Active' : 'Disabled',
            'memory_limit'    => $env['php']['memory_limit'] ?? 'Unknown',
            'server_software' => $env['server']['software'] ?? 'Unknown',
            'block_theme'     => !empty($env['wordpress']['is_block_theme']),
            'tag_processor'   => !empty($env['wordpress']['has_tag_processor']),
            'active_plugins'  => count($env['active_plugins'] ?? []),
        ]);
    }

    public function get_logs(int $max_lines = 150): string {
        if (!file_exists($this->log_file)) {
            return 'Belum ada log tercatat.';
        }

        $lines = @file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return 'Log kosong.';
        }

        if (count($lines) > $max_lines) {
            $lines = array_slice($lines, -$max_lines);
        }

        return implode("\n", array_reverse($lines));
    }

    public function clear(): bool {
        if (file_exists($this->log_file)) {
            $res = @unlink($this->log_file);
            $this->info('Log berkas telah dibersihkan oleh Administrator.');
            return $res;
        }
        return true;
    }

    private function rotate_if_needed(): void {
        if (file_exists($this->log_file) && filesize($this->log_file) > self::MAX_FILE_SIZE) {
            $backup = $this->log_dir . 'debug-' . gmdate('Ymd-His') . '.log.bak';
            @rename($this->log_file, $backup);
            $this->cleanup_old_backups();
        }
    }

    private function cleanup_old_backups(): void {
        $files = glob($this->log_dir . '*.log.bak');
        if ($files && count($files) > 3) {
            usort($files, static fn($a, $b) => filemtime($a) <=> filemtime($b));
            while (count($files) > 3) {
                $old = array_shift($files);
                if ($old && is_file($old)) {
                    @unlink($old);
                }
            }
        }
    }
}
