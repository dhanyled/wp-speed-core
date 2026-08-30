<?php
declare(strict_types=1);

namespace WPSpeedCore\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use WP_Mock;
use WPSpeedCore\Database\DbCleaner;
use WPSpeedCore\Engine\Logger;

class DbCleanerTest extends TestCase {

    public function setUp(): void {
        WP_Mock::setUp();
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
        unset($GLOBALS['wpdb']);
    }

    public function test_run_cleanup_deletes_records(): void {
        global $wpdb;

        $wpdb = \Mockery::mock('stdClass');
        $wpdb->posts = 'wp_posts';
        $wpdb->options = 'wp_options';
        $wpdb->comments = 'wp_comments';

        $wpdb->shouldReceive('get_col')
            ->once()
            ->with("SELECT ID FROM wp_posts WHERE post_type = 'revision' LIMIT 500")
            ->andReturn(['101', '102']);

        WP_Mock::userFunction('wp_delete_post_revision', [
            'args' => [101],
            'times' => 1,
            'return' => true,
        ]);
        WP_Mock::userFunction('wp_delete_post_revision', [
            'args' => [102],
            'times' => 1,
            'return' => true,
        ]);

        $wpdb->shouldReceive('prepare')
            ->once()
            ->andReturn("SELECT ID FROM wp_posts WHERE post_status = 'auto-draft'");

        $wpdb->shouldReceive('get_col')
            ->once()
            ->with("SELECT ID FROM wp_posts WHERE post_status = 'auto-draft'")
            ->andReturn(['201']);

        WP_Mock::userFunction('wp_delete_post', [
            'args' => [201, true],
            'times' => 1,
            'return' => true,
        ]);

        $wpdb->shouldReceive('prepare')
            ->once()
            ->andReturn("SELECT option_name FROM wp_options WHERE option_name LIKE '_transient_timeout_%'");

        $wpdb->shouldReceive('get_col')
            ->once()
            ->with("SELECT option_name FROM wp_options WHERE option_name LIKE '_transient_timeout_%'")
            ->andReturn(['_transient_timeout_my_transient']);

        WP_Mock::userFunction('delete_transient', [
            'args' => ['my_transient'],
            'times' => 1,
            'return' => true,
        ]);

        $wpdb->shouldReceive('get_col')
            ->once()
            ->with("SELECT comment_ID FROM wp_comments WHERE comment_approved = 'spam'")
            ->andReturn(['301']);

        WP_Mock::userFunction('wp_delete_comment', [
            'args' => [301, true],
            'times' => 1,
            'return' => true,
        ]);

        $wpdb->shouldReceive('get_col')
            ->once()
            ->with("SELECT comment_ID FROM wp_comments WHERE comment_approved = 'trash'")
            ->andReturn(['401']);

        WP_Mock::userFunction('wp_delete_comment', [
            'args' => [401, true],
            'times' => 1,
            'return' => true,
        ]);

        $cleaner = new DbCleaner(null);
        $stats = $cleaner->run_cleanup();

        $this->assertEquals([
            'revisions_deleted' => 2,
            'auto_drafts_deleted' => 1,
            'transients_deleted' => 1,
            'spam_comments_deleted' => 1,
            'trash_comments_deleted' => 1,
        ], $stats);
    }
}