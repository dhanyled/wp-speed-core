<?php
declare(strict_types=1);

namespace WPSpeedCore;

if (!defined('ABSPATH')) {
    exit;
}

final class Kernel {
    private static ?Kernel $instance = null;
    private array $registry = [];

    public static function launch(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get(string $id): ?object {
        return $this->registry[$id] ?? null;
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
        $logger = new Engine\Logger();
        $env    = new Engine\EnvironmentScanner();

        $this->registry['logger']    = $logger;
        $this->registry['env']       = $env;
        $this->registry['tuner']     = new Engine\AdaptiveTuner($env, $logger);
        $this->registry['arbiter']   = new Engine\OverlapArbiter($env);
        $this->registry['auditor']   = new Engine\TagAuditor($logger);
        $this->registry['mcp']       = new Engine\McpServer();
        $this->registry['checklist'] = new Engine\PerformanceChecklist();
        $this->registry['migration'] = new Engine\MigrationManager($logger);
        $this->registry['updater']   = new Engine\GitHubUpdater('dhanyled/wp-speed-core', WPSC_BASENAME, WPSC_VERSION, $logger);
    }

    private function boot_optimizations(): void {
        $logger = $this->registry['logger'];

        $this->registry['bloat']   = new Optimization\BloatSuppressor();
        $this->registry['script']  = new Optimization\ScriptController();
        $this->registry['style']   = new Optimization\StyleController();
        $this->registry['media']   = new Optimization\MediaController();
        $this->registry['fonts']   = new Optimization\FontController();
        $this->registry['preload'] = new Optimization\SpeculationEngine();
        $this->registry['assets']  = new Optimization\AssetGatekeeper();
        $this->registry['cdn']     = new Optimization\CdnRewriter();
        $this->registry['cloudflare']   = new Optimization\CloudflarePurger($logger);
        $this->registry['db']      = new Optimization\DatabaseHousekeeper($logger);
        $this->registry['media_facade'] = new Optimization\MediaFacadeOptimizer();
        $this->registry['font_opt']     = new Optimization\FontOptimizer();
        $this->registry['db_cleaner']   = new Database\DbCleaner($logger);

        $ps_service = new PageSpeed\PageSpeedService();
        $this->registry['pagespeed_service']    = $ps_service;
        $this->registry['pagespeed_controller'] = new PageSpeed\PageSpeedController($ps_service);
    }

    private function boot_cache(): void {
        $logger = $this->registry['logger'];
        $this->registry['cache'] = new Cache\HtmlCacheEngine($logger);
    }

    private function boot_admin(): void {
        if (is_admin()) {
            $this->registry['dashboard'] = new Admin\Dashboard($this->registry);
            $this->registry['assetui']   = new Admin\AssetManagerPanel();
        }
        $this->registry['admin_bar'] = new Admin\AdminBar($this->registry);
    }
}
