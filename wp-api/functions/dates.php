<?php

/**
 * Dates and the site's time zone: current_time(), wp_timezone(), and
 * converting between the site's time and UTC.
 *
 * Contains code from WordPress 7.1.4: src/wp-includes/functions.php and
 * formatting.php.
 */

/**
 * Retrieves the current time based on specified type.
 *
 *  - The 'mysql' type will return the time in the format for MySQL DATETIME field.
 *  - The 'timestamp' or 'U' types will return the current timestamp or a sum of timestamp
 *    and timezone offset, depending on `$gmt`.
 *  - Other strings will be interpreted as PHP date formats (e.g. 'Y-m-d').
 *
 * If `$gmt` is a truthy value then both types will use GMT time, otherwise the
 * output is adjusted with the GMT offset for the site.
 *
 * @since WM-0.5.0
 * @since WP-1.0.0
 * @since WP-5.3.0 Now returns an integer if `$type` is 'U'. Previously a string was returned.
 *
 * @param  string  $type  Type of time to retrieve. Accepts 'mysql', 'timestamp', 'U',
 *                        or PHP date format string (e.g. 'Y-m-d').
 * @param  bool  $gmt  Optional. Whether to use GMT timezone. Default false.
 * @return int|string Integer if `$type` is 'timestamp' or 'U', string otherwise.
 */
function current_time($type, $gmt = false)
{
    // Don't use non-GMT timestamp, unless you know the difference and really need to.
    if ($type === 'timestamp' || $type === 'U') {
        return $gmt ? time() : time() + (int) ((float) get_option('gmt_offset') * HOUR_IN_SECONDS);
    }

    if ($type === 'mysql') {
        $type = 'Y-m-d H:i:s';
    }

    $timezone = $gmt ? new DateTimeZone('UTC') : wp_timezone();
    $datetime = new DateTime('now', $timezone);

    return $datetime->format($type);
}

/**
 * Retrieves the current time as an object using the site's timezone.
 *
 * @since WM-0.5.0
 * @since WP-5.3.0
 *
 * @return DateTimeImmutable Date and time object.
 */
function current_datetime()
{
    return new DateTimeImmutable('now', wp_timezone());
}

/**
 * Retrieves the timezone of the site as a string.
 *
 * Uses the `timezone_string` option to get a proper timezone name if available,
 * otherwise falls back to a manual UTC ± offset.
 *
 * Example return values:
 *
 *  - 'Europe/Rome'
 *  - 'America/North_Dakota/New_Salem'
 *  - 'UTC'
 *  - '-06:30'
 *  - '+00:00'
 *  - '+08:45'
 *
 * @since WM-0.5.0
 * @since WP-5.3.0
 *
 * @return string PHP timezone name or a ±HH:MM offset.
 */
function wp_timezone_string()
{
    $timezone_string = get_option('timezone_string');

    if ($timezone_string) {
        return $timezone_string;
    }

    $offset = (float) get_option('gmt_offset');
    $hours = (int) $offset;
    $minutes = ($offset - $hours);

    $sign = ($offset < 0) ? '-' : '+';
    $abs_hour = abs($hours);
    $abs_mins = abs($minutes * 60);
    $tz_offset = sprintf('%s%02d:%02d', $sign, $abs_hour, $abs_mins);

    return $tz_offset;
}

/**
 * Retrieves the timezone of the site as a `DateTimeZone` object.
 *
 * Timezone can be based on a PHP timezone string or a ±HH:MM offset.
 *
 * @since WM-0.5.0
 * @since WP-5.3.0
 *
 * @return DateTimeZone Timezone object.
 */
function wp_timezone()
{
    return new DateTimeZone(wp_timezone_string());
}

/**
 * Tests if the supplied date is valid for the Gregorian calendar.
 *
 * @since WM-0.5.0
 * @since WP-3.5.0
 * @link https://www.php.net/manual/en/function.checkdate.php
 *
 * @param  int  $month  Month number.
 * @param  int  $day  Day number.
 * @param  int  $year  Year number.
 * @param  string  $source_date  The date to filter.
 * @return bool True if valid date, false if not valid date.
 */
function wp_checkdate($month, $day, $year, $source_date)
{
    $checkdate = false;
    if (is_numeric($month) && is_numeric($day) && is_numeric($year)) {
        $checkdate = checkdate((int) $month, (int) $day, (int) $year);
    }

    /**
     * Filters whether the given date is valid for the Gregorian calendar.
     *
     * @since WP-3.5.0
     *
     * @param  bool  $checkdate  Whether the given date is valid.
     * @param  string  $source_date  Date to check.
     */
    return apply_filters('wp_checkdate', $checkdate, $source_date);
}

/**
 * Modifies gmt_offset for smart timezone handling.
 *
 * Overrides the gmt_offset option if we have a timezone_string available.
 *
 * @since WM-0.5.0
 * @since WP-2.8.0
 *
 * @return float|false Timezone GMT offset, false otherwise.
 */
function wp_timezone_override_offset()
{
    $timezone_string = get_option('timezone_string');
    if (! $timezone_string) {
        return false;
    }

    $timezone_object = timezone_open($timezone_string);
    $datetime_object = date_create();
    if ($timezone_object === false || $datetime_object === false) {
        return false;
    }

    return round(timezone_offset_get($timezone_object, $datetime_object) / HOUR_IN_SECONDS, 2);
}
