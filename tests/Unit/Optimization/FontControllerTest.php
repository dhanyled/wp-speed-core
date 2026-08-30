<?php
declare(strict_types=1);

namespace WPSpeedCore\Tests\Unit\Optimization;

use PHPUnit\Framework\TestCase;
use WP_Mock;
use WPSpeedCore\Optimization\FontController;

class FontControllerTest extends TestCase {

    public function setUp(): void {
        WP_Mock::setUp();
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
    }

    public function test_ensure_display_swap_injects_display_swap(): void {
        WP_Mock::userFunction('get_option')
            ->with('wpsc_settings', [])
            ->andReturn([
                'font' => ['swap_google_fonts' => true],
            ]);

        $controller = new FontController();

        $html = '<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:400,700">';
        $output = $controller->ensure_display_swap($html);

        $this->assertStringContainsString('&amp;display=swap', $output);
    }
}