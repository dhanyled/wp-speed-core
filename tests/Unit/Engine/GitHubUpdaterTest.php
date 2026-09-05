<?php
declare(strict_types=1);

namespace WPSpeedCore\Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use WP_Mock;
use WPSpeedCore\Engine\GitHubUpdater;

class GitHubUpdaterTest extends TestCase {

    public function setUp(): void {
        WP_Mock::setUp();
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
    }

    public function test_check_update_adds_response_when_newer_version_available(): void {
        WP_Mock::userFunction('get_transient')
            ->with('wpsc_github_release_data')
            ->andReturn([
                'version'      => '1.7.0',
                'tag_name'     => 'v1.7.0',
                'download_url' => 'https://github.com/dhanyled/wp-speed-core/releases/download/v1.7.0/wp-speed-core.zip',
                'changelog'    => 'New features',
                'published_at' => '2026-09-05T08:00:00Z',
                'url'          => 'https://github.com/dhanyled/wp-speed-core/releases/tag/v1.7.0',
                'requires'     => '6.2',
                'tested'       => '6.7',
                'requires_php' => '8.0',
            ]);

        $updater = new GitHubUpdater(
            'dhanyled/wp-speed-core',
            'wp-speed-core/wp-speed-core.php',
            '1.6.0'
        );

        $transient = new \stdClass();
        $transient->response = [];
        $transient->no_update = [];

        $result = $updater->check_update($transient);

        $this->assertArrayHasKey('wp-speed-core/wp-speed-core.php', $result->response);
        $this->assertSame('1.7.0', $result->response['wp-speed-core/wp-speed-core.php']->new_version);
        $this->assertArrayNotHasKey('wp-speed-core/wp-speed-core.php', $result->no_update);
    }

    public function test_check_update_adds_no_update_when_same_version(): void {
        WP_Mock::userFunction('get_transient')
            ->with('wpsc_github_release_data')
            ->andReturn([
                'version'      => '1.6.0',
                'tag_name'     => 'v1.6.0',
                'download_url' => 'https://github.com/dhanyled/wp-speed-core/releases/download/v1.6.0/wp-speed-core.zip',
                'changelog'    => 'Current features',
                'published_at' => '2026-09-05T08:00:00Z',
                'url'          => 'https://github.com/dhanyled/wp-speed-core/releases/tag/v1.6.0',
                'requires'     => '6.2',
                'tested'       => '6.7',
                'requires_php' => '8.0',
            ]);

        $updater = new GitHubUpdater(
            'dhanyled/wp-speed-core',
            'wp-speed-core/wp-speed-core.php',
            '1.6.0'
        );

        $transient = new \stdClass();
        $transient->response = [];
        $transient->no_update = [];

        $result = $updater->check_update($transient);

        $this->assertArrayHasKey('wp-speed-core/wp-speed-core.php', $result->no_update);
        $this->assertSame('1.6.0', $result->no_update['wp-speed-core/wp-speed-core.php']->new_version);
        $this->assertArrayNotHasKey('wp-speed-core/wp-speed-core.php', $result->response);
    }

    public function test_plugin_info_returns_correct_details(): void {
        WP_Mock::userFunction('get_transient')
            ->with('wpsc_github_release_data')
            ->andReturn([
                'version'      => '1.7.0',
                'tag_name'     => 'v1.7.0',
                'download_url' => 'https://github.com/dhanyled/wp-speed-core/releases/download/v1.7.0/wp-speed-core.zip',
                'changelog'    => 'Awesome release',
                'published_at' => '2026-09-05T08:00:00Z',
                'url'          => 'https://github.com/dhanyled/wp-speed-core/releases/tag/v1.7.0',
                'requires'     => '6.2',
                'tested'       => '6.7',
                'requires_php' => '8.0',
            ]);

        $updater = new GitHubUpdater(
            'dhanyled/wp-speed-core',
            'wp-speed-core/wp-speed-core.php',
            '1.6.0'
        );

        $args = (object)['slug' => 'wp-speed-core'];
        $info = $updater->plugin_info(false, 'plugin_information', $args);

        $this->assertInstanceOf(\stdClass::class, $info);
        $this->assertSame('WP Speed Core', $info->name);
        $this->assertSame('1.7.0', $info->version);
        $this->assertArrayHasKey('description', $info->sections);
        $this->assertArrayHasKey('changelog', $info->sections);
    }

    public function test_get_update_status_reports_correctly(): void {
        WP_Mock::userFunction('get_transient')
            ->with('wpsc_github_release_data')
            ->andReturn([
                'version'      => '1.7.0',
                'tag_name'     => 'v1.7.0',
                'download_url' => 'https://github.com/dhanyled/wp-speed-core/releases/download/v1.7.0/wp-speed-core.zip',
                'changelog'    => 'New features',
                'published_at' => '2026-09-05T08:00:00Z',
                'url'          => 'https://github.com/dhanyled/wp-speed-core/releases/tag/v1.7.0',
            ]);

        WP_Mock::userFunction('admin_url')
            ->andReturn('https://example.com/wp-admin/options-general.php?page=wp-speed-core&wpsc_check_update=1');

        WP_Mock::userFunction('self_admin_url')
            ->andReturn('https://example.com/wp-admin/update.php?action=upgrade-plugin');

        WP_Mock::userFunction('wp_nonce_url')
            ->andReturnUsing(function($action_url) {
                return $action_url . '&_wpnonce=test';
            });

        $updater = new GitHubUpdater(
            'dhanyled/wp-speed-core',
            'wp-speed-core/wp-speed-core.php',
            '1.6.0'
        );

        $status = $updater->get_update_status();

        $this->assertTrue($status['has_update']);
        $this->assertSame('1.6.0', $status['current_version']);
        $this->assertSame('1.7.0', $status['latest_version']);
    }
}
