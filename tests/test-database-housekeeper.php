<?php
declare(strict_types=1);

namespace WPSpeedCore\Tests;

use WPSpeedCore\Optimization\DatabaseHousekeeper;
use WPSpeedCore\Engine\Logger;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

/**
 * Lightweight mock implementation for $wpdb to test DatabaseHousekeeper without external dependencies.
 */
class MockWpdb {
    public array $queries = [];
    public ?array $tablesToReturn = [];

    public function get_col(string $query): ?array {
        $this->queries[] = ['method' => 'get_col', 'query' => $query];
        return $this->tablesToReturn;
    }

    public function query(string $query): int {
        $this->queries[] = ['method' => 'query', 'query' => $query];
        return 1;
    }

    public function prepare(string $query, ...$args): string {
        return $query;
    }

    public function esc_like(string $text): string {
        return addcslashes($text, '_%\\');
    }
}

/**
 * Lightweight mock for Logger to record log messages in unit tests.
 */
class MockLogger extends Logger {
    public array $logs = [];

    public function __construct() {
        // Skip parent constructor to avoid filesystem initialization
    }

    public function info(string $message, array $context = []): void {
        $this->logs[] = [
            'level'   => 'info',
            'message' => $message,
            'context' => $context,
        ];
    }
}

class TestDatabaseHousekeeper {
    public function runAll(): void {
        $this->test_optimize_tables_with_multiple_tables();
        $this->test_optimize_tables_with_no_tables();
        $this->test_optimize_tables_when_tables_null_or_false();
    }

    private function assertSame($expected, $actual, string $message = ''): void {
        if ($expected !== $actual) {
            throw new \Exception(
                sprintf("Assertion failed: Expected %s, got %s. %s", var_export($expected, true), var_export($actual, true), $message)
            );
        }
    }

    private function assertCount(int $expectedCount, array $array, string $message = ''): void {
        if (count($array) !== $expectedCount) {
            throw new \Exception(
                sprintf("Assertion failed: Expected count %d, got %d. %s", $expectedCount, count($array), $message)
            );
        }
    }

    public function test_optimize_tables_with_multiple_tables(): void {
        global $wpdb;
        $mockWpdb = new MockWpdb();
        $mockWpdb->tablesToReturn = ['wp_posts', 'wp_options', 'wp_users'];
        $wpdb = $mockWpdb;

        $logger = new MockLogger();
        $housekeeper = new DatabaseHousekeeper($logger);

        $result = $housekeeper->optimize_tables();

        // 1. Assert return value matches count of tables optimized
        $this->assertSame(3, $result, 'optimize_tables should return the count of optimized tables');

        // 2. Assert $wpdb->get_col('SHOW TABLES') was called
        $this->assertSame('get_col', $mockWpdb->queries[0]['method']);
        $this->assertSame('SHOW TABLES', $mockWpdb->queries[0]['query']);

        // 3. Assert OPTIMIZE TABLE was called for each table
        $this->assertSame('query', $mockWpdb->queries[1]['method']);
        $this->assertSame('OPTIMIZE TABLE `wp_posts`', $mockWpdb->queries[1]['query']);
        $this->assertSame('OPTIMIZE TABLE `wp_options`', $mockWpdb->queries[2]['query']);
        $this->assertSame('OPTIMIZE TABLE `wp_users`', $mockWpdb->queries[3]['query']);

        // 4. Assert logger recorded info log with correct context
        $this->assertCount(1, $logger->logs);
        $this->assertSame('Database tables optimization completed.', $logger->logs[0]['message']);
        $this->assertSame(['tables_optimized' => 3], $logger->logs[0]['context']);
    }

    public function test_optimize_tables_with_no_tables(): void {
        global $wpdb;
        $mockWpdb = new MockWpdb();
        $mockWpdb->tablesToReturn = [];
        $wpdb = $mockWpdb;

        $logger = new MockLogger();
        $housekeeper = new DatabaseHousekeeper($logger);

        $result = $housekeeper->optimize_tables();

        // 1. Assert return value is 0
        $this->assertSame(0, $result);

        // 2. Assert only get_col query was made, no OPTIMIZE TABLE queries executed
        $this->assertCount(1, $mockWpdb->queries);
        $this->assertSame('SHOW TABLES', $mockWpdb->queries[0]['query']);

        // 3. Assert logger recorded info log with 0 count
        $this->assertCount(1, $logger->logs);
        $this->assertSame(['tables_optimized' => 0], $logger->logs[0]['context']);
    }

    public function test_optimize_tables_when_tables_null_or_false(): void {
        global $wpdb;
        $mockWpdb = new MockWpdb();
        $mockWpdb->tablesToReturn = null;
        $wpdb = $mockWpdb;

        $housekeeper = new DatabaseHousekeeper();

        $result = $housekeeper->optimize_tables();

        // Assert return value is 0 and no OPTIMIZE TABLE queries executed
        $this->assertSame(0, $result);
        $this->assertCount(1, $mockWpdb->queries);
    }
}
