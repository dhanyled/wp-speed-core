<?php
declare(strict_types=1);

namespace WPSpeedCore\Tests\CLI;

use PHPUnit\Framework\TestCase;
use WP_CLI;
use WPSpeedCore\CLI\CLICommand;
use WPSpeedCore\Kernel;

class CLICommandTest extends TestCase {
    private CLICommand $command;

    protected function setUp(): void {
        parent::setUp();
        WP_CLI::reset_logs();
        $GLOBALS['wpsc_actions_triggered'] = [];
        $this->command = new CLICommand();
    }

    public function testPurgeTriggersActionAndLogsSuccess(): void {
        $this->command->purge([], []);

        $this->assertCount(1, $GLOBALS['wpsc_actions_triggered']);
        $this->assertSame('wpsc_purge_all', $GLOBALS['wpsc_actions_triggered'][0]['tag']);
        $this->assertCount(1, WP_CLI::$success_logs);
        $this->assertSame('WP Speed Core HTML Cache cleared successfully.', WP_CLI::$success_logs[0]);
    }

    public function testAutotuneSuccessWhenTunerAvailable(): void {
        $tunerMock = $this->createMock(\WPSpeedCore\Engine\AdaptiveTuner::class);
        $tunerMock->expects($this->once())->method('apply');

        $kernel = Kernel::launch();
        $reflection = new \ReflectionClass($kernel);
        $registryProperty = $reflection->getProperty('registry');
        $registryProperty->setAccessible(true);
        $registry = $registryProperty->getValue($kernel);
        $registry['tuner'] = $tunerMock;
        $registryProperty->setValue($kernel, $registry);

        $this->command->autotune([], []);

        $this->assertCount(1, WP_CLI::$success_logs);
        $this->assertSame('WP Speed Core Auto-Tune applied successfully.', WP_CLI::$success_logs[0]);
        $this->assertEmpty(WP_CLI::$error_logs);
    }

    public function testAutotuneErrorWhenTunerUnavailable(): void {
        $kernel = Kernel::launch();
        $reflection = new \ReflectionClass($kernel);
        $registryProperty = $reflection->getProperty('registry');
        $registryProperty->setAccessible(true);
        $registry = $registryProperty->getValue($kernel);
        $registry['tuner'] = null;
        $registryProperty->setValue($kernel, $registry);

        $this->command->autotune([], []);

        $this->assertCount(1, WP_CLI::$error_logs);
        $this->assertSame('Auto-Tune engine is unavailable.', WP_CLI::$error_logs[0]);
        $this->assertEmpty(WP_CLI::$success_logs);
    }

    public function testDbCleanSuccessWhenDbAvailable(): void {
        $dbMock = $this->createMock(\WPSpeedCore\Optimization\DatabaseHousekeeper::class);
        $dbMock->expects($this->once())->method('maintain');

        $kernel = Kernel::launch();
        $reflection = new \ReflectionClass($kernel);
        $registryProperty = $reflection->getProperty('registry');
        $registryProperty->setAccessible(true);
        $registry = $registryProperty->getValue($kernel);
        $registry['db'] = $dbMock;
        $registryProperty->setValue($kernel, $registry);

        $this->command->db_clean([], []);

        $this->assertCount(1, WP_CLI::$success_logs);
        $this->assertSame('Database housekeeping and table optimization completed.', WP_CLI::$success_logs[0]);
        $this->assertEmpty(WP_CLI::$error_logs);
    }

    public function testDbCleanSuccessWithOptimizeTablesMethod(): void {
        $dbMock = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['maintain', 'optimize_tables'])
            ->getMock();
        $dbMock->expects($this->once())->method('maintain');
        $dbMock->expects($this->once())->method('optimize_tables');

        $kernel = Kernel::launch();
        $reflection = new \ReflectionClass($kernel);
        $registryProperty = $reflection->getProperty('registry');
        $registryProperty->setAccessible(true);
        $registry = $registryProperty->getValue($kernel);
        $registry['db'] = $dbMock;
        $registryProperty->setValue($kernel, $registry);

        $this->command->db_clean([], []);

        $this->assertCount(1, WP_CLI::$success_logs);
        $this->assertSame('Database housekeeping and table optimization completed.', WP_CLI::$success_logs[0]);
        $this->assertEmpty(WP_CLI::$error_logs);
    }

    public function testDbCleanErrorWhenDbUnavailable(): void {
        $kernel = Kernel::launch();
        $reflection = new \ReflectionClass($kernel);
        $registryProperty = $reflection->getProperty('registry');
        $registryProperty->setAccessible(true);
        $registry = $registryProperty->getValue($kernel);
        $registry['db'] = null;
        $registryProperty->setValue($kernel, $registry);

        $this->command->db_clean([], []);

        $this->assertCount(1, WP_CLI::$error_logs);
        $this->assertSame('Database Housekeeper is unavailable.', WP_CLI::$error_logs[0]);
        $this->assertEmpty(WP_CLI::$success_logs);
    }

    public function testStatusOutputsDiagnostics(): void {
        $this->command->status([], []);

        $this->assertNotEmpty(WP_CLI::$line_logs);
        $joinedLines = implode("\n", WP_CLI::$line_logs);
        $this->assertStringContainsString('WP Speed Core Status & Diagnostics', $joinedLines);
        $this->assertStringContainsString('PHP Version:', $joinedLines);
    }
}
