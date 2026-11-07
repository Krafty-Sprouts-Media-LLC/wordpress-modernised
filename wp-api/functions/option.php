<?php

/**
 * Option API: get_option(), add_option(), update_option(), delete_option(),
 * autoloading, and the site-option doors on a single site.
 *
 * WordPress's flow (filters, actions, the alloptions and notoptions cache
 * entries) is kept as it is. Rows are read and written by the house,
 * App\Core\Options\OptionRepository, through Laravel's query builder, one
 * call per query WordPress runs. The added, updated and deleted announcements
 * are Laravel events, bridged to WordPress's actions (CoreServiceProvider).
 * Multisite branches are left out: there is no multisite.
 *
 * Contains code from WordPress 7.1.4: src/wp-includes/option.php.
 */

use App\Core\Options\Events\OptionAdded;
use App\Core\Options\Events\OptionDeleted;
use App\Core\Options\Events\OptionUpdated;
use App\Core\Options\OptionRepository;
use Illuminate\Database\QueryException;

/**
 * Retrieves an option value based on an option name.
 *
 * If the option does not exist, and a default value is not provided,
 * boolean false is returned. This could be used to check whether you need
 * to initialize an option during installation of a plugin, however that
 * can be done better by using add_option() which will not overwrite
 * existing options.
 *
 * Not initializing an option and using boolean `false` as a return value
 * is a bad practice as it triggers an additional database query.
 *
 * The type of the returned value can be different from the type that was passed
 * when saving or updating the option. If the option value was serialized,
 * then it will be unserialized when it is returned. In this case the type will
 * be the same. For example, storing a non-scalar value like an array will
 * return the same array.
 *
 * In most cases non-string scalar and null values will be converted and returned
 * as string equivalents.
 *
 * Exceptions:
 *
 * 1. When the option has not been saved in the database, the `$default_value` value
 *    is returned if provided. If not, boolean `false` is returned.
 * 2. When one of the Options API filters is used: {@see 'pre_option_$option'},
 *    {@see 'default_option_$option'}, or {@see 'option_$option'}, the returned
 *    value may not match the expected type.
 * 3. When the option has just been saved in the database, and get_option()
 *    is used right after, non-string scalar and null values are not converted to
 *    string equivalents and the original type is returned.
 *
 * Examples:
 *
 * When adding options like this: `add_option( 'my_option_name', 'value' )`
 * and then retrieving them with `get_option( 'my_option_name' )`, the returned
 * values will be:
 *
 *   - `false` returns `string(0) ""`
 *   - `true`  returns `string(1) "1"`
 *   - `0`     returns `string(1) "0"`
 *   - `1`     returns `string(1) "1"`
 *   - `'0'`   returns `string(1) "0"`
 *   - `'1'`   returns `string(1) "1"`
 *   - `null`  returns `string(0) ""`
 *
 * When adding options with non-scalar values like
 * `add_option( 'my_array', array( false, 'str', null ) )`, the returned value
 * will be identical to the original as it is serialized before saving
 * it in the database:
 *
 *     array(3) {
 *         [0] => bool(false)
 *         [1] => string(3) "str"
 *         [2] => NULL
 *     }
 *
 * @since WM-0.4.0
 * @since WP-1.5.0
 *
 * @param  string  $option  Name of the option to retrieve. Expected to not be SQL-escaped.
 * @param  mixed  $default_value  Optional. Default value to return if the option does not exist.
 * @return mixed Value of the option. A value of any type may be returned, including
 *               scalar (string, boolean, float, integer), null, array, object.
 *               Scalar and null values will be returned as strings as long as they originate
 *               from a database stored option value. If there is no option in the database,
 *               boolean `false` is returned.
 */
function get_option($option, $default_value = false)
{
    if (is_scalar($option)) {
        $option = trim($option);
    }

    if (empty($option)) {
        return false;
    }

    /*
     * Until a proper _deprecated_option() function can be introduced,
     * redirect requests to deprecated keys to the new, correct ones.
     */
    $deprecated_keys = [
        'blacklist_keys' => 'disallowed_keys',
        'comment_whitelist' => 'comment_previously_approved',
    ];

    if (isset($deprecated_keys[$option]) && ! wp_installing()) {
        _deprecated_argument(
            __FUNCTION__,
            '5.5.0',
            sprintf(
                /* translators: 1: Deprecated option key, 2: New option key. */
                __('The "%1$s" option key has been renamed to "%2$s".'),
                $option,
                $deprecated_keys[$option]
            )
        );

        return get_option($deprecated_keys[$option], $default_value);
    }

    /**
     * Filters the value of an existing option before it is retrieved.
     *
     * The dynamic portion of the hook name, `$option`, refers to the option name.
     *
     * Returning a value other than false from the filter will short-circuit retrieval
     * and return that value instead.
     *
     * @since WP-1.5.0
     * @since WP-4.4.0 The `$option` parameter was added.
     * @since WP-4.9.0 The `$default_value` parameter was added.
     *
     * @param  mixed  $pre_option  The value to return instead of the option value. This differs from
     *                             `$default_value`, which is used as the fallback value in the event
     *                             the option doesn't exist elsewhere in get_option().
     *                             Default false (to skip past the short-circuit).
     * @param  string  $option  Option name.
     * @param  mixed  $default_value  The fallback value to return if the option does not exist.
     *                                Default false.
     */
    $pre = apply_filters("pre_option_{$option}", false, $option, $default_value);

    /**
     * Filters the value of any existing option before it is retrieved.
     *
     * Returning a value other than false from the filter will short-circuit retrieval
     * and return that value instead.
     *
     * @since WP-6.1.0
     *
     * @param  mixed  $pre_option  The value to return instead of the option value. This differs from
     *                             `$default_value`, which is used as the fallback value in the event
     *                             the option doesn't exist elsewhere in get_option().
     *                             Default false (to skip past the short-circuit).
     * @param  string  $option  Name of the option.
     * @param  mixed  $default_value  The fallback value to return if the option does not exist.
     *                                Default false.
     */
    $pre = apply_filters('pre_option', $pre, $option, $default_value);

    if ($pre !== false) {
        return $pre;
    }

    if (defined('WP_SETUP_CONFIG')) {
        return false;
    }

    // Distinguish between `false` as a default, and not passing one.
    $passed_default = func_num_args() > 1;

    if (! wp_installing()) {
        $alloptions = wp_load_alloptions();
        /*
         * When getting an option value, we check in the following order for performance:
         *
         * 1. Check the 'alloptions' cache first to prioritize existing loaded options.
         * 2. Check the 'notoptions' cache before a cache lookup or DB hit.
         * 3. Check the 'options' cache prior to a DB hit.
         * 4. Check the DB for the option and cache it in either the 'options' or 'notoptions' cache.
         */
        if (isset($alloptions[$option])) {
            $value = $alloptions[$option];
        } else {
            // Check for non-existent options first to avoid unnecessary object cache lookups and DB hits.
            $notoptions = wp_cache_get('notoptions', 'options');

            if (! is_array($notoptions)) {
                $notoptions = [];
                wp_cache_set('notoptions', $notoptions, 'options');
            }

            if (isset($notoptions[$option])) {
                /**
                 * Filters the default value for an option.
                 *
                 * The dynamic portion of the hook name, `$option`, refers to the option name.
                 *
                 * @since WP-3.4.0
                 * @since WP-4.4.0 The `$option` parameter was added.
                 * @since WP-4.7.0 The `$passed_default` parameter was added to distinguish between a `false` value and the default parameter value.
                 *
                 * @param  mixed  $default_value  The default value to return if the option does not exist
                 *                                in the database.
                 * @param  string  $option  Option name.
                 * @param  bool  $passed_default  Was `get_option()` passed a default value?
                 */
                return apply_filters("default_option_{$option}", $default_value, $option, $passed_default);
            }

            $value = wp_cache_get($option, 'options');

            if ($value === false) {
                $row = app(OptionRepository::class)->find($option);

                if ($row !== null) {
                    $value = $row;
                    wp_cache_add($option, $value, 'options');
                } else { // Option does not exist, so we must cache its non-existence.
                    $notoptions[$option] = true;
                    wp_cache_set('notoptions', $notoptions, 'options');

                    /** This filter is documented in wp-includes/option.php */
                    return apply_filters("default_option_{$option}", $default_value, $option, $passed_default);
                }
            }
        }
    } else {
        // While installing, the table may not exist yet: WordPress suppresses the error.
        try {
            $row = app(OptionRepository::class)->find($option);
        } catch (QueryException) {
            $row = null;
        }

        if ($row !== null) {
            $value = $row;
        } else {
            /** This filter is documented in wp-includes/option.php */
            return apply_filters("default_option_{$option}", $default_value, $option, $passed_default);
        }
    }

    // If home is not set, use siteurl.
    if ($option === 'home' && $value === '') {
        return get_option('siteurl');
    }

    if (in_array($option, ['siteurl', 'home', 'category_base', 'tag_base'], true)) {
        $value = untrailingslashit($value);
    }

    /**
     * Filters the value of an existing option.
     *
     * The dynamic portion of the hook name, `$option`, refers to the option name.
     *
     * @since WP-1.5.0 As `option_{$setting}`.
     * @since WP-3.0.0
     * @since WP-4.4.0 The `$option` parameter was added.
     *
     * @param  mixed  $value  Value of the option. If stored serialized, it will be
     *                        unserialized prior to being returned.
     * @param  string  $option  Option name.
     */
    return apply_filters("option_{$option}", maybe_unserialize($value), $option);
}

/**
 * Primes specific options into the cache with a single database query.
 *
 * Only options that do not already exist in cache will be loaded.
 *
 * @since WM-0.4.0
 * @since WP-6.4.0
 *
 * @param  string[]  $options  An array of option names to be loaded.
 */
function wp_prime_option_caches($options)
{
    $alloptions = wp_load_alloptions();
    $cached_options = wp_cache_get_multiple($options, 'options');
    $notoptions = wp_cache_get('notoptions', 'options');
    if (! is_array($notoptions)) {
        $notoptions = [];
    }

    // Filter options that are not in the cache.
    $options_to_prime = [];
    foreach ($options as $option) {
        if (
            (! isset($cached_options[$option]) || $cached_options[$option] === false)
            && ! isset($alloptions[$option])
            && ! isset($notoptions[$option])
        ) {
            $options_to_prime[] = $option;
        }
    }

    // Bail early if there are no options to be loaded.
    if (empty($options_to_prime)) {
        return;
    }

    /*
     * The cache is primed with the raw value (i.e. not maybe_unserialized).
     *
     * `get_option()` will handle unserializing the value as needed.
     */
    $options_found = app(OptionRepository::class)->findMany(array_values($options_to_prime));
    wp_cache_set_multiple($options_found, 'options');

    // If all options were found, no need to update `notoptions` cache.
    if (count($options_found) === count($options_to_prime)) {
        return;
    }

    $options_not_found = array_diff($options_to_prime, array_keys($options_found));

    // Add the options that were not found to the cache.
    $update_notoptions = false;
    foreach ($options_not_found as $option_name) {
        if (! isset($notoptions[$option_name])) {
            $notoptions[$option_name] = true;
            $update_notoptions = true;
        }
    }

    // Only update the cache if it was modified.
    if ($update_notoptions) {
        wp_cache_set('notoptions', $notoptions, 'options');
    }
}

/**
 * Primes the cache of all options registered with a specific option group.
 *
 * @since WM-0.4.0
 * @since WP-6.4.0
 *
 * @global array $new_allowed_options
 *
 * @param  string  $option_group  The option group to load options for.
 */
function wp_prime_option_caches_by_group($option_group)
{
    global $new_allowed_options;

    if (isset($new_allowed_options[$option_group])) {
        wp_prime_option_caches($new_allowed_options[$option_group]);
    }
}

/**
 * Retrieves multiple options.
 *
 * Options are loaded as necessary first in order to use a single database query at most.
 *
 * @since WM-0.4.0
 * @since WP-6.4.0
 *
 * @param  string[]  $options  An array of option names to retrieve.
 * @return array An array of key-value pairs for the requested options.
 */
function get_options($options)
{
    wp_prime_option_caches($options);

    $result = [];
    foreach ($options as $option) {
        $result[$option] = get_option($option);
    }

    return $result;
}

/**
 * Sets the autoload values for multiple options in the database.
 *
 * Autoloading too many options can lead to performance problems, especially if the options are not frequently used.
 * This function allows modifying the autoload value for multiple options without changing the actual option value.
 * This is for example recommended for plugin activation and deactivation hooks, to ensure any options exclusively used
 * by the plugin which are generally autoloaded can be set to not autoload when the plugin is inactive.
 *
 * @since WM-0.4.0
 * @since WP-6.4.0
 * @since WP-6.7.0 The autoload values 'yes' and 'no' are deprecated.
 *
 * @param  array  $options  Associative array of option names and their autoload values to set. The option names are
 *                          expected to not be SQL-escaped. The autoload values should be boolean values. For backward
 *                          compatibility 'yes' and 'no' are also accepted, though using these values is deprecated.
 * @return array Associative array of all provided $options as keys and boolean values for whether their autoload value
 *               was updated.
 */
function wp_set_option_autoload_values(array $options)
{
    if (! $options) {
        return [];
    }

    $grouped_options = [
        'on' => [],
        'off' => [],
    ];
    $results = [];
    foreach ($options as $option => $autoload) {
        wp_protect_special_option($option); // Ensure only valid options can be passed.

        /*
         * Sanitize autoload value and categorize accordingly.
         * The values 'yes', 'no', 'on', and 'off' are supported for backward compatibility.
         */
        if ($autoload === 'off' || $autoload === 'no' || $autoload === false) {
            $grouped_options['off'][] = $option;
        } else {
            $grouped_options['on'][] = $option;
        }
        $results[$option] = false; // Initialize result value.
    }

    /*
     * Determine the relevant options that do not already use the given autoload value.
     * If no options are returned, no need to update.
     */
    $options_to_update = app(OptionRepository::class)->needingAutoload(array_map(
        fn ($names) => array_map('strval', $names),
        $grouped_options
    ));
    if (! $options_to_update) {
        return $results;
    }

    // Run UPDATE queries as needed (maximum 2) to update the relevant options' autoload values to 'yes' or 'no'.
    foreach ($grouped_options as $autoload => $options) {
        if (! $options) {
            continue;
        }
        $options = array_intersect($options, $options_to_update);
        $grouped_options[$autoload] = $options;
        if (! $grouped_options[$autoload]) {
            continue;
        }

        // Run query to update autoload value for all the options where it is needed.
        $success = app(OptionRepository::class)->setAutoload($autoload, array_values(array_map('strval', $grouped_options[$autoload])));
        if (! $success) {
            // Set option list to an empty array to indicate no options were updated.
            $grouped_options[$autoload] = [];

            continue;
        }

        // Assume that on success all options were updated, which should be the case given only new values are sent.
        foreach ($grouped_options[$autoload] as $option) {
            $results[$option] = true;
        }
    }

    /*
     * If any options were changed to 'on', delete their individual caches, and delete 'alloptions' cache so that it
     * is refreshed as needed.
     * If no options were changed to 'on' but any options were changed to 'no', delete them from the 'alloptions'
     * cache. This is not necessary when options were changed to 'on', since in that situation the entire cache is
     * deleted anyway.
     */
    if ($grouped_options['on']) {
        wp_cache_delete_multiple($grouped_options['on'], 'options');
        wp_cache_delete('alloptions', 'options');
    } elseif ($grouped_options['off']) {
        $alloptions = wp_load_alloptions(true);

        foreach ($grouped_options['off'] as $option) {
            if (isset($alloptions[$option])) {
                unset($alloptions[$option]);
            }
        }

        wp_cache_set('alloptions', $alloptions, 'options');
    }

    return $results;
}

/**
 * Sets the autoload value for multiple options in the database.
 *
 * This is a wrapper for {@see wp_set_option_autoload_values()}, which can be used to set different autoload values for
 * each option at once.
 *
 * @since WM-0.4.0
 * @since WP-6.4.0
 * @since WP-6.7.0 The autoload values 'yes' and 'no' are deprecated.
 * @see wp_set_option_autoload_values()
 *
 * @param  string[]  $options  List of option names. Expected to not be SQL-escaped.
 * @param  bool  $autoload  Autoload value to control whether to load the options when WordPress starts up.
 *                          For backward compatibility 'yes' and 'no' are also accepted, though using these values is
 *                          deprecated.
 * @return array Associative array of all provided $options as keys and boolean values for whether their autoload value
 *               was updated.
 */
function wp_set_options_autoload(array $options, $autoload)
{
    return wp_set_option_autoload_values(
        array_fill_keys($options, $autoload)
    );
}

/**
 * Sets the autoload value for an option in the database.
 *
 * This is a wrapper for {@see wp_set_option_autoload_values()}, which can be used to set the autoload value for
 * multiple options at once.
 *
 * @since WM-0.4.0
 * @since WP-6.4.0
 * @since WP-6.7.0 The autoload values 'yes' and 'no' are deprecated.
 * @see wp_set_option_autoload_values()
 *
 * @param  string  $option  Name of the option. Expected to not be SQL-escaped.
 * @param  bool  $autoload  Autoload value to control whether to load the option when WordPress starts up.
 *                          For backward compatibility 'yes' and 'no' are also accepted, though using these values is
 *                          deprecated.
 * @return bool True if the autoload value was modified, false otherwise.
 */
function wp_set_option_autoload($option, $autoload)
{
    $result = wp_set_option_autoload_values([$option => $autoload]);

    return $result[$option] ?? false;
}

/**
 * Protects WordPress special option from being modified.
 *
 * Will die if $option is in protected list. Protected options are 'alloptions'
 * and 'notoptions' options.
 *
 * @since WM-0.4.0
 * @since WP-2.2.0
 *
 * @param  string  $option  Option name.
 * @return void Never returns if `$option` is protected, as the function dies in that case.
 *
 * @phpstan-return ( $option is 'alloptions'|'notoptions' ? never : void )
 */
function wp_protect_special_option($option)
{
    if ($option === 'alloptions' || $option === 'notoptions') {
        wp_die(
            sprintf(
                /* translators: %s: Option name. */
                __('%s is a protected WP option and may not be modified'),
                // WM: WordPress passes it through esc_html() (the formatting step);
                // here it is always one of the two fixed names above.
                $option
            )
        );
    }
}

/**
 * Loads and caches all autoloaded options, if available or all options.
 *
 * @since WM-0.4.0
 * @since WP-2.2.0
 * @since WP-5.3.1 The `$force_cache` parameter was added.
 *
 * @param  bool  $force_cache  Optional. Whether to force an update of the local cache
 *                             from the persistent cache. Default false.
 * @return array List of all options.
 */
function wp_load_alloptions($force_cache = false)
{
    /**
     * Filters the array of alloptions before it is populated.
     *
     * Returning an array from the filter will effectively short circuit
     * wp_load_alloptions(), returning that value instead.
     *
     * @since WP-6.2.0
     *
     * @param  array|null  $alloptions  An array of alloptions. Default null.
     * @param  bool  $force_cache  Whether to force an update of the local cache from the persistent cache. Default false.
     */
    $alloptions = apply_filters('pre_wp_load_alloptions', null, $force_cache);
    if (is_array($alloptions)) {
        return $alloptions;
    }

    if (! wp_installing() || ! is_multisite()) {
        $alloptions = wp_cache_get('alloptions', 'options', $force_cache);
    } else {
        $alloptions = false;
    }

    if (! $alloptions) {
        // WordPress suppresses errors here (the table may not exist yet while installing).
        try {
            $repository = app(OptionRepository::class);
            $alloptions = $repository->autoloaded(array_values(wp_autoload_values_to_autoload()));

            if (! $alloptions) {
                $alloptions = $repository->all();
            }
        } catch (QueryException) {
            $alloptions = [];
        }

        if (! wp_installing() || ! is_multisite()) {
            /**
             * Filters all options before caching them.
             *
             * @since WP-4.9.0
             *
             * @param  array  $alloptions  Array with all options.
             */
            $alloptions = apply_filters('pre_cache_alloptions', $alloptions);

            wp_cache_add('alloptions', $alloptions, 'options');
        }
    }

    /**
     * Filters all options after retrieving them.
     *
     * @since WP-4.9.0
     *
     * @param  array  $alloptions  Array with all options.
     */
    return apply_filters('alloptions', $alloptions);
}

/**
 * Primes specific network options for the current network into the cache with a single database query.
 *
 * Only network options that do not already exist in cache will be loaded.
 *
 * If site is not multisite, then call wp_prime_option_caches().
 *
 * @since WM-0.4.0
 * @since WP-6.6.0
 * @see wp_prime_network_option_caches()
 *
 * @param  string[]  $options  An array of option names to be loaded.
 */
function wp_prime_site_option_caches(array $options)
{
    wp_prime_network_option_caches(null, $options);
}

/**
 * Primes specific network options into the cache with a single database query.
 *
 * Only network options that do not already exist in cache will be loaded.
 *
 * If site is not multisite, then call wp_prime_option_caches().
 *
 * @since WM-0.4.0
 * @since WP-6.6.0
 *
 * @param  int|null  $network_id  ID of the network. Can be null to default to the current network ID.
 * @param  string[]  $options  An array of option names to be loaded.
 */
function wp_prime_network_option_caches($network_id, array $options)
{
    if (wp_installing()) {
        return;
    }

    if (! is_multisite()) {
        wp_prime_option_caches($options);

        return;
    }
    // WM: the multisite branch (the sitemeta table) is left out: there is no multisite.
}

/**
 * Updates the value of an option that was already added.
 *
 * You do not need to serialize values. If the value needs to be serialized,
 * then it will be serialized before it is inserted into the database.
 * Remember, resources cannot be serialized or added as an option.
 *
 * If the option does not exist, it will be created.

 * This function is designed to work with or without a logged-in user. In terms of security,
 * plugin developers should check the current user's capabilities before updating any options.
 *
 * @since WM-0.4.0
 * @since WP-1.0.0
 * @since WP-4.2.0 The `$autoload` parameter was added.
 * @since WP-6.7.0 The autoload values 'yes' and 'no' are deprecated.
 *
 * @param  string  $option  Name of the option to update. Expected to not be SQL-escaped.
 * @param  mixed  $value  Option value. Must be serializable if non-scalar. Expected to not be SQL-escaped.
 * @param  bool|null  $autoload  Optional. Whether to load the option when WordPress starts up.
 *                               Accepts a boolean, or `null` to stick with the initial value or, if no initial value is
 *                               set, to leave the decision up to default heuristics in WordPress.
 *                               For existing options, `$autoload` can only be updated using `update_option()` if `$value`
 *                               is also changed.
 *                               For backward compatibility 'yes' and 'no' are also accepted, though using these values is
 *                               deprecated.
 *                               Autoloading too many options can lead to performance problems, especially if the
 *                               options are not frequently used. For options which are accessed across several places
 *                               in the frontend, it is recommended to autoload them, by using true.
 *                               For options which are accessed only on few specific URLs, it is recommended
 *                               to not autoload them, by using false.
 *                               For non-existent options, the default is null, which means WordPress will determine
 *                               the autoload value.
 * @return bool True if the value was updated, false otherwise.
 */
function update_option($option, $value, $autoload = null)
{

    if (is_scalar($option)) {
        $option = trim($option);
    }

    if (empty($option)) {
        return false;
    }

    /*
     * Until a proper _deprecated_option() function can be introduced,
     * redirect requests to deprecated keys to the new, correct ones.
     */
    $deprecated_keys = [
        'blacklist_keys' => 'disallowed_keys',
        'comment_whitelist' => 'comment_previously_approved',
    ];

    if (isset($deprecated_keys[$option]) && ! wp_installing()) {
        _deprecated_argument(
            __FUNCTION__,
            '5.5.0',
            sprintf(
                /* translators: 1: Deprecated option key, 2: New option key. */
                __('The "%1$s" option key has been renamed to "%2$s".'),
                $option,
                $deprecated_keys[$option]
            )
        );

        return update_option($deprecated_keys[$option], $value, $autoload);
    }

    wp_protect_special_option($option);

    if (is_object($value)) {
        $value = clone $value;
    }

    $value = sanitize_option($option, $value);
    $old_value = get_option($option);

    /**
     * Filters a specific option before its value is (maybe) serialized and updated.
     *
     * The dynamic portion of the hook name, `$option`, refers to the option name.
     *
     * @since WP-2.6.0
     * @since WP-4.4.0 The `$option` parameter was added.
     *
     * @param  mixed  $value  The new, unserialized option value.
     * @param  mixed  $old_value  The old option value.
     * @param  string  $option  Option name.
     */
    $value = apply_filters("pre_update_option_{$option}", $value, $old_value, $option);

    /**
     * Filters an option before its value is (maybe) serialized and updated.
     *
     * @since WP-3.9.0
     *
     * @param  mixed  $value  The new, unserialized option value.
     * @param  string  $option  Name of the option.
     * @param  mixed  $old_value  The old option value.
     */
    $value = apply_filters('pre_update_option', $value, $option, $old_value);

    /*
     * If the new and old values are the same, no need to update.
     *
     * Unserialized values will be adequate in most cases. If the unserialized
     * data differs, the (maybe) serialized data is checked to avoid
     * unnecessary database calls for otherwise identical object instances.
     *
     * See https://core.trac.wordpress.org/ticket/38903
     */
    if ($value === $old_value || maybe_serialize($value) === maybe_serialize($old_value)) {
        return false;
    }

    /** This filter is documented in wp-includes/option.php */
    if (apply_filters("default_option_{$option}", false, $option, false) === $old_value) {
        return add_option($option, $value, '', $autoload);
    }

    $serialized_value = maybe_serialize($value);

    /**
     * Fires immediately before an option value is updated.
     *
     * @since WP-2.9.0
     *
     * @param  string  $option  Name of the option to update.
     * @param  mixed  $old_value  The old option value.
     * @param  mixed  $value  The new option value.
     */
    do_action('update_option', $option, $old_value, $value);

    $update_args = [
        'option_value' => $serialized_value,
    ];

    if ($autoload !== null) {
        $update_args['autoload'] = wp_determine_option_autoload_value($option, $value, $serialized_value, $autoload);
    } else {
        // Retrieve the current autoload value to reevaluate it in case it was set automatically.
        $raw_autoload = app(OptionRepository::class)->autoloadOf($option);
        $allow_values = ['auto-on', 'auto-off', 'auto'];
        if (in_array($raw_autoload, $allow_values, true)) {
            $autoload = wp_determine_option_autoload_value($option, $value, $serialized_value, $autoload);
            if ($autoload !== $raw_autoload) {
                $update_args['autoload'] = $autoload;
            }
        }
    }

    // WM: stored as text, as $wpdb->update()'s %s format would.
    $result = app(OptionRepository::class)->update($option, array_map('strval', $update_args));
    if (! $result) {
        return false;
    }

    $notoptions = wp_cache_get('notoptions', 'options');

    if (is_array($notoptions) && isset($notoptions[$option])) {
        unset($notoptions[$option]);
        wp_cache_set('notoptions', $notoptions, 'options');
    }

    if (! wp_installing()) {
        if (! isset($update_args['autoload'])) {
            // Update the cached value based on where it is currently cached.
            $alloptions = wp_load_alloptions(true);

            if (isset($alloptions[$option])) {
                $alloptions[$option] = $serialized_value;
                wp_cache_set('alloptions', $alloptions, 'options');
            } else {
                wp_cache_set($option, $serialized_value, 'options');
            }
        } elseif (in_array($update_args['autoload'], wp_autoload_values_to_autoload(), true)) {
            // Delete the individual cache, then set in alloptions cache.
            wp_cache_delete($option, 'options');

            $alloptions = wp_load_alloptions(true);

            $alloptions[$option] = $serialized_value;
            wp_cache_set('alloptions', $alloptions, 'options');
        } else {
            // Delete the alloptions cache, then set the individual cache.
            $alloptions = wp_load_alloptions(true);

            if (isset($alloptions[$option])) {
                unset($alloptions[$option]);
                wp_cache_set('alloptions', $alloptions, 'options');
            }

            wp_cache_set($option, $serialized_value, 'options');
        }
    }

    /**
     * Fires after the value of a specific option has been successfully updated.
     *
     * The dynamic portion of the hook name, `$option`, refers to the option name.
     *
     * @since WP-2.0.1
     * @since WP-4.4.0 The `$option` parameter was added.
     *
     * @param  mixed  $old_value  The old option value.
     * @param  mixed  $value  The new option value.
     * @param  string  $option  Option name.
     */
    do_action("update_option_{$option}", $old_value, $value, $option);

    /**
     * Fires after the value of an option has been successfully updated.
     *
     * WM: announced as the Laravel event OptionUpdated, which HookBus bridges
     * to this action, so Laravel listeners and plugins both hear it once.
     *
     * @since WP-2.9.0
     *
     * @param  string  $option  Name of the updated option.
     * @param  mixed  $old_value  The old option value.
     * @param  mixed  $value  The new option value.
     */
    event(new OptionUpdated($option, $old_value, $value));

    return true;
}

/**
 * Adds a new option.
 *
 * You do not need to serialize values. If the value needs to be serialized,
 * then it will be serialized before it is inserted into the database.
 * Remember, resources cannot be serialized or added as an option.
 *
 * You can create options without values and then update the values later.
 * Existing options will not be updated and checks are performed to ensure that you
 * aren't adding a protected WordPress option. Care should be taken to not name
 * options the same as the ones which are protected.
 *
 * @since WM-0.4.0
 * @since WP-1.0.0
 * @since WP-6.6.0 The $autoload parameter's default value was changed to null.
 * @since WP-6.7.0 The autoload values 'yes' and 'no' are deprecated.
 *
 * @param  string  $option  Name of the option to add. Expected to not be SQL-escaped.
 * @param  mixed  $value  Optional. Option value. Must be serializable if non-scalar.
 *                        Expected to not be SQL-escaped.
 * @param  string  $deprecated  Optional. Description. Not used anymore.
 * @param  bool|null  $autoload  Optional. Whether to load the option when WordPress starts up.
 *                               Accepts a boolean, or `null` to leave the decision up to default heuristics in
 *                               WordPress. For backward compatibility 'yes' and 'no' are also accepted, though using
 *                               these values is deprecated.
 *                               Autoloading too many options can lead to performance problems, especially if the
 *                               options are not frequently used. For options which are accessed across several places
 *                               in the frontend, it is recommended to autoload them, by using true.
 *                               For options which are accessed only on few specific URLs, it is recommended
 *                               to not autoload them, by using false.
 *                               Default is null, which means WordPress will determine the autoload value.
 * @return bool True if the option was added, false otherwise.
 */
function add_option($option, $value = '', $deprecated = '', $autoload = null)
{

    if (! empty($deprecated)) {
        _deprecated_argument(__FUNCTION__, '2.3.0');
    }

    if (is_scalar($option)) {
        $option = trim($option);
    }

    if (empty($option)) {
        return false;
    }

    /*
     * Until a proper _deprecated_option() function can be introduced,
     * redirect requests to deprecated keys to the new, correct ones.
     */
    $deprecated_keys = [
        'blacklist_keys' => 'disallowed_keys',
        'comment_whitelist' => 'comment_previously_approved',
    ];

    if (isset($deprecated_keys[$option]) && ! wp_installing()) {
        _deprecated_argument(
            __FUNCTION__,
            '5.5.0',
            sprintf(
                /* translators: 1: Deprecated option key, 2: New option key. */
                __('The "%1$s" option key has been renamed to "%2$s".'),
                $option,
                $deprecated_keys[$option]
            )
        );

        return add_option($deprecated_keys[$option], $value, $deprecated, $autoload);
    }

    wp_protect_special_option($option);

    if (is_object($value)) {
        $value = clone $value;
    }

    $value = sanitize_option($option, $value);

    /*
     * Make sure the option doesn't already exist.
     * We can check the 'notoptions' cache before we ask for a DB query.
     */
    $notoptions = wp_cache_get('notoptions', 'options');

    if (! is_array($notoptions) || ! isset($notoptions[$option])) {
        /** This filter is documented in wp-includes/option.php */
        if (apply_filters("default_option_{$option}", false, $option, false) !== get_option($option)) {
            return false;
        }
    }

    $serialized_value = maybe_serialize($value);

    $autoload = wp_determine_option_autoload_value($option, $value, $serialized_value, $autoload);

    /**
     * Fires before an option is added.
     *
     * @since WP-2.9.0
     *
     * @param  string  $option  Name of the option to add.
     * @param  mixed  $value  Value of the option.
     */
    do_action('add_option', $option, $value);

    // WM: WordPress's INSERT … ON DUPLICATE KEY UPDATE, written portably; values as text, as %s would.
    $result = app(OptionRepository::class)->upsert((string) $option, (string) $serialized_value, (string) $autoload);
    if (! $result) {
        return false;
    }

    if (! wp_installing()) {
        if (in_array($autoload, wp_autoload_values_to_autoload(), true)) {
            $alloptions = wp_load_alloptions(true);
            $alloptions[$option] = $serialized_value;
            wp_cache_set('alloptions', $alloptions, 'options');
        } else {
            wp_cache_set($option, $serialized_value, 'options');
        }
    }

    // This option exists now.
    $notoptions = wp_cache_get('notoptions', 'options'); // Yes, again... we need it to be fresh.

    if (is_array($notoptions) && isset($notoptions[$option])) {
        unset($notoptions[$option]);
        wp_cache_set('notoptions', $notoptions, 'options');
    }

    /**
     * Fires after a specific option has been added.
     *
     * The dynamic portion of the hook name, `$option`, refers to the option name.
     *
     * @since WP-2.5.0 As `add_option_{$name}`
     * @since WP-3.0.0
     *
     * @param  string  $option  Name of the option to add.
     * @param  mixed  $value  Value of the option.
     */
    do_action("add_option_{$option}", $option, $value);

    /**
     * Fires after an option has been added.
     *
     * WM: announced as the Laravel event OptionAdded, which HookBus bridges
     * to this action, so Laravel listeners and plugins both hear it once.
     *
     * @since WP-2.9.0
     *
     * @param  string  $option  Name of the added option.
     * @param  mixed  $value  Value of the option.
     */
    event(new OptionAdded($option, $value));

    return true;
}

/**
 * Removes an option by name. Prevents removal of protected WordPress options.
 *
 * @since WM-0.4.0
 * @since WP-1.2.0
 *
 * @param  string  $option  Name of the option to delete. Expected to not be SQL-escaped.
 * @return bool True if the option was deleted, false otherwise.
 */
function delete_option($option)
{

    if (is_scalar($option)) {
        $option = trim($option);
    }

    if (empty($option)) {
        return false;
    }

    wp_protect_special_option($option);

    // Get the ID, if no ID then return.
    $row_autoload = app(OptionRepository::class)->autoloadOf($option);
    if (is_null($row_autoload)) {
        return false;
    }

    /**
     * Fires immediately before an option is deleted.
     *
     * @since WP-2.9.0
     *
     * @param  string  $option  Name of the option to delete.
     */
    do_action('delete_option', $option);

    $result = app(OptionRepository::class)->delete($option);

    if (! wp_installing()) {
        if (in_array($row_autoload, wp_autoload_values_to_autoload(), true)) {
            $alloptions = wp_load_alloptions(true);

            if (is_array($alloptions) && isset($alloptions[$option])) {
                unset($alloptions[$option]);
                wp_cache_set('alloptions', $alloptions, 'options');
            }
        } else {
            wp_cache_delete($option, 'options');
        }

        $notoptions = wp_cache_get('notoptions', 'options');

        if (! is_array($notoptions)) {
            $notoptions = [];
        }
        $notoptions[$option] = true;

        wp_cache_set('notoptions', $notoptions, 'options');
    }

    if ($result) {

        /**
         * Fires after a specific option has been deleted.
         *
         * The dynamic portion of the hook name, `$option`, refers to the option name.
         *
         * @since WP-3.0.0
         *
         * @param  string  $option  Name of the deleted option.
         */
        do_action("delete_option_{$option}", $option);

        /**
         * Fires after an option has been deleted.
         *
         * WM: announced as the Laravel event OptionDeleted, which HookBus
         * bridges to this action, so Laravel listeners and plugins both hear it once.
         *
         * @since WP-2.9.0
         *
         * @param  string  $option  Name of the deleted option.
         */
        event(new OptionDeleted($option));

        return true;
    }

    return false;
}

/**
 *  Determines the appropriate autoload value for an option based on input.
 *
 *  This function checks the provided autoload value and returns a standardized value
 *  ('on', 'off', 'auto-on', 'auto-off', or 'auto') based on specific conditions.
 *
 * If no explicit autoload value is provided, the function will check for certain heuristics around the given option.
 * It will return `auto-on` to indicate autoloading, `auto-off` to indicate not autoloading, or `auto` if no clear
 * decision could be made.
 *
 * @since WM-0.4.0
 * @since WP-6.6.0
 *
 * @param  string  $option  The name of the option.
 * @param  mixed  $value  The value of the option to check its autoload value.
 * @param  mixed  $serialized_value  The serialized value of the option to check its autoload value.
 * @param  bool|null  $autoload  The autoload value to check.
 *                               Accepts 'on'|true to enable or 'off'|false to disable, or
 *                               'auto-on', 'auto-off', or 'auto' for internal purposes.
 *                               Any other autoload value will be forced to either 'auto-on',
 *                               'auto-off', or 'auto'.
 *                               'yes' and 'no' are supported for backward compatibility.
 * @return string Returns the original $autoload value if explicit, or 'auto-on', 'auto-off',
 *                or 'auto' depending on default heuristics.
 */
function wp_determine_option_autoload_value($option, $value, $serialized_value, $autoload)
{

    // Check if autoload is a boolean.
    if (is_bool($autoload)) {
        return $autoload ? 'on' : 'off';
    }

    switch ($autoload) {
        case 'on':
        case 'yes':
            return 'on';
        case 'off':
        case 'no':
            return 'off';
    }

    /**
     * Allows to determine the default autoload value for an option where no explicit value is passed.
     *
     * @since WP-6.6.0
     *
     * @param  bool|null  $autoload  The default autoload value to set. Returning true will be set as 'auto-on' in the
     *                               database, false will be set as 'auto-off', and null will be set as 'auto'.
     * @param  string  $option  The passed option name.
     * @param  mixed  $value  The passed option value to be saved.
     * @param  mixed  $serialized_value  The passed option value to be saved, in serialized form.
     */
    $autoload = apply_filters('wp_default_autoload_value', null, $option, $value, $serialized_value);
    if (is_bool($autoload)) {
        return $autoload ? 'auto-on' : 'auto-off';
    }

    return 'auto';
}

/**
 * Filters the default autoload value to disable autoloading if the option value is too large.
 *
 * @since WM-0.4.0
 * @since WP-6.6.0
 *
 * @param  bool|null  $autoload  The default autoload value to set.
 * @param  string  $option  The passed option name.
 * @param  mixed  $value  The passed option value to be saved.
 * @param  mixed  $serialized_value  The passed option value to be saved, in serialized form.
 * @return bool|null Potentially modified $default.
 */
function wp_filter_default_autoload_value_via_option_size($autoload, $option, $value, $serialized_value)
{
    /**
     * Filters the maximum size of option value in bytes.
     *
     * @since WP-6.6.0
     *
     * @param  int  $max_option_size  The option-size threshold, in bytes. Default 150000.
     * @param  string  $option  The name of the option.
     */
    $max_option_size = (int) apply_filters('wp_max_autoloaded_option_size', 150000, $option);
    $size = ! empty($serialized_value) ? strlen($serialized_value) : 0;

    if ($size > $max_option_size) {
        return false;
    }

    return $autoload;
}

/**
 * Retrieve an option value for the current network based on name of option.
 *
 * @since WM-0.4.0
 * @since WP-2.8.0
 * @since WP-4.4.0 The `$use_cache` parameter was deprecated.
 * @since WP-4.4.0 Modified into wrapper for get_network_option()
 * @see get_network_option()
 *
 * @param  string  $option  Name of the option to retrieve. Expected to not be SQL-escaped.
 * @param  mixed  $default_value  Optional. Value to return if the option doesn't exist. Default false.
 * @param  bool  $deprecated  Whether to use cache. Multisite only. Always set to true.
 * @return mixed Value set for the option.
 */
function get_site_option($option, $default_value = false, $deprecated = true)
{
    return get_network_option(null, $option, $default_value);
}

/**
 * Adds a new option for the current network.
 *
 * Existing options will not be updated. Note that prior to 3.3 this wasn't the case.
 *
 * @since WM-0.4.0
 * @since WP-2.8.0
 * @since WP-4.4.0 Modified into wrapper for add_network_option()
 * @see add_network_option()
 *
 * @param  string  $option  Name of the option to add. Expected to not be SQL-escaped.
 * @param  mixed  $value  Option value, can be anything. Expected to not be SQL-escaped.
 * @return bool True if the option was added, false otherwise.
 */
function add_site_option($option, $value)
{
    return add_network_option(null, $option, $value);
}

/**
 * Removes an option by name for the current network.
 *
 * @since WM-0.4.0
 * @since WP-2.8.0
 * @since WP-4.4.0 Modified into wrapper for delete_network_option()
 * @see delete_network_option()
 *
 * @param  string  $option  Name of the option to delete. Expected to not be SQL-escaped.
 * @return bool True if the option was deleted, false otherwise.
 */
function delete_site_option($option)
{
    return delete_network_option(null, $option);
}

/**
 * Updates the value of an option that was already added for the current network.
 *
 * @since WM-0.4.0
 * @since WP-2.8.0
 * @since WP-4.4.0 Modified into wrapper for update_network_option()
 * @see update_network_option()
 *
 * @param  string  $option  Name of the option. Expected to not be SQL-escaped.
 * @param  mixed  $value  Option value. Expected to not be SQL-escaped.
 * @return bool True if the value was updated, false otherwise.
 */
function update_site_option($option, $value)
{
    return update_network_option(null, $option, $value);
}

/**
 * Retrieves a network's option value based on the option name.
 *
 * @since WM-0.4.0
 * @since WP-4.4.0
 * @see get_option()
 *
 * @param  int|null  $network_id  ID of the network. Can be null to default to the current network ID.
 * @param  string  $option  Name of the option to retrieve. Expected to not be SQL-escaped.
 * @param  mixed  $default_value  Optional. Value to return if the option doesn't exist. Default false.
 * @return mixed Value set for the option.
 */
function get_network_option($network_id, $option, $default_value = false)
{

    if ($network_id && ! is_numeric($network_id)) {
        return false;
    }

    $network_id = (int) $network_id;

    // Fallback to the current network if a network ID is not specified.
    if (! $network_id) {
        $network_id = get_current_network_id();
    }

    /**
     * Filters the value of an existing network option before it is retrieved.
     *
     * The dynamic portion of the hook name, `$option`, refers to the option name.
     *
     * Returning a value other than false from the filter will short-circuit retrieval
     * and return that value instead.
     *
     * @since WP-2.9.0 As `pre_site_option_{$key}`.
     * @since WP-3.0.0
     * @since WP-4.4.0 The `$option` parameter was added.
     * @since WP-4.7.0 The `$network_id` parameter was added.
     * @since WP-4.9.0 The `$default_value` parameter was added.
     *
     * @param  mixed  $pre_site_option  The value to return instead of the option value. This differs from
     *                                  `$default_value`, which is used as the fallback value in the event
     *                                  the option doesn't exist elsewhere in get_network_option().
     *                                  Default false (to skip past the short-circuit).
     * @param  string  $option  Option name.
     * @param  int  $network_id  ID of the network.
     * @param  mixed  $default_value  The fallback value to return if the option does not exist.
     *                                Default false.
     */
    $pre = apply_filters("pre_site_option_{$option}", false, $option, $network_id, $default_value);

    /**
     * Filters the value of any existing network option before it is retrieved.
     *
     * Returning a value other than false from the filter will short-circuit retrieval
     * and return that value instead.
     *
     * @since WP-6.9.0
     *
     * @param  mixed  $pre_option  The value to return instead of the network option value. This differs
     *                             from `$default_value`, which is used as the fallback value in the event
     *                             the option doesn't exist elsewhere in get_network_option().
     *                             Default false (to skip past the short-circuit).
     * @param  string  $option  Name of the option.
     * @param  int  $network_id  ID of the network.
     * @param  mixed  $default_value  The fallback value to return if the option does not exist.
     *                                Default false.
     */
    $pre = apply_filters('pre_site_option', $pre, $option, $network_id, $default_value);

    if ($pre !== false) {
        return $pre;
    }

    // Prevent non-existent options from triggering multiple queries.
    $notoptions_key = "$network_id:notoptions";
    $notoptions = wp_cache_get($notoptions_key, 'site-options');

    if (is_array($notoptions) && isset($notoptions[$option])) {

        /**
         * Filters the value of a specific default network option.
         *
         * The dynamic portion of the hook name, `$option`, refers to the option name.
         *
         * @since WP-3.4.0
         * @since WP-4.4.0 The `$option` parameter was added.
         * @since WP-4.7.0 The `$network_id` parameter was added.
         *
         * @param  mixed  $default_value  The value to return if the site option does not exist
         *                                in the database.
         * @param  string  $option  Option name.
         * @param  int  $network_id  ID of the network.
         */
        return apply_filters("default_site_option_{$option}", $default_value, $option, $network_id);
    }

    if (! is_multisite()) {
        /** This filter is documented in wp-includes/option.php */
        $default_value = apply_filters('default_site_option_'.$option, $default_value, $option, $network_id);
        $value = get_option($option, $default_value);
    }
    // WM: the multisite branch (the sitemeta table) is left out: there is no multisite.

    if (! is_array($notoptions)) {
        $notoptions = [];
        wp_cache_set($notoptions_key, $notoptions, 'site-options');
    }

    /**
     * Filters the value of an existing network option.
     *
     * The dynamic portion of the hook name, `$option`, refers to the option name.
     *
     * @since WP-2.9.0 As `site_option_{$key}`.
     * @since WP-3.0.0
     * @since WP-4.4.0 The `$option` parameter was added.
     * @since WP-4.7.0 The `$network_id` parameter was added.
     *
     * @param  mixed  $value  Value of network option.
     * @param  string  $option  Option name.
     * @param  int  $network_id  ID of the network.
     */
    return apply_filters("site_option_{$option}", $value, $option, $network_id);
}

/**
 * Adds a new network option.
 *
 * Existing options will not be updated.
 *
 * @since WM-0.4.0
 * @since WP-4.4.0
 * @see add_option()
 *
 * @param  int|null  $network_id  ID of the network. Can be null to default to the current network ID.
 * @param  string  $option  Name of the option to add. Expected to not be SQL-escaped.
 * @param  mixed  $value  Option value, can be anything. Expected to not be SQL-escaped.
 * @return bool True if the option was added, false otherwise.
 */
function add_network_option($network_id, $option, $value)
{

    if ($network_id && ! is_numeric($network_id)) {
        return false;
    }

    $network_id = (int) $network_id;

    // Fallback to the current network if a network ID is not specified.
    if (! $network_id) {
        $network_id = get_current_network_id();
    }

    wp_protect_special_option($option);

    /**
     * Filters the value of a specific network option before it is added.
     *
     * The dynamic portion of the hook name, `$option`, refers to the option name.
     *
     * @since WP-2.9.0 As `pre_add_site_option_{$key}`.
     * @since WP-3.0.0
     * @since WP-4.4.0 The `$option` parameter was added.
     * @since WP-4.7.0 The `$network_id` parameter was added.
     *
     * @param  mixed  $value  Value of network option.
     * @param  string  $option  Option name.
     * @param  int  $network_id  ID of the network.
     */
    $value = apply_filters("pre_add_site_option_{$option}", $value, $option, $network_id);

    $notoptions_key = "$network_id:notoptions";

    if (! is_multisite()) {
        $result = add_option($option, $value, '', false);
    }
    // WM: the multisite branch (the sitemeta table) is left out: there is no multisite.

    if ($result) {

        /**
         * Fires after a specific network option has been successfully added.
         *
         * The dynamic portion of the hook name, `$option`, refers to the option name.
         *
         * @since WP-2.9.0 As `add_site_option_{$key}`.
         * @since WP-3.0.0
         * @since WP-4.7.0 The `$network_id` parameter was added.
         *
         * @param  string  $option  Name of the network option.
         * @param  mixed  $value  Value of the network option.
         * @param  int  $network_id  ID of the network.
         */
        do_action("add_site_option_{$option}", $option, $value, $network_id);

        /**
         * Fires after a network option has been successfully added.
         *
         * @since WP-3.0.0
         * @since WP-4.7.0 The `$network_id` parameter was added.
         *
         * @param  string  $option  Name of the network option.
         * @param  mixed  $value  Value of the network option.
         * @param  int  $network_id  ID of the network.
         */
        do_action('add_site_option', $option, $value, $network_id);

        return true;
    }

    return false;
}

/**
 * Removes a network option by name.
 *
 * @since WM-0.4.0
 * @since WP-4.4.0
 * @see delete_option()
 *
 * @param  int|null  $network_id  ID of the network. Can be null to default to the current network ID.
 * @param  string  $option  Name of the option to delete. Expected to not be SQL-escaped.
 * @return bool True if the option was deleted, false otherwise.
 */
function delete_network_option($network_id, $option)
{

    if ($network_id && ! is_numeric($network_id)) {
        return false;
    }

    $network_id = (int) $network_id;

    // Fallback to the current network if a network ID is not specified.
    if (! $network_id) {
        $network_id = get_current_network_id();
    }

    /**
     * Fires immediately before a specific network option is deleted.
     *
     * The dynamic portion of the hook name, `$option`, refers to the option name.
     *
     * @since WP-3.0.0
     * @since WP-4.4.0 The `$option` parameter was added.
     * @since WP-4.7.0 The `$network_id` parameter was added.
     *
     * @param  string  $option  Option name.
     * @param  int  $network_id  ID of the network.
     */
    do_action("pre_delete_site_option_{$option}", $option, $network_id);

    if (! is_multisite()) {
        $result = delete_option($option);
    }
    // WM: the multisite branch (the sitemeta table) is left out: there is no multisite.

    if ($result) {

        /**
         * Fires after a specific network option has been deleted.
         *
         * The dynamic portion of the hook name, `$option`, refers to the option name.
         *
         * @since WP-2.9.0 As `delete_site_option_{$key}`.
         * @since WP-3.0.0
         * @since WP-4.7.0 The `$network_id` parameter was added.
         *
         * @param  string  $option  Name of the network option.
         * @param  int  $network_id  ID of the network.
         */
        do_action("delete_site_option_{$option}", $option, $network_id);

        /**
         * Fires after a network option has been deleted.
         *
         * @since WP-3.0.0
         * @since WP-4.7.0 The `$network_id` parameter was added.
         *
         * @param  string  $option  Name of the network option.
         * @param  int  $network_id  ID of the network.
         */
        do_action('delete_site_option', $option, $network_id);

        return true;
    }

    return false;
}

/**
 * Updates the value of a network option that was already added.
 *
 * @since WM-0.4.0
 * @since WP-4.4.0
 * @see update_option()
 *
 * @param  int|null  $network_id  ID of the network. Can be null to default to the current network ID.
 * @param  string  $option  Name of the option. Expected to not be SQL-escaped.
 * @param  mixed  $value  Option value. Expected to not be SQL-escaped.
 * @return bool True if the value was updated, false otherwise.
 */
function update_network_option($network_id, $option, $value)
{

    if ($network_id && ! is_numeric($network_id)) {
        return false;
    }

    $network_id = (int) $network_id;

    // Fallback to the current network if a network ID is not specified.
    if (! $network_id) {
        $network_id = get_current_network_id();
    }

    wp_protect_special_option($option);

    $old_value = get_network_option($network_id, $option);

    /**
     * Filters a specific network option before its value is updated.
     *
     * The dynamic portion of the hook name, `$option`, refers to the option name.
     *
     * @since WP-2.9.0 As 'pre_update_site_option_' . $key
     * @since WP-3.0.0
     * @since WP-4.4.0 The `$option` parameter was added.
     * @since WP-4.7.0 The `$network_id` parameter was added.
     *
     * @param  mixed  $value  New value of the network option.
     * @param  mixed  $old_value  Old value of the network option.
     * @param  string  $option  Option name.
     * @param  int  $network_id  ID of the network.
     */
    $value = apply_filters("pre_update_site_option_{$option}", $value, $old_value, $option, $network_id);

    /*
     * If the new and old values are the same, no need to update.
     *
     * Unserialized values will be adequate in most cases. If the unserialized
     * data differs, the (maybe) serialized data is checked to avoid
     * unnecessary database calls for otherwise identical object instances.
     *
     * See https://core.trac.wordpress.org/ticket/44956
     */
    if ($value === $old_value || maybe_serialize($value) === maybe_serialize($old_value)) {
        return false;
    }

    if ($old_value === false) {
        return add_network_option($network_id, $option, $value);
    }

    $notoptions_key = "$network_id:notoptions";
    $notoptions = wp_cache_get($notoptions_key, 'site-options');

    if (is_array($notoptions) && isset($notoptions[$option])) {
        unset($notoptions[$option]);
        wp_cache_set($notoptions_key, $notoptions, 'site-options');
    }

    if (! is_multisite()) {
        $result = update_option($option, $value, false);
    }
    // WM: the multisite branch (the sitemeta table) is left out: there is no multisite.

    if ($result) {

        /**
         * Fires after the value of a specific network option has been successfully updated.
         *
         * The dynamic portion of the hook name, `$option`, refers to the option name.
         *
         * @since WP-2.9.0 As "update_site_option_{$key}"
         * @since WP-3.0.0
         * @since WP-4.7.0 The `$network_id` parameter was added.
         *
         * @param  string  $option  Name of the network option.
         * @param  mixed  $value  Current value of the network option.
         * @param  mixed  $old_value  Old value of the network option.
         * @param  int  $network_id  ID of the network.
         */
        do_action("update_site_option_{$option}", $option, $value, $old_value, $network_id);

        /**
         * Fires after the value of a network option has been successfully updated.
         *
         * @since WP-3.0.0
         * @since WP-4.7.0 The `$network_id` parameter was added.
         *
         * @param  string  $option  Name of the network option.
         * @param  mixed  $value  Current value of the network option.
         * @param  mixed  $old_value  Old value of the network option.
         * @param  int  $network_id  ID of the network.
         */
        do_action('update_site_option', $option, $value, $old_value, $network_id);

        return true;
    }

    return false;
}

/**
 * Returns the values that trigger autoloading from the options table.
 *
 * @since WM-0.4.0
 * @since WP-6.6.0
 *
 * @return string[] The values that trigger autoloading.
 */
function wp_autoload_values_to_autoload()
{
    $autoload_values = ['yes', 'on', 'auto-on', 'auto'];

    /**
     * Filters the autoload values that should be considered for autoloading from the options table.
     *
     * The filter can only be used to remove autoload values from the default list.
     *
     * @since WP-6.6.0
     *
     * @param  string[]  $autoload_values  Autoload values used to autoload option.
     *                                     Default list contains 'yes', 'on', 'auto-on', and 'auto'.
     */
    $filtered_values = apply_filters('wp_autoload_values_to_autoload', $autoload_values);

    return array_intersect($filtered_values, $autoload_values);
}
