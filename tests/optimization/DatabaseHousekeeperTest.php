<?php
declare(strict_types=1);

namespace WPSpeedCore\Tests\Optimization;

use PHPUnit\Framework\TestCase;
use WPSpeedCore\Engine\Logger;
use WPSpeedCore\Optimization\DatabaseHousekeeper;

class DatabaseHousekeeperTest extends TestCase {
    private $originalWpdb;

    protected function setUp(): void {
        parent::setUp();
        $this->originalWpdb = $GLOBALS['wpdb'] ?? null;
    }

    protected function tearDown(): void {
        $GLOBALS['wpdb'] = $this->originalWpdb;
        parent::tearDown();
    }

    public function testOptimizeTablesWithMultipleTablesAndLogger(): void {
        $tables = ['wp_posts', 'wp_options', 'wp_users'];

        $wpdbMock = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['get_col', 'query'])
            ->getMock();

        $wpdbMock->expects($this->once())
            ->method('get_col')
            ->with('SHOW TABLES')
            ->willReturn($tables);

        $wpdbMock->expects($this->exactly(3))
            ->method('query')
            ->withConsecutive(
                ['OPTIMIZE TABLE `wp_posts`'],
                ['OPTIMIZE TABLE `wp_options`'],
                ['OPTIMIZE TABLE `wp_users`']
            )
            ->willReturn(true);

        $GLOBALS['wpdb'] = $wpdbMock;

        $loggerMock = $this->createMock(Logger::class);
        $loggerMock->expects($this->once())
            ->method('info')
            ->with(
                'Database tables optimization completed.',
                ['tables_optimized' => 3]
            );

        $housekeeper = new DatabaseHousekeeper($loggerMock);
        $result = $housekeeper->optimize_tables();

        $this->assertSame(3, $result);
    }

    public function testOptimizeTablesWithEmptyTablesList(): void {
        $wpdbMock = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['get_col', 'query'])
            ->getMock();

        $wpdbMock->expects($this->once())
            ->method('get_col')
            ->with('SHOW TABLES')
            ->willReturn([]);

        $wpdbMock->expects($this->never())
            ->method('query');

        $GLOBALS['wpdb'] = $wpdbMock;

        $loggerMock = $this->createMock(Logger::class);
        $loggerMock->expects($this->once())
            ->method('info')
            ->with(
                'Database tables optimization completed.',
                ['tables_optimized' => 0]
            );

        $housekeeper = new DatabaseHousekeeper($loggerMock);
        $result = $housekeeper->optimize_tables();

        $this->assertSame(0, $result);
    }

    public function testOptimizeTablesWithNullTablesResult(): void {
        $wpdbMock = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['get_col', 'query'])
            ->getMock();

        $wpdbMock->expects($this->once())
            ->method('get_col')
            ->with('SHOW TABLES')
            ->willReturn(null);

        $wpdbMock->expects($this->never())
            ->method('query');

        $GLOBALS['wpdb'] = $wpdbMock;

        $housekeeper = new DatabaseHousekeeper(null);
        $result = $housekeeper->optimize_tables();

        $this->assertSame(0, $result);
    }

    public function testOptimizeTablesWithoutLogger(): void {
        $tables = ['wp_comments'];

        $wpdbMock = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['get_col', 'query'])
            ->getMock();

        $wpdbMock->expects($this->once())
            ->method('get_col')
            ->with('SHOW TABLES')
            ->willReturn($tables);

        $wpdbMock->expects($this->once())
            ->method('query')
            ->with('OPTIMIZE TABLE `wp_comments`')
            ->willReturn(true);

        $GLOBALS['wpdb'] = $wpdbMock;

        $housekeeper = new DatabaseHousekeeper(null);
        $result = $housekeeper->optimize_tables();

        $this->assertSame(1, $result);
    }
}
