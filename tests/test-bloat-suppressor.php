<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use WPSpeedCore\Optimization\BloatSuppressor;

class BloatSuppressorTest {
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void {
        echo "Running BloatSuppressor Unit Tests...\n";
        echo "-------------------------------------\n";

        $methods = get_class_methods($this);
        foreach ($methods as $method) {
            if (str_starts_with($method, 'test_')) {
                $this->setUp();
                try {
                    $this->$method();
                    echo "  [PASS] {$method}\n";
                    $this->passed++;
                } catch (\Throwable $e) {
                    echo "  [FAIL] {$method}: {$e->getMessage()}\n";
                    echo "         {$e->getFile()}:{$e->getLine()}\n";
                    $this->failed++;
                }
            }
        }

        echo "-------------------------------------\n";
        echo "Summary: {$this->passed} passed, {$this->failed} failed.\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }

    private function setUp(): void {
        $GLOBALS['wp_stubs']['is_user_logged_in'] = false;
        $GLOBALS['wp_stubs']['is_admin'] = false;
        $GLOBALS['wp_stubs']['options'] = [];
    }

    private function assertSame($expected, $actual, string $message = ''): void {
        if ($expected !== $actual) {
            $expectedStr = var_export($expected, true);
            $actualStr = var_export($actual, true);
            throw new \AssertionError($message ?: "Expected {$expectedStr}, got {$actualStr}");
        }
    }

    public function test_disable_user_rest_endpoint_when_logged_out(): void {
        $GLOBALS['wp_stubs']['is_user_logged_in'] = false;
        $suppressor = new BloatSuppressor();

        $endpoints = [
            '/wp/v2/posts' => ['GET'],
            '/wp/v2/users' => ['GET'],
            '/wp/v2/users/(?P<id>[\d]+)' => ['GET'],
            '/wp/v2/comments' => ['GET'],
        ];

        $filtered = $suppressor->disable_user_rest_endpoint($endpoints);

        $expected = [
            '/wp/v2/posts' => ['GET'],
            '/wp/v2/comments' => ['GET'],
        ];

        $this->assertSame($expected, $filtered, "User REST endpoints should be disabled for logged-out users.");
    }

    public function test_disable_user_rest_endpoint_when_logged_in(): void {
        $GLOBALS['wp_stubs']['is_user_logged_in'] = true;
        $suppressor = new BloatSuppressor();

        $endpoints = [
            '/wp/v2/posts' => ['GET'],
            '/wp/v2/users' => ['GET'],
            '/wp/v2/users/(?P<id>[\d]+)' => ['GET'],
            '/wp/v2/comments' => ['GET'],
        ];

        $filtered = $suppressor->disable_user_rest_endpoint($endpoints);

        $this->assertSame($endpoints, $filtered, "User REST endpoints should remain enabled for logged-in users.");
    }

    public function test_disable_user_rest_endpoint_without_user_endpoints(): void {
        $GLOBALS['wp_stubs']['is_user_logged_in'] = false;
        $suppressor = new BloatSuppressor();

        $endpoints = [
            '/wp/v2/posts' => ['GET'],
            '/wp/v2/pages' => ['GET'],
        ];

        $filtered = $suppressor->disable_user_rest_endpoint($endpoints);

        $this->assertSame($endpoints, $filtered, "Endpoints should remain unchanged if user endpoints are not present.");
    }

    public function test_disable_user_rest_endpoint_empty_array(): void {
        $GLOBALS['wp_stubs']['is_user_logged_in'] = false;
        $suppressor = new BloatSuppressor();

        $filtered = $suppressor->disable_user_rest_endpoint([]);

        $this->assertSame([], $filtered, "Empty array input should return empty array without errors.");
    }
}

$testRunner = new BloatSuppressorTest();
$testRunner->run();
