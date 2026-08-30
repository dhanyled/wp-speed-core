<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/test-database-housekeeper.php';

use WPSpeedCore\Tests\TestDatabaseHousekeeper;

$test = new TestDatabaseHousekeeper();

$methods = [
    'test_optimize_tables_with_multiple_tables',
    'test_optimize_tables_with_no_tables',
    'test_optimize_tables_when_tables_null_or_false',
];

$passed = 0;
$failed = 0;

foreach ($methods as $method) {
    try {
        $test->$method();
        echo "[PASS] $method\n";
        $passed++;
    } catch (\Throwable $e) {
        echo "[FAIL] $method: " . $e->getMessage() . "\n";
        echo $e->getTraceAsString() . "\n";
        $failed++;
    }
}

echo "\nSummary: $passed passed, $failed failed.\n";
if ($failed > 0) {
    exit(1);
}
