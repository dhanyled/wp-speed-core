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
        $this->log_file = $this->log_dir . 'debug.log.php';
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

        if (!file_exists($this->log_file) || filesize($this->log_file) === 0) {
            @file_put_contents($this->log_file, "<?php die(); ?>\n", LOCK_EX);
        }

        $timestamp = function_exists('wp_date') ? wp_date('Y-m-d H:i:s') : (function_exists('date_i18n') ? date_i18n('Y-m-d H:i:s') : gmdate('Y-m-d H:i:s'));
        $ctx_str   = !empty($context) ? ' ' . wp_json_encode($context, JSON_UNESCAPED_SLASHES) : '';
        $clean_msg = $this->sanitize_log_text($message);
        $clean_ctx = $this->sanitize_log_text($ctx_str);
        $entry     = sprintf("[%s] [%s] %s%s\n", $timestamp, strtoupper($level), $clean_msg, $clean_ctx);

        @file_put_contents($this->log_file, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Sanitize log text to prevent PHP execution injection and log forging.
     *
     * @param string $text Raw text input.
     * @return string Sanitized text safe for single-line log storage.
     */
    private function sanitize_log_text(string $text): string {
        $text = str_replace("\0", "", $text);
        $text = str_replace(["<?php", "<?=", "<?", "?>"], ["&lt;?php", "&lt;?=", "&lt;?", "?&gt;"], $text);
        $text = str_replace(["\r\n", "\r", "\n"], " ", $text);
        return $text;
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

    /**
     * Get recent log entries as an array of strings.
     *
     * @param int $max_lines Maximum number of lines to return.
     * @return array
     */
    public function get_recent(int $max_lines = 50): array {
        if (!file_exists($this->log_file)) {
            return [];
        }

        $lines = @file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return [];
        }

        $lines = array_filter($lines, static function (string $line): bool {
            return strpos($line, '<?php') === false;
        });

        if (count($lines) > $max_lines) {
            $lines = array_slice($lines, -$max_lines);
        }

        return array_values(array_reverse($lines));
    }

    public function get_logs(int $max_lines = 150): string {
        $recent = $this->get_recent($max_lines);
        if (empty($recent)) {
            return 'Belum ada log tercatat.';
        }
        return implode("\n", $recent);
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
            $backup = $this->log_dir . 'debug-' . gmdate('Ymd-His') . '.log.php';
            @rename($this->log_file, $backup);
            $this->cleanup_old_backups();
        }
    }

    private function cleanup_old_backups(): void {
        $files = glob($this->log_dir . 'debug-*.log.php');
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
