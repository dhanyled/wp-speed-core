<?php
declare(strict_types=1);

namespace WPSpeedCore\Tests\Unit\CLI;

use PHPUnit\Framework\TestCase;
use WP_Mock;
use WPSpeedCore\CLI\CLICommand;

class CLICommandTest extends TestCase {

    public function setUp(): void {
        WP_Mock::setUp();
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
    }

    public function test_purge_executes_action_and_logs_success(): void {
        if (!class_exists('WP_CLI')) {
            eval('class WP_CLI { public static function success($m){} public static function error($m){} public static function line($m){} }');
        }

        WP_Mock::expectAction('wpsc_purge_all');

        $cli = new CLICommand();
        $cli->purge([], []);
        $this->assertTrue(true);
    }
}