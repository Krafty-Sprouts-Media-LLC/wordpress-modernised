<?php

/**
 * Small callbacks that return a fixed value, used with filters.
 *
 * Contains code from WordPress 7.1.4: src/wp-includes/functions.php.
 */

/**
 * Returns true.
 *
 * Useful for returning true to filters easily.
 *
 * @since WM-0.1.0
 * @since WP-3.0.0
 * @see __return_false()
 *
 * @return true True.
 */
function __return_true()
{
    return true;
}

/**
 * Returns false.
 *
 * Useful for returning false to filters easily.
 *
 * @since WM-0.1.0
 * @since WP-3.0.0
 * @see __return_true()
 *
 * @return false False.
 */
function __return_false()
{
    return false;
}

/**
 * Returns 0.
 *
 * Useful for returning 0 to filters easily.
 *
 * @since WM-0.1.0
 * @since WP-3.0.0
 *
 * @return int 0.
 */
function __return_zero()
{
    return 0;
}

/**
 * Returns null.
 *
 * Useful for returning null to filters easily.
 *
 * @since WM-0.1.0
 * @since WP-3.4.0
 *
 * @return null Null value.
 */
function __return_null()
{
    return null;
}

/**
 * Returns an empty array.
 *
 * Useful for returning an empty array to filters easily.
 *
 * @since WM-0.1.0
 * @since WP-3.0.0
 *
 * @return array Empty array.
 */
function __return_empty_array()
{
    return [];
}

/**
 * Returns an empty string.
 *
 * Useful for returning an empty string to filters easily.
 *
 * @since WM-0.1.0
 * @since WP-3.7.0
 * @see __return_null()
 *
 * @return string Empty string.
 */
function __return_empty_string()
{
    return '';
}
