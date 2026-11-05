<?php

/**
 * WP_Exception: the base exception for WordPress code.
 *
 * Contains code from WordPress 7.1.4: src/wp-includes/class-wp-exception.php.
 */

/**
 * Core base Exception class.
 *
 * Future, more specific, Exceptions should always extend this base class.
 *
 * @since WM-0.1.0
 * @since WP-6.7.0
 */
class WP_Exception extends Exception {}
