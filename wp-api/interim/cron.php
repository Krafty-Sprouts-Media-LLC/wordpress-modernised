<?php

/**
 * Stand-in for WP-Cron until its step. Listed in docs/stand-ins.md. Nothing
 * can be scheduled yet (Laravel's scheduler runs the jobs built so far), so
 * there is never anything to unschedule.
 */
if (! function_exists('wp_clear_scheduled_hook')) {
    /**
     * Unschedules all events attached to the hook with the specified arguments.
     *
     * Stand-in: nothing is scheduled through WP-Cron yet.
     *
     * @since WM-0.5.0
     * @since WP-2.1.0
     *
     * @param  string  $hook  Action hook, the execution of which will be unscheduled.
     * @param  array  $args  Optional. Array containing each separate argument to pass to the hook's callback function.
     * @param  bool  $wp_error  Optional. Whether to return a WP_Error on failure. Default false.
     * @return int|false|WP_Error On success an integer indicating number of events unscheduled.
     */
    function wp_clear_scheduled_hook($hook, $args = [], $wp_error = false)
    {
        return 0;
    }
}

if (! function_exists('wp_schedule_single_event')) {
    /**
     * Schedules an event to run only once.
     *
     * Stand-in: WP-Cron isn't built, so nothing is scheduled and this returns
     * false, as WordPress does when an event can't be scheduled. A post saved
     * for the future stays scheduled ("future") and doesn't publish itself
     * until the cron step (docs/divergences.md).
     *
     * @since WM-0.5.0
     * @since WP-2.1.0
     *
     * @param  int  $timestamp  Unix timestamp (UTC) for when to next run the event.
     * @param  string  $hook  Action hook to execute when the event is run.
     * @param  array  $args  Optional. Array containing arguments to pass to the hook's callback function.
     * @param  bool  $wp_error  Optional. Whether to return a WP_Error on failure. Default false.
     * @return bool|WP_Error True if event successfully scheduled. False or WP_Error on failure.
     */
    function wp_schedule_single_event($timestamp, $hook, $args = [], $wp_error = false)
    {
        return $wp_error ? new WP_Error('invalid_schedule', 'WP-Cron is not built yet.') : false;
    }
}
