<?php

namespace Tests;

use App\Core\Database\DatabaseAvailability;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Base for the app's tests. All tests share one PHP process, so the WordPress
 * hook globals are saved after the first boot and restored after every test.
 * The first test also checks the test database is reachable.
 */
abstract class TestCase extends BaseTestCase
{
    /** @var array<string, mixed>|null */
    private static ?array $hooksAfterBoot = null;

    private static bool $databaseChecked = false;

    protected function setUp(): void
    {
        parent::setUp();

        self::$hooksAfterBoot ??= self::copyHooks($GLOBALS);

        if (! self::$databaseChecked) {
            self::$databaseChecked = true;

            try {
                DB::connection()->getPdo();
            } catch (Throwable $e) {
                $this->fail(DatabaseAvailability::hint($e) ?? $e->getMessage());
            }
        }
    }

    protected function tearDown(): void
    {
        foreach (self::copyHooks(self::$hooksAfterBoot ?? []) as $name => $value) {
            $GLOBALS[$name] = $value;
        }

        parent::tearDown();
    }

    /**
     * Copies the four hook globals, cloning each WP_Hook so later changes
     * to the live hooks never reach the copy.
     *
     * @param  array<string, mixed>  $from
     * @return array<string, mixed>
     */
    private static function copyHooks(array $from): array
    {
        $copy = [];

        foreach (['wp_filter', 'wp_actions', 'wp_filters', 'wp_current_filter'] as $name) {
            $copy[$name] = $from[$name] ?? [];
        }

        $copy['wp_filter'] = array_map(fn ($hook) => clone $hook, $copy['wp_filter']);

        return $copy;
    }
}
