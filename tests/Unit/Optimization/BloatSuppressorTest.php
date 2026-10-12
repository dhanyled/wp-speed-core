<?php
declare(strict_types=1);

namespace WPSpeedCore\Tests\Unit\Optimization;

use PHPUnit\Framework\TestCase;
use WP_Mock;
use WPSpeedCore\Optimization\BloatSuppressor;

class BloatSuppressorTest extends TestCase {

    public function setUp(): void {
        WP_Mock::setUp();
        $_GET = [];
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
        $_GET = [];
    }

    public function test_disable_user_rest_endpoint_unsets_endpoints_for_logged_out_users(): void {
        WP_Mock::userFunction('get_option')
            ->with('wpsc_settings', [])
            ->andReturn([]);

        WP_Mock::userFunction('is_user_logged_in')
            ->andReturn(false);

        $suppressor = new BloatSuppressor();

        $endpoints = [
            '/wp/v2/posts'                     => [],
            '/wp/v2/users'                     => [],
            '/wp/v2/users/(?P<id>[\d]+)'      => [],
        ];

        $filtered = $suppressor->disable_user_rest_endpoint($endpoints);

        $this->assertArrayNotHasKey('/wp/v2/users', $filtered);
        $this->assertArrayNotHasKey('/wp/v2/users/(?P<id>[\d]+)', $filtered);
        $this->assertArrayHasKey('/wp/v2/posts', $filtered);
    }

    public function test_disable_user_rest_endpoint_retains_endpoints_for_logged_in_users(): void {
        WP_Mock::userFunction('get_option')
            ->with('wpsc_settings', [])
            ->andReturn([]);

        WP_Mock::userFunction('is_user_logged_in')
            ->andReturn(true);

        $suppressor = new BloatSuppressor();

        $endpoints = [
            '/wp/v2/posts'                     => [],
            '/wp/v2/users'                     => [],
            '/wp/v2/users/(?P<id>[\d]+)'      => [],
        ];

        $filtered = $suppressor->disable_user_rest_endpoint($endpoints);

        $this->assertArrayHasKey('/wp/v2/users', $filtered);
        $this->assertArrayHasKey('/wp/v2/users/(?P<id>[\d]+)', $filtered);
        $this->assertArrayHasKey('/wp/v2/posts', $filtered);
    }

    public function test_strip_pingback_removes_x_pingback_header(): void {
        WP_Mock::userFunction('get_option')
            ->with('wpsc_settings', [])
            ->andReturn([]);

        $suppressor = new BloatSuppressor();

        $headers = [
            'Content-Type' => 'text/html',
            'X-Pingback'   => 'https://example.com/xmlrpc.php',
        ];

        $cleaned = $suppressor->strip_pingback($headers);

        $this->assertArrayNotHasKey('X-Pingback', $cleaned);
        $this->assertArrayHasKey('Content-Type', $cleaned);
    }

    public function test_strip_gutenberg_bloat_dequeues_editor_scripts_on_public_frontend(): void {
        WP_Mock::userFunction('get_option')
            ->with('wpsc_settings', [])
            ->andReturn([]);

        WP_Mock::userFunction('is_admin')->andReturn(false);
        WP_Mock::userFunction('is_customize_preview')->andReturn(false);

        WP_Mock::userFunction('wp_dequeue_script')
            ->with('wp-block-editor')
            ->once();
        WP_Mock::userFunction('wp_deregister_script')
            ->with('wp-block-editor')
            ->once();

        // Allow any other calls to dequeue/deregister
        WP_Mock::userFunction('wp_dequeue_script')->zeroOrMoreTimes();
        WP_Mock::userFunction('wp_deregister_script')->zeroOrMoreTimes();

        $suppressor = new BloatSuppressor();
        $suppressor->strip_gutenberg_bloat();
        $this->assertTrue(true);
    }

    public function test_strip_gutenberg_styles_dequeues_editor_styles_on_public_frontend(): void {
        WP_Mock::userFunction('get_option')
            ->with('wpsc_settings', [])
            ->andReturn([]);

        WP_Mock::userFunction('is_admin')->andReturn(false);
        WP_Mock::userFunction('is_customize_preview')->andReturn(false);

        WP_Mock::userFunction('wp_dequeue_style')
            ->with('wp-block-editor')
            ->once();
        WP_Mock::userFunction('wp_deregister_style')
            ->with('wp-block-editor')
            ->once();

        WP_Mock::userFunction('wp_dequeue_style')->zeroOrMoreTimes();
        WP_Mock::userFunction('wp_deregister_style')->zeroOrMoreTimes();

        $suppressor = new BloatSuppressor();
        $suppressor->strip_gutenberg_styles();
        $this->assertTrue(true);
    }
}