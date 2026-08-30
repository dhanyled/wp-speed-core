<?php
declare(strict_types=1);

namespace WPSpeedCore\Tests\Unit\Optimization;

use Mockery;
use WP_Mock;
use WP_Mock\Matcher\AnyInstance;
use WP_Mock\Tools\TestCase;
use WPSpeedCore\Engine\Logger;
use WPSpeedCore\Optimization\DatabaseHousekeeper;

class DatabaseHousekeeperTest extends TestCase {

    public function test_constructor_registers_maintenance_action(): void {
        WP_Mock::expectActionAdded('wpsc_maintenance', [ new AnyInstance(DatabaseHousekeeper::class), 'maintain' ]);

        $housekeeper = new DatabaseHousekeeper();
        $this->assertInstanceOf(DatabaseHousekeeper::class, $housekeeper);
    }

    public function test_trim_revisions_executes_correct_query(): void {
        WP_Mock::expectActionAdded('wpsc_maintenance', [ new AnyInstance(DatabaseHousekeeper::class), 'maintain' ]);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->posts = 'wp_posts';

        $expected_sql = "DELETE FROM wp_posts WHERE post_type = 'revision' AND post_status = 'inherit'";

        $wpdb->shouldReceive('prepare')
            ->once()
            ->with("DELETE FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s", 'revision', 'inherit')
            ->andReturn($expected_sql);

        $wpdb->shouldReceive('query')
            ->once()
            ->with($expected_sql)
            ->andReturn(5);

        $GLOBALS['wpdb'] = $wpdb;

        $housekeeper = new DatabaseHousekeeper();
        $result = $housekeeper->trim_revisions();

        $this->assertSame(5, $result);
    }

    public function test_trim_drafts_executes_correct_query(): void {
        WP_Mock::expectActionAdded('wpsc_maintenance', [ new AnyInstance(DatabaseHousekeeper::class), 'maintain' ]);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->posts = 'wp_posts';

        $expected_sql = "DELETE FROM wp_posts WHERE post_status = 'auto-draft'";

        $wpdb->shouldReceive('prepare')
            ->once()
            ->with("DELETE FROM {$wpdb->posts} WHERE post_status = %s", 'auto-draft')
            ->andReturn($expected_sql);

        $wpdb->shouldReceive('query')
            ->once()
            ->with($expected_sql)
            ->andReturn(3);

        $GLOBALS['wpdb'] = $wpdb;

        $housekeeper = new DatabaseHousekeeper();
        $result = $housekeeper->trim_drafts();

        $this->assertSame(3, $result);
    }

    public function test_trim_trash_executes_correct_query(): void {
        WP_Mock::expectActionAdded('wpsc_maintenance', [ new AnyInstance(DatabaseHousekeeper::class), 'maintain' ]);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->posts = 'wp_posts';

        $expected_sql = "DELETE FROM wp_posts WHERE post_status = 'trash'";

        $wpdb->shouldReceive('prepare')
            ->once()
            ->with("DELETE FROM {$wpdb->posts} WHERE post_status = %s", 'trash')
            ->andReturn($expected_sql);

        $wpdb->shouldReceive('query')
            ->once()
            ->with($expected_sql)
            ->andReturn(12);

        $GLOBALS['wpdb'] = $wpdb;

        $housekeeper = new DatabaseHousekeeper();
        $result = $housekeeper->trim_trash();

        $this->assertSame(12, $result);
    }

    public function test_trim_spam_executes_correct_query(): void {
        WP_Mock::expectActionAdded('wpsc_maintenance', [ new AnyInstance(DatabaseHousekeeper::class), 'maintain' ]);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->comments = 'wp_comments';

        $expected_sql = "DELETE FROM wp_comments WHERE comment_approved IN ('spam', 'trash')";

        $wpdb->shouldReceive('prepare')
            ->once()
            ->with("DELETE FROM {$wpdb->comments} WHERE comment_approved IN (%s, %s)", 'spam', 'trash')
            ->andReturn($expected_sql);

        $wpdb->shouldReceive('query')
            ->once()
            ->with($expected_sql)
            ->andReturn(7);

        $GLOBALS['wpdb'] = $wpdb;

        $housekeeper = new DatabaseHousekeeper();
        $result = $housekeeper->trim_spam();

        $this->assertSame(7, $result);
    }

    public function test_trim_transients_executes_correct_query(): void {
        WP_Mock::expectActionAdded('wpsc_maintenance', [ new AnyInstance(DatabaseHousekeeper::class), 'maintain' ]);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->options = 'wp_options';

        $wpdb->shouldReceive('esc_like')
            ->once()
            ->with('_transient_timeout_')
            ->andReturn('\_transient\_timeout\_');

        $expected_sql = "DELETE FROM wp_options WHERE option_name LIKE '\_transient\_timeout\_%' AND CAST(option_value AS UNSIGNED) < 1700000000";

        $wpdb->shouldReceive('prepare')
            ->once()
            ->with(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d",
                '\_transient\_timeout\_%',
                Mockery::type('int')
            )
            ->andReturn($expected_sql);

        $wpdb->shouldReceive('query')
            ->once()
            ->with($expected_sql)
            ->andReturn(20);

        $GLOBALS['wpdb'] = $wpdb;

        $housekeeper = new DatabaseHousekeeper();
        $result = $housekeeper->trim_transients();

        $this->assertSame(20, $result);
    }

    public function test_optimize_tables_optimizes_all_tables_and_logs(): void {
        WP_Mock::expectActionAdded('wpsc_maintenance', [ new AnyInstance(DatabaseHousekeeper::class), 'maintain' ]);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->shouldReceive('get_col')
            ->once()
            ->with('SHOW TABLES')
            ->andReturn(['wp_posts', 'wp_options', 'wp_users']);

        $wpdb->shouldReceive('query')
            ->once()
            ->with("OPTIMIZE TABLE `wp_posts`");
        $wpdb->shouldReceive('query')
            ->once()
            ->with("OPTIMIZE TABLE `wp_options`");
        $wpdb->shouldReceive('query')
            ->once()
            ->with("OPTIMIZE TABLE `wp_users`");

        $GLOBALS['wpdb'] = $wpdb;

        $logger = Mockery::mock(Logger::class);
        $logger->shouldReceive('info')
            ->once()
            ->with('Database tables optimization completed.', ['tables_optimized' => 3]);

        $housekeeper = new DatabaseHousekeeper($logger);
        $result = $housekeeper->optimize_tables();

        $this->assertSame(3, $result);
    }

    public function test_optimize_tables_handles_empty_tables(): void {
        WP_Mock::expectActionAdded('wpsc_maintenance', [ new AnyInstance(DatabaseHousekeeper::class), 'maintain' ]);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->shouldReceive('get_col')
            ->once()
            ->with('SHOW TABLES')
            ->andReturn([]);

        $GLOBALS['wpdb'] = $wpdb;

        $logger = Mockery::mock(Logger::class);
        $logger->shouldReceive('info')
            ->once()
            ->with('Database tables optimization completed.', ['tables_optimized' => 0]);

        $housekeeper = new DatabaseHousekeeper($logger);
        $result = $housekeeper->optimize_tables();

        $this->assertSame(0, $result);
    }

    public function test_maintain_runs_all_trims_and_logs_results(): void {
        WP_Mock::expectActionAdded('wpsc_maintenance', [ new AnyInstance(DatabaseHousekeeper::class), 'maintain' ]);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->posts = 'wp_posts';
        $wpdb->comments = 'wp_comments';
        $wpdb->options = 'wp_options';

        $wpdb->shouldReceive('esc_like')
            ->once()
            ->with('_transient_timeout_')
            ->andReturn('\_transient\_timeout\_');

        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('query')->andReturn(1, 2, 3, 4, 5);

        $GLOBALS['wpdb'] = $wpdb;

        $logger = Mockery::mock(Logger::class);
        $logger->shouldReceive('info')
            ->once()
            ->with('Scheduled DB Housekeeping completed.', [
                'revisions_pruned' => 1,
                'drafts_cleared'   => 2,
                'trash_emptied'    => 3,
                'spam_removed'     => 4,
                'expired_trans'    => 5,
            ]);

        $housekeeper = new DatabaseHousekeeper($logger);
        $housekeeper->maintain();

        $this->addToAssertionCount(1);
    }
}
