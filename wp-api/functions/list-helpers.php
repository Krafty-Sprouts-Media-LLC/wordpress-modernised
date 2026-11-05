<?php

/**
 * List helpers: wp_list_pluck(), wp_list_filter(), wp_list_sort() and wp_filter_object_list().
 *
 * Contains code from WordPress 7.1.4: src/wp-includes/functions.php.
 */

/**
 * Filters a list of objects, based on a set of key => value arguments.
 *
 * Retrieves the objects from the list that match the given arguments.
 * Key represents property name, and value represents property value.
 *
 * If an object has more properties than those specified in arguments,
 * that will not disqualify it. When using the 'AND' operator,
 * any missing properties will disqualify it.
 *
 * When using the `$field` argument, this function can also retrieve
 * a particular field from all matching objects, whereas wp_list_filter()
 * only does the filtering.
 *
 * @since WM-0.1.0
 * @since WP-3.0.0
 * @since WP-4.7.0 Uses `WP_List_Util` class.
 *
 * @param  array  $input_list  An array of objects to filter.
 * @param  array  $args  Optional. An array of key => value arguments to match
 *                       against each object. Default empty array.
 * @param  string  $operator  Optional. The logical operation to perform. 'AND' means
 *                            all elements from the array must match. 'OR' means only
 *                            one element needs to match. 'NOT' means no elements may
 *                            match. Default 'AND'.
 * @param  bool|string  $field  Optional. A field from the object to place instead
 *                              of the entire object. Default false.
 * @return array A list of objects or object fields.
 */
function wp_filter_object_list($input_list, $args = [], $operator = 'and', $field = false)
{
    if (! is_array($input_list)) {
        return [];
    }

    $util = new WP_List_Util($input_list);

    $util->filter($args, $operator);

    if ($field) {
        $util->pluck($field);
    }

    return $util->get_output();
}

/**
 * Filters a list of objects, based on a set of key => value arguments.
 *
 * Retrieves the objects from the list that match the given arguments.
 * Key represents property name, and value represents property value.
 *
 * If an object has more properties than those specified in arguments,
 * that will not disqualify it. When using the 'AND' operator,
 * any missing properties will disqualify it.
 *
 * If you want to retrieve a particular field from all matching objects,
 * use wp_filter_object_list() instead.
 *
 * @since WM-0.1.0
 * @since WP-3.1.0
 * @since WP-4.7.0 Uses `WP_List_Util` class.
 * @since WP-5.9.0 Converted into a wrapper for `wp_filter_object_list()`.
 *
 * @param  array  $input_list  An array of objects to filter.
 * @param  array  $args  Optional. An array of key => value arguments to match
 *                       against each object. Default empty array.
 * @param  string  $operator  Optional. The logical operation to perform. 'AND' means
 *                            all elements from the array must match. 'OR' means only
 *                            one element needs to match. 'NOT' means no elements may
 *                            match. Default 'AND'.
 * @return array Array of found values.
 */
function wp_list_filter($input_list, $args = [], $operator = 'AND')
{
    return wp_filter_object_list($input_list, $args, $operator);
}

/**
 * Plucks a certain field out of each object or array in an array.
 *
 * This has the same functionality and prototype of
 * array_column() (PHP 5.5) but also supports objects.
 *
 * @since WM-0.1.0
 * @since WP-3.1.0
 * @since WP-4.0.0 $index_key parameter added.
 * @since WP-4.7.0 Uses `WP_List_Util` class.
 *
 * @param  array  $input_list  List of objects or arrays.
 * @param  int|string  $field  Field from the object to place instead of the entire object.
 * @param  int|string  $index_key  Optional. Field from the object to use as keys for the new array.
 *                                 Default null.
 * @return array Array of found values. If `$index_key` is set, an array of found values with keys
 *               corresponding to `$index_key`. If `$index_key` is null, array keys from the original
 *               `$input_list` will be preserved in the results.
 */
function wp_list_pluck($input_list, $field, $index_key = null)
{
    if (! is_array($input_list)) {
        return [];
    }

    $util = new WP_List_Util($input_list);

    return $util->pluck($field, $index_key);
}

/**
 * Sorts an array of objects or arrays based on one or more orderby arguments.
 *
 * @since WM-0.1.0
 * @since WP-4.7.0
 *
 * @param  array  $input_list  An array of objects or arrays to sort.
 * @param  string|array  $orderby  Optional. Either the field name to order by or an array
 *                                 of multiple orderby fields as `$orderby => $order`.
 *                                 Default empty array.
 * @param  string  $order  Optional. Either 'ASC' or 'DESC'. Only used if `$orderby`
 *                         is a string. Default 'ASC'.
 * @param  bool  $preserve_keys  Optional. Whether to preserve keys. Default false.
 * @return array The sorted array.
 */
function wp_list_sort($input_list, $orderby = [], $order = 'ASC', $preserve_keys = false)
{
    if (! is_array($input_list)) {
        return [];
    }

    $util = new WP_List_Util($input_list);

    return $util->sort($orderby, $order, $preserve_keys);
}
