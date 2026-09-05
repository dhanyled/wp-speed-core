<?php
declare(strict_types=1);

namespace WPSpeedCore\Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use WP_Mock;
use WPSpeedCore\Engine\MigrationManager;

class MigrationManagerTest extends TestCase {

    public function setUp(): void {
        WP_Mock::setUp();
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
    }

    public function test_get_available_migrations_detects_wp_rocket(): void {
        WP_Mock::userFunction('get_option')
            ->with('wp_rocket_settings')
            ->andReturn(['cache_mobile' => 1, 'delay_js' => 1]);

        WP_Mock::userFunction('get_option')
            ->with('perfmatters_options')
            ->andReturn(false);

        WP_Mock::userFunction('get_option')
            ->with('litespeed.conf')
            ->andReturn(false);

        WP_Mock::userFunction('get_option')
            ->with('litespeed-cache-conf')
            ->andReturn(false);

        $manager = new MigrationManager();
        $available = $manager->get_available_migrations();

        $this->assertArrayHasKey('wp_rocket', $available);
        $this->assertSame('WP Rocket', $available['wp_rocket']['name']);
        $this->assertSame(2, $available['wp_rocket']['count']);
    }

    public function test_import_settings_from_perfmatters(): void {
        WP_Mock::userFunction('get_option')
            ->with('wpsc_settings', [])
            ->andReturn([]);

        WP_Mock::userFunction('get_option')
            ->with('perfmatters_options')
            ->andReturn([
                'lazy_loading'      => '1',
                'delay_js'          => '1',
                'disable_emojis'    => '1',
                'speculation_rules' => '1',
            ]);

        WP_Mock::userFunction('update_option')
            ->with('wpsc_settings', WP_Mock\Functions::type('array'))
            ->andReturn(true);

        WP_Mock::userFunction('__')
            ->andReturnUsing(static fn($str) => $str);

        $manager = new MigrationManager();
        $result  = $manager->import_settings('perfmatters');

        $this->assertTrue($result['success']);
        $this->assertGreaterThanOrEqual(4, $result['imported_count']);
    }
}
