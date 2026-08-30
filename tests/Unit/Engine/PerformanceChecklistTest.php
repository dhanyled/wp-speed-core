<?php
declare(strict_types=1);

namespace WPSpeedCore\Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use WP_Mock;
use WPSpeedCore\Engine\PerformanceChecklist;

class PerformanceChecklistTest extends TestCase {

    public function setUp(): void {
        WP_Mock::setUp();
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
    }

    public function test_evaluate_returns_expected_structure(): void {
        WP_Mock::userFunction('get_option')
            ->with('wpsc_settings', [])
            ->andReturn([]);

        WP_Mock::userFunction('get_option')
            ->with('wpsc_disabled_assets', [])
            ->andReturn([]);

        WP_Mock::userFunction('esc_html__')
            ->andReturnArg(0);

        $checklist = new PerformanceChecklist();
        $result    = $checklist->evaluate();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('passed_count', $result);
        $this->assertArrayHasKey('total_count', $result);
        $this->assertArrayHasKey('categories', $result);

        $this->assertIsInt($result['score']);
        $this->assertIsInt($result['passed_count']);
        $this->assertIsInt($result['total_count']);
        $this->assertIsArray($result['categories']);

        $expected_categories = ['server_stack', 'core_web_vitals', 'asset_hygiene', 'database_ai'];
        foreach ($expected_categories as $cat) {
            $this->assertArrayHasKey($cat, $result['categories']);
            $this->assertArrayHasKey('title', $result['categories'][$cat]);
            $this->assertArrayHasKey('items', $result['categories'][$cat]);
            $this->assertIsArray($result['categories'][$cat]['items']);
        }
    }

    public function test_evaluate_calculates_correct_score(): void {
        $all_settings = [
            'cache' => [
                'html_cache' => true,
            ],
            'script' => [
                'delayed_execution' => true,
            ],
            'media' => [
                'auto_hero_priority' => true,
                'auto_dimensions'    => true,
            ],
            'preload' => [
                'speculation_rules' => true,
                'speculation_level' => 'conservative',
            ],
            'general' => [
                'strip_emojis'      => true,
                'strip_duotone_svg' => true,
                'block_xmlrpc'      => true,
            ],
        ];

        WP_Mock::userFunction('get_option')
            ->with('wpsc_settings', [])
            ->andReturn($all_settings);

        WP_Mock::userFunction('get_option')
            ->with('wpsc_disabled_assets', [])
            ->andReturn([
                ['handle' => 'style-1', 'target' => 'everywhere'],
                ['handle' => 'script-1', 'target' => 'everywhere'],
            ]);

        WP_Mock::userFunction('esc_html__')
            ->andReturnArg(0);

        $checklist = new PerformanceChecklist();
        $result    = $checklist->evaluate();

        $this->assertGreaterThan(50, $result['score']);
        $this->assertEquals(
            (int) round(($result['passed_count'] / $result['total_count']) * 100),
            $result['score']
        );
    }
}