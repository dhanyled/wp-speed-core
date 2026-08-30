<?php
declare(strict_types=1);

namespace WPSpeedCore\Tests;

use PHPUnit\Framework\TestCase;
use WP_Mock;
use WPSpeedCore\Database\DbCleaner;
use WPSpeedCore\Engine\Logger;

class DbCleanerTest extends TestCase {

    public function setUp(): void {
        parent::setUp();
        WP_Mock::setUp();
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    public function test_run_cleanup_with_all_items_cleaned_and_logger(): void {
        global $wpdb;

        $wpdb = \Mockery::mock('stdClass');
        $wpdb->posts = 'wp_posts';
        $wpdb->options = 'wp_options';
        $wpdb->comments = 'wp_comments';

        // 1. Revisions
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

        // 2. Auto-drafts
        $wpdb->shouldReceive('prepare')
            ->once()
            ->with(
                "SELECT ID FROM wp_posts WHERE post_status = 'auto-draft' AND post_date < %s",
                \Mockery::type('string')
            )
            ->andReturn("SELECT ID FROM wp_posts WHERE post_status = 'auto-draft' AND post_date < '2026-02-23'");

        $wpdb->shouldReceive('get_col')
            ->once()
            ->with("SELECT ID FROM wp_posts WHERE post_status = 'auto-draft' AND post_date < '2026-02-23'")
            ->andReturn(['201']);

        WP_Mock::userFunction('wp_delete_post', [
            'args' => [201, true],
            'times' => 1,
            'return' => true,
        ]);

        // 3. Expired transients
        $wpdb->shouldReceive('prepare')
            ->once()
            ->with(
                "SELECT option_name FROM wp_options WHERE option_name LIKE %s AND option_value < %d",
                '_transient_timeout_%',
                \Mockery::type('int')
            )
            ->andReturn("SELECT option_name FROM wp_options WHERE option_name LIKE '_transient_timeout_%' AND option_value < 1000");

        $wpdb->shouldReceive('get_col')
            ->once()
            ->with("SELECT option_name FROM wp_options WHERE option_name LIKE '_transient_timeout_%' AND option_value < 1000")
            ->andReturn(['_transient_timeout_my_transient']);

        WP_Mock::userFunction('delete_transient', [
            'args' => ['my_transient'],
            'times' => 1,
            'return' => true,
        ]);

        // 4. Spam & Trash comments
        $wpdb->shouldReceive('get_col')
            ->once()
            ->with("SELECT comment_ID FROM wp_comments WHERE comment_approved = 'spam'")
            ->andReturn(['301', '302', '303']);

        WP_Mock::userFunction('wp_delete_comment', [
            'args' => [301, true],
            'times' => 1,
            'return' => true,
        ]);
        WP_Mock::userFunction('wp_delete_comment', [
            'args' => [302, true],
            'times' => 1,
            'return' => true,
        ]);
        WP_Mock::userFunction('wp_delete_comment', [
            'args' => [303, true],
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

        // Mock Logger
        $logger = \Mockery::mock(Logger::class);
        $logger->shouldReceive('info')
            ->once()
            ->with('1-Click DB Cleanup executed: Revisions (2), AutoDrafts (1), Transients (1), Spam (3), Trash (1)');

        $cleaner = new DbCleaner($logger);
        $stats = $cleaner->run_cleanup();

        $this->assertEquals([
            'revisions_deleted' => 2,
            'auto_drafts_deleted' => 1,
            'transients_deleted' => 1,
            'spam_comments_deleted' => 3,
            'trash_comments_deleted' => 1,
        ], $stats);
    }

    public function test_run_cleanup_when_nothing_to_clean_and_no_logger(): void {
        global $wpdb;

        $wpdb = \Mockery::mock('stdClass');
        $wpdb->posts = 'wp_posts';
        $wpdb->options = 'wp_options';
        $wpdb->comments = 'wp_comments';

        // 1. Revisions empty
        $wpdb->shouldReceive('get_col')
            ->once()
            ->with("SELECT ID FROM wp_posts WHERE post_type = 'revision' LIMIT 500")
            ->andReturn([]);

        // 2. Auto-drafts empty
        $wpdb->shouldReceive('prepare')
            ->once()
            ->andReturn("SELECT ID FROM wp_posts WHERE post_status = 'auto-draft'");

        $wpdb->shouldReceive('get_col')
            ->once()
            ->with("SELECT ID FROM wp_posts WHERE post_status = 'auto-draft'")
            ->andReturn([]);

        // 3. Transients empty
        $wpdb->shouldReceive('prepare')
            ->once()
            ->andReturn("SELECT option_name FROM wp_options WHERE option_name LIKE '_transient_timeout_%'");

        $wpdb->shouldReceive('get_col')
            ->once()
            ->with("SELECT option_name FROM wp_options WHERE option_name LIKE '_transient_timeout_%'")
            ->andReturn(null);

        // 4. Spam comments empty
        $wpdb->shouldReceive('get_col')
            ->once()
            ->with("SELECT comment_ID FROM wp_comments WHERE comment_approved = 'spam'")
            ->andReturn([]);

        // Trash comments empty
        $wpdb->shouldReceive('get_col')
            ->once()
            ->with("SELECT comment_ID FROM wp_comments WHERE comment_approved = 'trash'")
            ->andReturn([]);

        $cleaner = new DbCleaner(null);
        $stats = $cleaner->run_cleanup();

        $this->assertEquals([
            'revisions_deleted' => 0,
            'auto_drafts_deleted' => 0,
            'transients_deleted' => 0,
            'spam_comments_deleted' => 0,
            'trash_comments_deleted' => 0,
        ], $stats);
    }

    public function test_run_cleanup_handles_null_db_responses(): void {
        global $wpdb;

        $wpdb = \Mockery::mock('stdClass');
        $wpdb->posts = 'wp_posts';
        $wpdb->options = 'wp_options';
        $wpdb->comments = 'wp_comments';

        $wpdb->shouldReceive('get_col')->andReturn(null);
        $wpdb->shouldReceive('prepare')->andReturn('PREPARED_QUERY');

        $logger = \Mockery::mock(Logger::class);
        $logger->shouldReceive('info')
            ->once()
            ->with('1-Click DB Cleanup executed: Revisions (0), AutoDrafts (0), Transients (0), Spam (0), Trash (0)');

        $cleaner = new DbCleaner($logger);
        $stats = $cleaner->run_cleanup();

        $this->assertEquals([
            'revisions_deleted' => 0,
            'auto_drafts_deleted' => 0,
            'transients_deleted' => 0,
            'spam_comments_deleted' => 0,
            'trash_comments_deleted' => 0,
        ], $stats);
    }

    public function test_run_cleanup_transient_key_extraction(): void {
        global $wpdb;

        $wpdb = \Mockery::mock('stdClass');
        $wpdb->posts = 'wp_posts';
        $wpdb->options = 'wp_options';
        $wpdb->comments = 'wp_comments';

        $wpdb->shouldReceive('get_col')
            ->with("SELECT ID FROM wp_posts WHERE post_type = 'revision' LIMIT 500")
            ->andReturn([]);

        $wpdb->shouldReceive('prepare')
            ->with(
                "SELECT ID FROM wp_posts WHERE post_status = 'auto-draft' AND post_date < %s",
                \Mockery::type('string')
            )
            ->andReturn("SELECT ID FROM wp_posts WHERE post_status = 'auto-draft'");

        $wpdb->shouldReceive('get_col')
            ->with("SELECT ID FROM wp_posts WHERE post_status = 'auto-draft'")
            ->andReturn([]);

        $wpdb->shouldReceive('prepare')
            ->with(
                "SELECT option_name FROM wp_options WHERE option_name LIKE %s AND option_value < %d",
                '_transient_timeout_%',
                \Mockery::type('int')
            )
            ->andReturn("SELECT option_name FROM wp_options WHERE option_name LIKE '_transient_timeout_%'");

        $wpdb->shouldReceive('get_col')
            ->with("SELECT option_name FROM wp_options WHERE option_name LIKE '_transient_timeout_%'")
            ->andReturn(['_transient_timeout_custom_feed_cache', '_transient_timeout_api_token']);

        WP_Mock::userFunction('delete_transient', [
            'args' => ['custom_feed_cache'],
            'times' => 1,
            'return' => true,
        ]);

        WP_Mock::userFunction('delete_transient', [
            'args' => ['api_token'],
            'times' => 1,
            'return' => true,
        ]);

        $wpdb->shouldReceive('get_col')
            ->with("SELECT comment_ID FROM wp_comments WHERE comment_approved = 'spam'")
            ->andReturn([]);

        $wpdb->shouldReceive('get_col')
            ->with("SELECT comment_ID FROM wp_comments WHERE comment_approved = 'trash'")
            ->andReturn([]);

        $cleaner = new DbCleaner(null);
        $stats = $cleaner->run_cleanup();

        $this->assertEquals(2, $stats['transients_deleted']);
    }
}
