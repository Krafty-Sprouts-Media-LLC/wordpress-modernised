<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Options;

use App\Core\Options\OptionRepository;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Transients on the options table, and WordPress's daily clean-up of expired
 * ones, run by Laravel's scheduler and written portably for every database.
 */
final class TransientsTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_transients_are_cleaned_on_every_database(): void
    {
        $options = app(OptionRepository::class);
        set_transient('wm_old', 'x', 60);
        set_site_transient('wm_old_site', 'x', 60);
        set_transient('wm_new', 'y', 3600);
        // set_transient() reads PHP's clock, which tests can't move: age the rows instead.
        $options->update('_transient_timeout_wm_old', ['option_value' => (string) (time() - 10)]);
        $options->update('_site_transient_timeout_wm_old_site', ['option_value' => (string) (time() - 10)]);
        // A timeout with no value row is left alone, as in WordPress.
        $options->upsert('_transient_timeout_wm_orphan', (string) (time() - 10), 'off');

        delete_expired_transients();
        wp_cache_flush();

        $this->assertNull($options->find('_transient_wm_old'));
        $this->assertNull($options->find('_transient_timeout_wm_old'));
        $this->assertNull($options->find('_site_transient_wm_old_site'));
        $this->assertNull($options->find('_site_transient_timeout_wm_old_site'));
        $this->assertNotNull($options->find('_transient_timeout_wm_orphan'));
        $this->assertSame('y', get_transient('wm_new'));
    }

    public function test_the_scheduler_runs_the_clean_up_daily(): void
    {
        $events = collect(app(Schedule::class)->events());

        $this->assertTrue($events->contains(fn ($event) => $event->expression === '0 0 * * *'
            && $event->description === 'delete_expired_transients'));
    }

    public function test_the_clean_up_runs_through_the_wordpress_action(): void
    {
        set_transient('wm_old', 'x', 60);
        app(OptionRepository::class)->update('_transient_timeout_wm_old', ['option_value' => (string) (time() - 10)]);

        do_action('delete_expired_transients');

        $this->assertNull(app(OptionRepository::class)->find('_transient_wm_old'));
    }
}
