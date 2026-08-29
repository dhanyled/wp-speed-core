<?php
declare(strict_types=1);

namespace WPSpeedCore\Tests\Engine;

use PHPUnit\Framework\TestCase;
use WPSpeedCore\Engine\PerformanceChecklist;

class PerformanceChecklistTest extends TestCase {

    public function setUp(): void {
        parent::setUp();
        $GLOBALS['_wp_options_mock'] = [];
    }

    public function test_evaluate_structure(): void {
        $checklist = new PerformanceChecklist();
        $result = $checklist->evaluate();

        $this::assertIsArray($result);
        $this::assertArrayHasKey('score', $result);
        $this::assertArrayHasKey('passed_count', $result);
        $this::assertArrayHasKey('total_count', $result);
        $this::assertArrayHasKey('categories', $result);

        $this::assertIsInt($result['score']);
        $this::assertIsInt($result['passed_count']);
        $this::assertIsInt($result['total_count']);
        $this::assertIsArray($result['categories']);

        $expected_categories = ['server_stack', 'core_web_vitals', 'asset_hygiene', 'database_ai'];
        foreach ($expected_categories as $cat) {
            $this::assertArrayHasKey($cat, $result['categories']);
            $this::assertArrayHasKey('title', $result['categories'][$cat]);
            $this::assertArrayHasKey('items', $result['categories'][$cat]);
            $this::assertIsArray($result['categories'][$cat]['items']);
        }
    }

    public function test_evaluate_with_empty_settings(): void {
        update_option('wpsc_settings', []);
        update_option('wpsc_disabled_assets', []);

        $checklist = new PerformanceChecklist();
        $result = $checklist->evaluate();

        $this::assertGreaterThanOrEqual(0, $result['score']);
        $this::assertLessThanOrEqual(100, $result['score']);

        $categories = $result['categories'];

        // Verify Core Web Vitals items are off/action_needed
        foreach ($categories['core_web_vitals']['items'] as $item) {
            $this::assertEquals('action_needed', $item['status']);
            $this::assertEquals('Off', $item['badge']);
        }

        // Verify Asset Unloader count
        $asset_unloader = $categories['asset_hygiene']['items'][0];
        $this::assertEquals('asset_unloader', $asset_unloader['id']);
        $this::assertEquals('info', $asset_unloader['status']);
        $this::assertEquals('0 Rules Active', $asset_unloader['badge']);
    }

    public function test_evaluate_with_all_settings_enabled(): void {
        $all_settings = [
            'cache' => [
                'html_cache' => true,
            ],
            'script' => [
                'delayed_execution' => true,
            ],
            'media' => [
                'auto_hero_priority' => true,
                'auto_dimensions' => true,
            ],
            'preload' => [
                'speculation_rules' => true,
                'speculation_level' => 'conservative',
            ],
            'general' => [
                'strip_emojis' => true,
                'strip_duotone_svg' => true,
                'block_xmlrpc' => true,
            ],
        ];

        $disabled_assets = [
            'style-1' => ['homepage'],
            'script-1' => ['all'],
        ];

        update_option('wpsc_settings', $all_settings);
        update_option('wpsc_disabled_assets', $disabled_assets);

        $checklist = new PerformanceChecklist();
        $result = $checklist->evaluate();

        $categories = $result['categories'];

        // Core Web Vitals checks
        $cwv_items = [];
        foreach ($categories['core_web_vitals']['items'] as $item) {
            $cwv_items[$item['id']] = $item;
        }

        $this::assertEquals('passed', $cwv_items['inp_shield']['status']);
        $this::assertEquals('Chunked Yield', $cwv_items['inp_shield']['badge']);

        $this::assertEquals('passed', $cwv_items['lcp_hero']['status']);
        $this::assertEquals('Auto Eager', $cwv_items['lcp_hero']['badge']);

        $this::assertEquals('passed', $cwv_items['cls_dimensions']['status']);
        $this::assertEquals('Active', $cwv_items['cls_dimensions']['badge']);

        $this::assertEquals('passed', $cwv_items['speculation_rules']['status']);
        $this::assertEquals('conservative', $cwv_items['speculation_rules']['badge']);

        // Asset Hygiene checks
        $asset_items = [];
        foreach ($categories['asset_hygiene']['items'] as $item) {
            $asset_items[$item['id']] = $item;
        }

        $this::assertEquals('passed', $asset_items['asset_unloader']['status']);
        $this::assertEquals('2 Rules Active', $asset_items['asset_unloader']['badge']);

        $this::assertEquals('passed', $asset_items['emoji_strip']['status']);
        $this::assertEquals('Stripped', $asset_items['emoji_strip']['badge']);

        $this::assertEquals('passed', $asset_items['duotone_strip']['status']);
        $this::assertEquals('Cleaned', $asset_items['duotone_strip']['badge']);

        $this::assertEquals('passed', $asset_items['xmlrpc_block']['status']);
        $this::assertEquals('Hardened', $asset_items['xmlrpc_block']['badge']);

        // HTML Cache check in Server Stack
        $server_items = [];
        foreach ($categories['server_stack']['items'] as $item) {
            $server_items[$item['id']] = $item;
        }

        $this::assertEquals('passed', $server_items['html_cache']['status']);
        $this::assertEquals('Active', $server_items['html_cache']['badge']);
    }

    public function test_score_calculation_logic(): void {
        update_option('wpsc_settings', []);
        update_option('wpsc_disabled_assets', []);

        $checklist = new PerformanceChecklist();
        $result = $checklist->evaluate();

        $total = $result['total_count'];
        $passed = $result['passed_count'];
        $expected_score = (int) round(($passed / $total) * 100);

        $this::assertEquals($expected_score, $result['score']);
    }
}
