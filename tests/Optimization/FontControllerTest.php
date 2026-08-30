<?php
declare(strict_types=1);

namespace WPSpeedCore\Tests\Optimization;

use PHPUnit\Framework\TestCase;
use WPSpeedCore\Optimization\FontController;
use WP_Mock;

class FontControllerTest extends TestCase {

    public function setUp(): void {
        WP_Mock::setUp();
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
    }

    /**
     * @dataProvider fontDisplaySwapDataProvider
     */
    public function test_add_font_display_swap(string $html, string $handle, string $href, string $media, string $expected): void {
        WP_Mock::userFunction('is_admin', [
            'return' => false,
        ]);

        $controller = new FontController();
        $result = $controller->add_font_display_swap($html, $handle, $href, $media);
        $this->assertEquals($expected, $result);
    }

    public function fontDisplaySwapDataProvider(): array {
        return [
            'Google font URL with query parameter without display' => [
                '<link rel="stylesheet" id="google-fonts-css" href="https://fonts.googleapis.com/css?family=Open+Sans" type="text/css" media="all" />',
                'google-fonts',
                'https://fonts.googleapis.com/css?family=Open+Sans',
                'all',
                '<link rel="stylesheet" id="google-fonts-css" href="https://fonts.googleapis.com/css?family=Open+Sans&amp;display=swap" type="text/css" media="all" />',
            ],
            'Google font URL css2 with query parameter without display' => [
                '<link rel="stylesheet" id="google-fonts-css" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700" type="text/css" media="all" />',
                'google-fonts',
                'https://fonts.googleapis.com/css2?family=Roboto:wght@400;700',
                'all',
                '<link rel="stylesheet" id="google-fonts-css" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&amp;display=swap" type="text/css" media="all" />',
            ],
            'Google font URL without query parameter' => [
                '<link rel="stylesheet" id="google-fonts-css" href="https://fonts.googleapis.com/css" type="text/css" media="all" />',
                'google-fonts',
                'https://fonts.googleapis.com/css',
                'all',
                '<link rel="stylesheet" id="google-fonts-css" href="https://fonts.googleapis.com/css?display=swap" type="text/css" media="all" />',
            ],
            'Google font URL already having display parameter' => [
                '<link rel="stylesheet" id="google-fonts-css" href="https://fonts.googleapis.com/css?family=Open+Sans&amp;display=swap" type="text/css" media="all" />',
                'google-fonts',
                'https://fonts.googleapis.com/css?family=Open+Sans&amp;display=swap',
                'all',
                '<link rel="stylesheet" id="google-fonts-css" href="https://fonts.googleapis.com/css?family=Open+Sans&amp;display=swap" type="text/css" media="all" />',
            ],
            'Google font URL with custom display parameter e.g. display=optional' => [
                '<link rel="stylesheet" id="google-fonts-css" href="https://fonts.googleapis.com/css?family=Open+Sans&amp;display=optional" type="text/css" media="all" />',
                'google-fonts',
                'https://fonts.googleapis.com/css?family=Open+Sans&amp;display=optional',
                'all',
                '<link rel="stylesheet" id="google-fonts-css" href="https://fonts.googleapis.com/css?family=Open+Sans&amp;display=optional" type="text/css" media="all" />',
            ],
            'Non-Google font URL' => [
                '<link rel="stylesheet" id="custom-style-css" href="https://example.com/wp-content/themes/mytheme/style.css" type="text/css" media="all" />',
                'custom-style',
                'https://example.com/wp-content/themes/mytheme/style.css',
                'all',
                '<link rel="stylesheet" id="custom-style-css" href="https://example.com/wp-content/themes/mytheme/style.css" type="text/css" media="all" />',
            ],
            'Google font URL not found in html string' => [
                '<link rel="stylesheet" id="other-css" href="https://example.com/style.css" />',
                'google-fonts',
                'https://fonts.googleapis.com/css?family=Roboto',
                'all',
                '<link rel="stylesheet" id="other-css" href="https://example.com/style.css" />',
            ],
        ];
    }

    public function test_add_font_hints_dns_prefetch(): void {
        WP_Mock::userFunction('is_admin', [
            'return' => false,
        ]);

        $controller = new FontController();
        $urls = ['//example.com'];
        $result = $controller->add_font_hints($urls, 'dns-prefetch');

        $this->assertContains('//fonts.googleapis.com', $result);
        $this->assertContains('//fonts.gstatic.com', $result);
        $this->assertCount(3, $result);
    }

    public function test_add_font_hints_other_relation_type(): void {
        WP_Mock::userFunction('is_admin', [
            'return' => false,
        ]);

        $controller = new FontController();
        $urls = ['//example.com'];
        $result = $controller->add_font_hints($urls, 'preconnect');

        $this->assertEquals(['//example.com'], $result);
    }

    public function test_construct_registers_filters_when_not_admin(): void {
        WP_Mock::userFunction('is_admin', [
            'return' => false,
        ]);

        WP_Mock::expectFilterAdded(
            'style_loader_tag',
            [new WP_Mock\Matcher\AnyInstance(FontController::class), 'add_font_display_swap'],
            10,
            4
        );

        WP_Mock::expectFilterAdded(
            'wp_resource_hints',
            [new WP_Mock\Matcher\AnyInstance(FontController::class), 'add_font_hints'],
            10,
            2
        );

        new FontController();
        WP_Mock::assertHooksAdded();
    }

    public function test_construct_does_not_register_filters_when_admin(): void {
        WP_Mock::userFunction('is_admin', [
            'return' => true,
        ]);

        $controller = new FontController();
        $this->assertInstanceOf(FontController::class, $controller);
    }
}
