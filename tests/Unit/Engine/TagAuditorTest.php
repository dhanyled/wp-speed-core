<?php
declare(strict_types=1);

namespace WPSpeedCore\Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use WP_Mock;
use WPSpeedCore\Engine\TagAuditor;

class TagAuditorTest extends TestCase {

    public function setUp(): void {
        WP_Mock::setUp();
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
    }

    public function test_scan_html_detects_duplicate_gtm_tags(): void {
        WP_Mock::userFunction('set_transient')
            ->once();

        $auditor = new TagAuditor();

        $html = '<html><head><script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({\'gtm.start\':new Date().getTime(),event:\'gtm.js\'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!=\'dataLayer\'?\'&l=\'+l:\'\';j.async=true;j.src=\'https://www.googletagmanager.com/gtm.js?id=\'+i+dl;f.parentNode.insertBefore(j,f);})(window,document,\'script\',\'dataLayer\',\'GTM-XXXXXX\');</script><script>/* duplicate */ GTM-XXXXXX</script></head><body>Hello World</body></html>';

        $result = $auditor->scan_html($html);
        $this->assertEquals($html, $result);
    }

    public function test_scan_html_deletes_transient_when_no_duplicates(): void {
        WP_Mock::userFunction('delete_transient')
            ->with('wpsc_tag_audit')
            ->once();

        $auditor = new TagAuditor();

        $html = '<html><head><script>gtag(\'config\', \'G-ABC1234567\');</script></head><body>Clean page without duplicates</body></html>';

        $result = $auditor->scan_html($html);
        $this->assertEquals($html, $result);
    }
}