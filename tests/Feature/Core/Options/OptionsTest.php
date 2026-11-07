<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Options;

use App\Core\Options\Events\OptionUpdated;
use App\Core\Options\Options;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Options are stored through Laravel's query builder on every database, and
 * WordPress's doors and the Laravel-side Options service give the same
 * answers from the same cache and hooks.
 */
final class OptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_options_round_trip_on_this_database(): void
    {
        $value = ['a' => 1, 'quote' => "it's", 'nested' => [true, null, 1.5]];

        $this->assertTrue(add_option('wm_test', $value));
        wp_cache_flush();
        $this->assertSame($value, get_option('wm_test'));

        $this->assertTrue(update_option('wm_test', 'plain'));
        $this->assertSame('plain', get_option('wm_test'));

        $this->assertTrue(delete_option('wm_test'));
        $this->assertFalse(get_option('wm_test'));
    }

    public function test_laravel_code_reads_the_same_option_from_the_same_cache(): void
    {
        update_option('wm_shared', 'x');
        $before = get_num_queries();

        $this->assertSame('x', app(Options::class)->get('wm_shared'));
        $this->assertSame($before, get_num_queries());
    }

    public function test_laravel_code_writes_through_the_doors(): void
    {
        $options = app(Options::class);

        $this->assertTrue($options->set('wm_native', ['n' => 1], false));
        $this->assertSame(['n' => 1], get_option('wm_native'));
        $this->assertTrue($options->delete('wm_native'));
        $this->assertSame('fallback', $options->get('wm_native', 'fallback'));
    }

    public function test_the_query_filter_sees_plugin_queries_not_core_option_queries(): void
    {
        global $wpdb;
        $seen = [];
        add_filter('query', function ($query) use (&$seen) {
            $seen[] = $query;

            return $query;
        });

        update_option('wm_filtered', 'x');
        $this->assertSame([], $seen);

        $wpdb->query("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'wm_filtered'");
        $this->assertCount(1, $seen);
    }

    public function test_an_update_is_announced_once_to_both_sides(): void
    {
        $laravel = 0;
        $wordpress = 0;
        Event::listen(OptionUpdated::class, function () use (&$laravel) {
            $laravel++;
        });
        add_action('updated_option', function () use (&$wordpress) {
            $wordpress++;
        });

        add_option('wm_once', 'a');
        update_option('wm_once', 'b');

        $this->assertSame([1, 1], [$laravel, $wordpress]);
    }
}
