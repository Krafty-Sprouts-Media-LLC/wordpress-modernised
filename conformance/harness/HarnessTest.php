<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Runner\Version;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Proves the checker itself is sound before any WordPress test is trusted:
 * the right PHPUnit, the pinned WordPress checkout, a booted Laravel app,
 * and WordPress's test utilities loaded.
 */
final class HarnessTest extends TestCase
{
    public function test_runs_on_phpunit_9_6(): void
    {
        $this->assertStringStartsWith('9.6.', Version::id());
    }

    public function test_checkout_is_pinned_tag(): void
    {
        $head = trim((string) shell_exec('git -C '.escapeshellarg(WP_TESTS_CHECKOUT).' rev-parse HEAD'));

        $this->assertSame('0afb74de8c3fe4c1869e44b62b0418d67b11aa4c', $head);
    }

    public function test_checkout_is_pristine(): void
    {
        $changes = trim((string) shell_exec('git -C '.escapeshellarg(WP_TESTS_CHECKOUT).' status --porcelain'));

        $this->assertSame('', $changes, 'The WordPress checkout has local changes; restore it with: git -C conformance/wordpress-develop checkout -- .');
    }

    public function test_package_manifest_is_rebuilt_each_run(): void
    {
        $manifest = dirname(__DIR__).'/cache/packages.php';

        $this->assertFileExists($manifest);
        $this->assertGreaterThanOrEqual(CHECKER_STARTED_AT, filemtime($manifest));
    }

    public function test_checker_database_is_migrated(): void
    {
        $this->assertSame('wordpress_conformance', DB::getDatabaseName());
        $this->assertTrue(Schema::hasTable(config('wordpress.table_prefix').'posts'));
    }

    public function test_laravel_app_is_booted(): void
    {
        $this->assertTrue(app()->isBooted());
        $this->assertSame(realpath(__DIR__.'/../..'), realpath(base_path()));
    }

    public function test_wordpress_test_utilities_are_loaded(): void
    {
        $this->assertTrue(class_exists('MockAction'));
        $this->assertTrue(class_exists('Mock_Invokable'));
        $this->assertTrue(class_exists('WP_UnitTestCase'));
    }
}
