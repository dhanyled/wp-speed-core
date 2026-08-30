<?php
declare(strict_types=1);

namespace WPSpeedCore\Tests\Unit\Optimization;

use PHPUnit\Framework\TestCase;
use WP_Mock;
use WPSpeedCore\Engine\Logger;
use WPSpeedCore\Optimization\DatabaseHousekeeper;

class DatabaseHousekeeperTest extends TestCase {

    public function setUp(): void {
        WP_Mock::setUp();
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
        unset($GLOBALS['wpdb']);
    }

    private function createWpdbMock() {
        return $this->getMockBuilder(\stdClass::class)
            ->addMethods(['prepare', 'query', 'esc_like', 'get_col'])
            ->getMock();
    }

    public function test_constructor_registers_maintenance_action(): void {
        WP_Mock::expectActionAdded(
            'wpsc_maintenance',
            [WP_Mock\Functions::type(DatabaseHousekeeper::class), 'maintain']
        );

        new DatabaseHousekeeper();
        $this->assertTrue(true);
    }

    public function test_trim_revisions_deletes_revisions_and_returns_affected_rows(): void {
        $wpdb = $this->createWpdbMock();
        $wpdb->posts = 'wp_posts';

        $expectedSql = "DELETE FROM wp_posts WHERE post_type = 'revision' AND post_status = 'inherit'";

        $wpdb->expects($this->once())
             ->method('prepare')
             ->with(
                 "DELETE FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
                 'revision',
                 'inherit'
             )
             ->willReturn($expectedSql);

        $wpdb->expects($this->once())
             ->method('query')
             ->with($expectedSql)
             ->willReturn(5);

        $GLOBALS['wpdb'] = $wpdb;

        $housekeeper = new DatabaseHousekeeper();
        $result = $housekeeper->trim_revisions();

        $this->assertSame(5, $result);
    }

    public function test_trim_revisions_handles_zero_rows_deleted(): void {
        $wpdb = $this->createWpdbMock();
        $wpdb->posts = 'wp_posts';

        $wpdb->method('prepare')->willReturn('DELETE QUERY');
        $wpdb->method('query')->willReturn(0);

        $GLOBALS['wpdb'] = $wpdb;

        $housekeeper = new DatabaseHousekeeper();
        $result = $housekeeper->trim_revisions();

        $this->assertSame(0, $result);
    }

    public function test_trim_revisions_casts_query_result_to_int(): void {
        $wpdb = $this->createWpdbMock();
        $wpdb->posts = 'wp_posts';

        $wpdb->method('prepare')->willReturn('DELETE QUERY');
        $wpdb->method('query')->willReturn('12');

        $GLOBALS['wpdb'] = $wpdb;

        $housekeeper = new DatabaseHousekeeper();
        $result = $housekeeper->trim_revisions();

        $this->assertSame(12, $result);
    }

    public function test_trim_revisions_handles_false_query_return(): void {
        $wpdb = $this->createWpdbMock();
        $wpdb->posts = 'wp_posts';

        $wpdb->method('prepare')->willReturn('DELETE QUERY');
        $wpdb->method('query')->willReturn(false);

        $GLOBALS['wpdb'] = $wpdb;

        $housekeeper = new DatabaseHousekeeper();
        $result = $housekeeper->trim_revisions();

        $this->assertSame(0, $result);
    }

    public function test_trim_drafts_deletes_auto_drafts(): void {
        $wpdb = $this->createWpdbMock();
        $wpdb->posts = 'wp_posts';

        $expectedSql = "DELETE FROM wp_posts WHERE post_status = 'auto-draft'";

        $wpdb->expects($this->once())
             ->method('prepare')
             ->with(
                 "DELETE FROM {$wpdb->posts} WHERE post_status = %s",
                 'auto-draft'
             )
             ->willReturn($expectedSql);

        $wpdb->expects($this->once())
             ->method('query')
             ->with($expectedSql)
             ->willReturn(3);

        $GLOBALS['wpdb'] = $wpdb;

        $housekeeper = new DatabaseHousekeeper();
        $result = $housekeeper->trim_drafts();

        $this->assertSame(3, $result);
    }

    public function test_trim_trash_deletes_trash_posts(): void {
        $wpdb = $this->createWpdbMock();
        $wpdb->posts = 'wp_posts';

        $expectedSql = "DELETE FROM wp_posts WHERE post_status = 'trash'";

        $wpdb->expects($this->once())
             ->method('prepare')
             ->with(
                 "DELETE FROM {$wpdb->posts} WHERE post_status = %s",
                 'trash'
             )
             ->willReturn($expectedSql);

        $wpdb->expects($this->once())
             ->method('query')
             ->with($expectedSql)
             ->willReturn(2);

        $GLOBALS['wpdb'] = $wpdb;

        $housekeeper = new DatabaseHousekeeper();
        $result = $housekeeper->trim_trash();

        $this->assertSame(2, $result);
    }

    public function test_trim_spam_deletes_spam_and_trash_comments(): void {
        $wpdb = $this->createWpdbMock();
        $wpdb->comments = 'wp_comments';

        $expectedSql = "DELETE FROM wp_comments WHERE comment_approved IN ('spam', 'trash')";

        $wpdb->expects($this->once())
             ->method('prepare')
             ->with(
                 "DELETE FROM {$wpdb->comments} WHERE comment_approved IN (%s, %s)",
                 'spam',
                 'trash'
             )
             ->willReturn($expectedSql);

        $wpdb->expects($this->once())
             ->method('query')
             ->with($expectedSql)
             ->willReturn(10);

        $GLOBALS['wpdb'] = $wpdb;

        $housekeeper = new DatabaseHousekeeper();
        $result = $housekeeper->trim_spam();

        $this->assertSame(10, $result);
    }

    public function test_trim_transients_deletes_expired_transients(): void {
        $wpdb = $this->createWpdbMock();
        $wpdb->options = 'wp_options';

        $wpdb->expects($this->once())
             ->method('esc_like')
             ->with('_transient_timeout_')
             ->willReturn('_transient_timeout_');

        $wpdb->expects($this->once())
             ->method('prepare')
             ->with(
                 "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d",
                 '_transient_timeout_%',
                 $this->isType('int')
             )
             ->willReturn('DELETE TRANSIENTS SQL');

        $wpdb->expects($this->once())
             ->method('query')
             ->with('DELETE TRANSIENTS SQL')
             ->willReturn(4);

        $GLOBALS['wpdb'] = $wpdb;

        $housekeeper = new DatabaseHousekeeper();
        $result = $housekeeper->trim_transients();

        $this->assertSame(4, $result);
    }

    public function test_optimize_tables_optimizes_all_tables_and_logs(): void {
        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())
               ->method('info')
               ->with(
                   'Database tables optimization completed.',
                   ['tables_optimized' => 2]
               );

        $wpdb = $this->createWpdbMock();
        $wpdb->expects($this->once())
             ->method('get_col')
             ->with('SHOW TABLES')
             ->willReturn(['wp_posts', 'wp_options']);

        $wpdb->expects($this->exactly(2))
             ->method('query')
             ->withConsecutive(
                 ["OPTIMIZE TABLE `wp_posts`"],
                 ["OPTIMIZE TABLE `wp_options`"]
             );

        $GLOBALS['wpdb'] = $wpdb;

        $housekeeper = new DatabaseHousekeeper($logger);
        $result = $housekeeper->optimize_tables();

        $this->assertSame(2, $result);
    }

    public function test_optimize_tables_handles_empty_tables(): void {
        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())
               ->method('info')
               ->with(
                   'Database tables optimization completed.',
                   ['tables_optimized' => 0]
               );

        $wpdb = $this->createWpdbMock();
        $wpdb->expects($this->once())
             ->method('get_col')
             ->with('SHOW TABLES')
             ->willReturn(null);

        $wpdb->expects($this->never())->method('query');

        $GLOBALS['wpdb'] = $wpdb;

        $housekeeper = new DatabaseHousekeeper($logger);
        $result = $housekeeper->optimize_tables();

        $this->assertSame(0, $result);
    }

    public function test_maintain_calls_all_trim_methods_and_logs(): void {
        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())
               ->method('info')
               ->with(
                   'Scheduled DB Housekeeping completed.',
                   [
                       'revisions_pruned' => 1,
                       'drafts_cleared'   => 1,
                       'trash_emptied'    => 1,
                       'spam_removed'     => 1,
                       'expired_trans'    => 1,
                   ]
               );

        $wpdb = $this->createWpdbMock();
        $wpdb->posts = 'wp_posts';
        $wpdb->comments = 'wp_comments';
        $wpdb->options = 'wp_options';

        $wpdb->method('prepare')->willReturn('DUMMY SQL');
        $wpdb->method('query')->willReturn(1);
        $wpdb->method('esc_like')->willReturn('_transient_timeout_');

        $GLOBALS['wpdb'] = $wpdb;

        $housekeeper = new DatabaseHousekeeper($logger);
        $housekeeper->maintain();
    }

    public function test_maintain_works_without_logger(): void {
        $wpdb = $this->createWpdbMock();
        $wpdb->posts = 'wp_posts';
        $wpdb->comments = 'wp_comments';
        $wpdb->options = 'wp_options';

        $wpdb->method('prepare')->willReturn('DUMMY SQL');
        $wpdb->method('query')->willReturn(1);
        $wpdb->method('esc_like')->willReturn('_transient_timeout_');

        $GLOBALS['wpdb'] = $wpdb;

        $housekeeper = new DatabaseHousekeeper(null);
        $housekeeper->maintain();

        $this->assertTrue(true);
    }
}
