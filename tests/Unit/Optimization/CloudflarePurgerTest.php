<?php
declare(strict_types=1);

namespace WPSpeedCore\Tests\Unit\Optimization;

use PHPUnit\Framework\TestCase;
use WP_Mock;
use WPSpeedCore\Optimization\CloudflarePurger;

class CloudflarePurgerTest extends TestCase {

    public function setUp(): void {
        WP_Mock::setUp();
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
    }

    public function test_is_enabled_returns_false_when_settings_empty(): void {
        WP_Mock::userFunction('get_option')
            ->with('wpsc_settings', [])
            ->andReturn([]);

        $purger = new CloudflarePurger();
        $this->assertFalse($purger->is_enabled());
    }

    public function test_is_enabled_returns_true_when_configured(): void {
        WP_Mock::userFunction('get_option')
            ->with('wpsc_settings', [])
            ->andReturn([
                'cloudflare' => [
                    'enable'    => 1,
                    'api_token' => 'test-token',
                    'zone_id'   => 'test-zone-id',
                ],
            ]);

        $purger = new CloudflarePurger();
        $this->assertTrue($purger->is_enabled());
    }

    public function test_test_connection_fails_when_missing_credentials(): void {
        WP_Mock::userFunction('get_option')
            ->with('wpsc_settings', [])
            ->andReturn([]);

        WP_Mock::userFunction('__')
            ->andReturnUsing(static fn($str) => $str);

        $purger = new CloudflarePurger();
        $result = $purger->test_connection();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('wajib diisi', $result['message']);
    }
}
