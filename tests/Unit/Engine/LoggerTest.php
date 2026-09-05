<?php
declare(strict_types=1);

namespace WPSpeedCore\Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use WP_Mock;
use WPSpeedCore\Engine\Logger;

class LoggerTest extends TestCase {

    public function setUp(): void {
        WP_Mock::setUp();
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
    }

    public function test_logger_instantiation_ensures_directory(): void {
        WP_Mock::userFunction('wp_mkdir_p')
            ->andReturn(true);

        $logger = new Logger();
        $this->assertInstanceOf(Logger::class, $logger);
    }
}