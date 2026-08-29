<?php
declare(strict_types=1);

namespace WPSpeedCore\Tests;

use PHPUnit\Framework\TestCase;
use WPSpeedCore\Engine\McpServer;

class McpServerTest extends TestCase {
    private McpServer $server;

    protected function setUp(): void {
        parent::setUp();
        if (!defined('WPSC_VERSION')) {
            define('WPSC_VERSION', '1.5.2');
        }
        if (!defined('WPSC_CACHE_DIR')) {
            define('WPSC_CACHE_DIR', sys_get_temp_dir() . '/wpsc_cache_test/');
        }
        $this->server = new McpServer();
    }

    public function testGetToolDefinitionsReturnsExpectedStructure(): void {
        $response = $this->server->get_tool_definitions();
        $data = $response->get_data();

        $this->assertEquals('200', $response->get_status());
        $this->assertArrayHasKey('protocolVersion', $data);
        $this->assertArrayHasKey('tools', $data);
        $this->assertCount(8, $data['tools']);
    }

    public function testDispatchToolUnknownTool(): void {
        $reflection = new \ReflectionClass(McpServer::class);
        $method = $reflection->getMethod('dispatch_tool');
        $method->setAccessible(true);

        $result = $method->invoke($this->server, 'invalid_tool', []);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Unknown tool name', $result['error']);
    }

    public function testDispatchToolPurgeCacheWithInvalidHost(): void {
        $reflection = new \ReflectionClass(McpServer::class);
        $method = $reflection->getMethod('dispatch_tool');
        $method->setAccessible(true);

        // Simulated external host URL
        $result = $method->invoke($this->server, 'wpsc_purge_cache', ['url' => 'https://malicious-external-domain.com/path']);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Invalid host for purge operation.', $result['error']);
    }
}
