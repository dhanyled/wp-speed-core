<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (!function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): bool {
        return true;
    }
}

require_once dirname(__DIR__) . '/includes/engine/class-logger.php';
require_once dirname(__DIR__) . '/includes/optimization/class-database-housekeeper.php';
