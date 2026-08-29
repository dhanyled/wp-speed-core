<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use WPSpeedCore\Optimization\BloatSuppressor;

class TestBloatSuppressor {
    private function resetState(): void {
        $_GET = [];
        $GLOBALS['_wp_die_calls'] = [];
        $GLOBALS['_is_admin'] = false;
        $GLOBALS['_options'] = [];
    }

    public function runAll(): void {
        echo "Running BloatSuppressor Test Suite...\n\n";

        $this->testBlockAuthorEnumerationBlocksNumericAuthorForNonAdmin();
        $this->testBlockAuthorEnumerationAllowsNumericAuthorForAdmin();
        $this->testBlockAuthorEnumerationAllowsNonNumericAuthor();
        $this->testBlockAuthorEnumerationAllowsRequestWithoutAuthorParam();

        echo "\nAll tests passed successfully!\n";
    }

    public function testBlockAuthorEnumerationBlocksNumericAuthorForNonAdmin(): void {
        $this->resetState();
        $GLOBALS['_is_admin'] = false;
        $_GET['author'] = '1';

        $suppressor = new BloatSuppressor();

        $died = false;
        try {
            $suppressor->block_author_enumeration();
        } catch (\RuntimeException $e) {
            $died = true;
        }

        assert($died === true, 'Failed asserting that wp_die was called for non-admin author enumeration');
        assert(count($GLOBALS['_wp_die_calls']) === 1, 'Expected 1 call to wp_die');
        assert($GLOBALS['_wp_die_calls'][0]['message'] === 'Akses tidak diizinkan.', 'Incorrect wp_die message');
        assert($GLOBALS['_wp_die_calls'][0]['title'] === 'Akses Ditolak', 'Incorrect wp_die title');
        assert(isset($GLOBALS['_wp_die_calls'][0]['args']['response']) && $GLOBALS['_wp_die_calls'][0]['args']['response'] === 403, 'Expected response status 403');

        echo "✔ testBlockAuthorEnumerationBlocksNumericAuthorForNonAdmin passed\n";
    }

    public function testBlockAuthorEnumerationAllowsNumericAuthorForAdmin(): void {
        $this->resetState();
        $GLOBALS['_is_admin'] = true;
        $_GET['author'] = '1';

        $suppressor = new BloatSuppressor();

        $died = false;
        try {
            $suppressor->block_author_enumeration();
        } catch (\RuntimeException $e) {
            $died = true;
        }

        assert($died === false, 'Failed asserting that wp_die was NOT called for admin request');
        assert(count($GLOBALS['_wp_die_calls']) === 0, 'Expected 0 calls to wp_die');

        echo "✔ testBlockAuthorEnumerationAllowsNumericAuthorForAdmin passed\n";
    }

    public function testBlockAuthorEnumerationAllowsNonNumericAuthor(): void {
        $this->resetState();
        $GLOBALS['_is_admin'] = false;
        $_GET['author'] = 'admin_slug';

        $suppressor = new BloatSuppressor();

        $died = false;
        try {
            $suppressor->block_author_enumeration();
        } catch (\RuntimeException $e) {
            $died = true;
        }

        assert($died === false, 'Failed asserting that wp_die was NOT called for non-numeric author parameter');
        assert(count($GLOBALS['_wp_die_calls']) === 0, 'Expected 0 calls to wp_die');

        echo "✔ testBlockAuthorEnumerationAllowsNonNumericAuthor passed\n";
    }

    public function testBlockAuthorEnumerationAllowsRequestWithoutAuthorParam(): void {
        $this->resetState();
        $GLOBALS['_is_admin'] = false;
        unset($_GET['author']);

        $suppressor = new BloatSuppressor();

        $died = false;
        try {
            $suppressor->block_author_enumeration();
        } catch (\RuntimeException $e) {
            $died = true;
        }

        assert($died === false, 'Failed asserting that wp_die was NOT called when author GET parameter is absent');
        assert(count($GLOBALS['_wp_die_calls']) === 0, 'Expected 0 calls to wp_die');

        echo "✔ testBlockAuthorEnumerationAllowsRequestWithoutAuthorParam passed\n";
    }
}

$testRunner = new TestBloatSuppressor();
$testRunner->runAll();
