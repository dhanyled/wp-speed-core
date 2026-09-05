<?php
declare(strict_types=1);

namespace WPSpeedCore;

if (!defined('ABSPATH')) {
    exit;
}

final class Kernel {
    private static ?Kernel $instance = null;
    private array $registry = [];

    private static array $boot_errors = [];

    public static function launch(): ?self {
        try {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        } catch (\Throwable $e) {
            self::record_boot_error('kernel', $e);
            return self::$instance;
        }
    }

    public function get(string $id): ?object {
        return $this->registry[$id] ?? null;
    }

    public static function get_boot_errors(): array {
        return self::$boot_errors;
    }

    private static function record_boot_error(string $module_id, \Throwable $e, ?Engine\Logger $logger = null): void {
        self::$boot_errors[$module_id] = $e->getMessage();

        if ($logger) {
            try {
                $logger->error(sprintf('Kernel Guardrail caught failure in [%s]: %s', $module_id, $e->getMessage()), [
                    'module' => $module_id,
                    'file'   => $e->getFile(),
                    'line'   => $e->getLine(),
                ]);
            } catch (\Throwable $log_err) {
                // Failsafe if logger itself fails
            }
        }

        if (is_admin()) {
            add_action('admin_notices', static function () use ($module_id, $e) {
                if (!current_user_can('manage_options')) {
                    return;
                }
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p><strong>[WP Speed Core SafeGuard]</strong> Modul <code>' . esc_html($module_id) . '</code> gagal diinisialisasi dan dinonaktifkan sementara untuk mencegah layar putih (WSOD):</p>';
                echo '<p style="font-family:monospace; color:#c026d3; font-size:12px;">' . esc_html($e->getMessage()) . '</p>';
                echo '</div>';
            });
        }
    }

    /**
     * Safely register a module into Kernel registry with fault isolation.
     */
    private function register_safe(string $id, callable $factory): void {
        try {
            $logger = $this->registry['logger'] ?? null;
            $instance = $factory();
            if (is_object($instance)) {
                $this->registry[$id] = $instance;
            }
        } catch (\Throwable $e) {
            $logger = $this->registry['logger'] ?? null;
            self::record_boot_error($id, $e, $logger);
        }
    }

    public static function is_bypassed(): bool {
        static $bypassed = null;
        if ($bypassed !== null) {
            return $bypassed;
        }

        $bypassed = (isset($_GET['nowpsc']) && $_GET['nowpsc'] === '1') ||
                    (isset($_GET['wpsc_bypass']) && $_GET['wpsc_bypass'] === '1') ||
                    self::is_page_builder_editor();

        if ($bypassed && !headers_sent()) {
            header('X-WPSC-Bypass: 1');
        }

        return $bypassed;
    }

    /**
     * Check if a visual page builder editor/preview canvas is currently active.
     * Guarantees 100% compatibility with Elementor (v3 & v4), Bricks Builder, Divi, etc.
     */
    public static function is_page_builder_editor(): bool {
        // Elementor v3 & v4 editor and preview canvas
        if (isset($_GET['elementor-preview']) || (isset($_GET['action']) && $_GET['action'] === 'elementor')) {
            return true;
        }
        if (class_exists('\Elementor\Plugin')) {
            $plugin = \Elementor\Plugin::$instance;
            if (isset($plugin->preview) && method_exists($plugin->preview, 'is_preview_mode') && $plugin->preview->is_preview_mode()) {
                return true;
            }
            if (isset($plugin->editor) && method_exists($plugin->editor, 'is_edit_mode') && $plugin->editor->is_edit_mode()) {
                return true;
            }
        }

        // Bricks Builder editor canvas
        if (isset($_GET['bricks']) && $_GET['bricks'] === 'run') {
            return true;
        }
        if (function_exists('bricks_is_builder') && bricks_is_builder()) {
            return true;
        }
        if (function_exists('bricks_is_builder_call') && bricks_is_builder_call()) {
            return true;
        }

        // Divi Builder preview
        if (isset($_GET['et_fb'])) {
            return true;
        }

        // Beaver Builder preview
        if (isset($_GET['fl_builder'])) {
            return true;
        }

        return false;
    }

    private function __construct() {
        $this->boot_engine();
        $this->boot_optimizations();
        $this->boot_cache();
        $this->boot_admin();
    }

    private function boot_engine(): void {
        $this->register_safe('logger', static function () {
            return new Engine\Logger();
        });

        $this->register_safe('env', static function () {
            return new Engine\EnvironmentScanner();
        });

        $this->register_safe('tuner', function () {
            $env    = $this->registry['env'] ?? new Engine\EnvironmentScanner();
            $logger = $this->registry['logger'] ?? null;
            return new Engine\AdaptiveTuner($env, $logger);
        });

        $this->register_safe('arbiter', function () {
            $env = $this->registry['env'] ?? new Engine\EnvironmentScanner();
            return new Engine\OverlapArbiter($env);
        });

        $this->register_safe('auditor', function () {
            $logger = $this->registry['logger'] ?? null;
            return new Engine\TagAuditor($logger);
        });

        $this->register_safe('mcp', static function () {
            return new Engine\McpServer();
        });

        $this->register_safe('checklist', static function () {
            return new Engine\PerformanceChecklist();
        });

        $this->register_safe('migration', function () {
            $logger = $this->registry['logger'] ?? null;
            return new Engine\MigrationManager($logger);
        });

        if (class_exists(Engine\GitHubUpdater::class)) {
            $this->register_safe('updater', function () {
                $logger = $this->registry['logger'] ?? null;
                return new Engine\GitHubUpdater('dhanyled/wp-speed-core', WPSC_BASENAME, WPSC_VERSION, $logger);
            });
        }
    }

    private function boot_optimizations(): void {
        $this->register_safe('bloat', static function () {
            return new Optimization\BloatSuppressor();
        });

        $this->register_safe('script', static function () {
            return new Optimization\ScriptController();
        });

        $this->register_safe('style', static function () {
            return new Optimization\StyleController();
        });

        $this->register_safe('media', static function () {
            return new Optimization\MediaController();
        });

        $this->register_safe('fonts', static function () {
            return new Optimization\FontController();
        });

        $this->register_safe('preload', static function () {
            return new Optimization\SpeculationEngine();
        });

        $this->register_safe('assets', static function () {
            return new Optimization\AssetGatekeeper();
        });

        $this->register_safe('cdn', static function () {
            return new Optimization\CdnRewriter();
        });

        $this->register_safe('cloudflare', function () {
            $logger = $this->registry['logger'] ?? null;
            return new Optimization\CloudflarePurger($logger);
        });

        $this->register_safe('db', function () {
            $logger = $this->registry['logger'] ?? null;
            return new Optimization\DatabaseHousekeeper($logger);
        });

        $this->register_safe('media_facade', static function () {
            return new Optimization\MediaFacadeOptimizer();
        });

        $this->register_safe('font_opt', static function () {
            return new Optimization\FontOptimizer();
        });

        $this->register_safe('db_cleaner', function () {
            $logger = $this->registry['logger'] ?? null;
            return new Database\DbCleaner($logger);
        });

        $this->register_safe('pagespeed_service', static function () {
            return new PageSpeed\PageSpeedService();
        });

        $this->register_safe('pagespeed_controller', function () {
            $ps_service = $this->registry['pagespeed_service'] ?? new PageSpeed\PageSpeedService();
            return new PageSpeed\PageSpeedController($ps_service);
        });
    }

    private function boot_cache(): void {
        $this->register_safe('cache', function () {
            $logger = $this->registry['logger'] ?? null;
            return new Cache\HtmlCacheEngine($logger);
        });
    }

    private function boot_admin(): void {
        if (is_admin()) {
            $this->register_safe('dashboard', function () {
                return new Admin\Dashboard($this->registry);
            });

            $this->register_safe('assetui', static function () {
                return new Admin\AssetManagerPanel();
            });
        }

        $this->register_safe('admin_bar', function () {
            return new Admin\AdminBar($this->registry);
        });
    }
}
