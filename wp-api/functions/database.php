<?php

/**
 * Database setup: wp_set_wpdb_vars() gives $wpdb its table prefix and column
 * formats; get_num_queries() reports the connection's query count.
 *
 * Contains code from WordPress 7.1.4: src/wp-includes/load.php and
 * src/wp-includes/functions.php.
 */

/**
 * Sets the database table prefix and the format specifiers for database
 * table columns.
 *
 * Columns not listed here default to `%s`.
 *
 * @since WM-0.2.0
 * @since WP-3.0.0
 *
 * @global wpdb   $wpdb         WordPress database abstraction object.
 * @global string $table_prefix The database table prefix.
 */
function wp_set_wpdb_vars()
{
    global $wpdb, $table_prefix;

    if (! empty($wpdb->error)) {
        dead_db();
    }

    $wpdb->field_types = [
        'post_author' => '%d',
        'post_parent' => '%d',
        'menu_order' => '%d',
        'term_id' => '%d',
        'term_group' => '%d',
        'term_taxonomy_id' => '%d',
        'parent' => '%d',
        'count' => '%d',
        'object_id' => '%d',
        'term_order' => '%d',
        'ID' => '%d',
        'comment_ID' => '%d',
        'comment_post_ID' => '%d',
        'comment_parent' => '%d',
        'user_id' => '%d',
        'link_id' => '%d',
        'link_owner' => '%d',
        'link_rating' => '%d',
        'option_id' => '%d',
        'blog_id' => '%d',
        'meta_id' => '%d',
        'post_id' => '%d',
        'user_status' => '%d',
        'umeta_id' => '%d',
        'comment_karma' => '%d',
        'comment_count' => '%d',
        // Multisite:
        'active' => '%d',
        'cat_id' => '%d',
        'deleted' => '%d',
        'lang_id' => '%d',
        'mature' => '%d',
        'public' => '%d',
        'site_id' => '%d',
        'spam' => '%d',
    ];

    $prefix = $wpdb->set_prefix($table_prefix);

    if (is_wp_error($prefix)) {
        wp_load_translations_early();
        wp_die(
            sprintf(
                /* translators: 1: $table_prefix, 2: wp-config.php */
                __('<strong>Error:</strong> %1$s in %2$s can only contain numbers, letters, and underscores.'),
                '<code>$table_prefix</code>',
                '<code>wp-config.php</code>'
            )
        );
    }
}

/**
 * Escapes data for use in a MySQL query.
 *
 * Usually you should prepare queries using wpdb::prepare().
 * Sometimes, spot-escaping is required or useful. One example
 * is preparing an array for use in an IN clause.
 *
 * NOTE: Since 4.8.3, '%' characters will be replaced with a placeholder string,
 * this prevents certain SQLi attacks from taking place. This change in behavior
 * may cause issues for code that expects the return value of esc_sql() to be usable
 * for other purposes.
 *
 * Contains code from WordPress 7.1.4: src/wp-includes/formatting.php.
 *
 * @since WM-0.2.0
 * @since WP-2.8.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param  string|array  $data  Unescaped data.
 * @return string|array Escaped data, in the same type as supplied.
 */
function esc_sql($data)
{
    global $wpdb;

    return $wpdb->_escape($data);
}

/**
 * Retrieves the number of database queries during the WordPress execution.
 *
 * Counts every query on the connection: the ones plugins send through $wpdb,
 * and the ones the house runs through Laravel (App\Core\Database\QueryCounter).
 *
 * @since WM-0.4.0
 * @since WP-2.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @return int Number of database queries.
 */
function get_num_queries()
{
    global $wpdb;

    return $wpdb->num_queries;
}
