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

    private function __construct() {
        $this->boot_engine();
        $this->boot_optimizations();
        $this->boot_cache();
        $this->boot_admin();
    }

    private function boot_engine(): void {
        $logger = new Engine\Logger();
        $env    = new Engine\EnvironmentScanner();

        $this->registry['logger']  = $logger;
        $this->registry['env']     = $env;
        $this->registry['tuner']   = new Engine\AdaptiveTuner($env, $logger);
        $this->registry['arbiter'] = new Engine\OverlapArbiter($env);
        $this->registry['auditor'] = new Engine\TagAuditor($logger);
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
        $this->registry['db']      = new Optimization\DatabaseHousekeeper($logger);
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
    }
}
