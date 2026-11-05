<?php

/**
 * dbDelta(): creates or updates tables from CREATE TABLE statements, as plugins do on activation.
 *
 * Contains code from WordPress 7.1.4: src/wp-includes/wp-admin/includes/upgrade.php.
 */

/**
 * Modifies the database based on specified SQL statements.
 *
 * Useful for creating new tables and updating existing tables to a new structure.
 *
 * @since WM-0.2.0
 * @since WP-1.5.0
 * @since WP-6.1.0 Ignores display width for integer data types on MySQL 8.0.17 or later,
 *              to match MySQL behavior. Note: This does not affect MariaDB.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param  string[]|string  $queries  Optional. The query to run. Can be multiple queries
 *                                    in an array, or a string of queries separated by
 *                                    semicolons. Default empty string.
 * @param  bool  $execute  Optional. Whether or not to execute the query right away.
 *                         Default true.
 * @return string[] Strings containing the results of the various update queries.
 */
function dbDelta($queries = '', $execute = true)
{
    global $wpdb;

    if (in_array($queries, ['', 'all', 'blog', 'global', 'ms_global'], true)) {
        $queries = wp_get_db_schema($queries);
    }

    // Separate individual queries into an array.
    if (! is_array($queries)) {
        $queries = explode(';', $queries);
        $queries = array_filter($queries);
    }

    /**
     * Filters the dbDelta SQL queries.
     *
     * @since WM-0.2.0
     * @since WP-3.3.0
     *
     * @param  string[]  $queries  An array of dbDelta SQL queries.
     */
    $queries = apply_filters('dbdelta_queries', $queries);

    $cqueries = []; // Creation queries.
    $iqueries = []; // Insertion queries.
    $for_update = [];

    // Create a tablename index for an array ($cqueries) of recognized query types.
    foreach ($queries as $qry) {
        if (preg_match('|CREATE TABLE ([^ ]*)|', $qry, $matches)) {
            $table_name = trim($matches[1], '`');

            $cqueries[$table_name] = $qry;
            $for_update[$table_name] = 'Created table '.$matches[1];

            continue;
        }

        if (preg_match('|CREATE DATABASE ([^ ]*)|', $qry, $matches)) {
            array_unshift($cqueries, $qry);

            continue;
        }

        if (preg_match('|INSERT INTO ([^ ]*)|', $qry, $matches)) {
            $iqueries[] = $qry;

            continue;
        }

        if (preg_match('|UPDATE ([^ ]*)|', $qry, $matches)) {
            $iqueries[] = $qry;

            continue;
        }
    }

    /**
     * Filters the dbDelta SQL queries for creating tables and/or databases.
     *
     * Queries filterable via this hook contain "CREATE TABLE" or "CREATE DATABASE".
     *
     * @since WM-0.2.0
     * @since WP-3.3.0
     *
     * @param  string[]  $cqueries  An array of dbDelta create SQL queries.
     */
    $cqueries = apply_filters('dbdelta_create_queries', $cqueries);

    /**
     * Filters the dbDelta SQL queries for inserting or updating.
     *
     * Queries filterable via this hook contain "INSERT INTO" or "UPDATE".
     *
     * @since WM-0.2.0
     * @since WP-3.3.0
     *
     * @param  string[]  $iqueries  An array of dbDelta insert or update SQL queries.
     */
    $iqueries = apply_filters('dbdelta_insert_queries', $iqueries);

    $text_fields = ['tinytext', 'text', 'mediumtext', 'longtext'];
    $blob_fields = ['tinyblob', 'blob', 'mediumblob', 'longblob'];
    $int_fields = ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint'];

    $global_tables = $wpdb->tables('global');
    $db_version = $wpdb->db_version();
    $db_server_info = $wpdb->db_server_info();

    foreach ($cqueries as $table => $qry) {
        // Upgrade global tables only for the main site. Don't upgrade at all if conditions are not optimal.
        if (in_array($table, $global_tables, true) && ! wp_should_upgrade_global_tables()) {
            unset($cqueries[$table], $for_update[$table]);

            continue;
        }

        // Fetch the table column structure from the database.
        $suppress = $wpdb->suppress_errors();
        $tablefields = $wpdb->get_results("DESCRIBE {$table};");
        $wpdb->suppress_errors($suppress);

        if (! $tablefields) {
            continue;
        }

        // Clear the field and index arrays.
        $cfields = [];
        $indices = [];
        $indices_without_subparts = [];

        // Get all of the field names in the query from between the parentheses.
        preg_match('|\((.*)\)|ms', $qry, $match2);
        $qryline = trim($match2[1]);

        // Separate field lines into an array.
        $flds = explode("\n", $qryline);

        // For every field line specified in the query.
        foreach ($flds as $fld) {
            $fld = trim($fld, " \t\n\r\0\x0B,"); // Default trim characters, plus ','.

            // Extract the field name.
            preg_match('|^([^ ]*)|', $fld, $fvals);
            $fieldname = trim($fvals[1], '`');
            $fieldname_lowercased = strtolower($fieldname);

            // Verify the found field name.
            $validfield = true;
            switch ($fieldname_lowercased) {
                case '':
                case 'primary':
                case 'index':
                case 'fulltext':
                case 'unique':
                case 'key':
                case 'spatial':
                    $validfield = false;

                    /*
                     * Normalize the index definition.
                     *
                     * This is done so the definition can be compared against the result of a
                     * `SHOW INDEX FROM $table_name` query which returns the current table
                     * index information.
                     */

                    // Extract type, name and columns from the definition.
                    preg_match(
                        '/^
							(?P<index_type>             # 1) Type of the index.
								PRIMARY\s+KEY|(?:UNIQUE|FULLTEXT|SPATIAL)\s+(?:KEY|INDEX)|KEY|INDEX
							)
							\s+                         # Followed by at least one white space character.
							(?:                         # Name of the index. Optional if type is PRIMARY KEY.
								`?                      # Name can be escaped with a backtick.
									(?P<index_name>     # 2) Name of the index.
										(?:[0-9a-zA-Z$_-]|[\xC2-\xDF][\x80-\xBF])+
									)
								`?                      # Name can be escaped with a backtick.
								\s+                     # Followed by at least one white space character.
							)*
							\(                          # Opening bracket for the columns.
								(?P<index_columns>
									.+?                 # 3) Column names, index prefixes, and orders.
								)
							\)                          # Closing bracket for the columns.
						$/imx',
                        $fld,
                        $index_matches
                    );

                    // Uppercase the index type and normalize space characters.
                    $index_type = strtoupper(preg_replace('/\s+/', ' ', trim($index_matches['index_type'])));

                    // 'INDEX' is a synonym for 'KEY', standardize on 'KEY'.
                    $index_type = str_replace('INDEX', 'KEY', $index_type);

                    // Escape the index name with backticks. An index for a primary key has no name.
                    $index_name = ($index_type === 'PRIMARY KEY') ? '' : '`'.strtolower($index_matches['index_name']).'`';

                    // Parse the columns. Multiple columns are separated by a comma.
                    $index_columns = array_map('trim', explode(',', $index_matches['index_columns']));
                    $index_columns_without_subparts = $index_columns;

                    // Normalize columns.
                    foreach ($index_columns as $id => &$index_column) {
                        // Extract column name and number of indexed characters (sub_part).
                        preg_match(
                            '/
								`?                      # Name can be escaped with a backtick.
									(?P<column_name>    # 1) Name of the column.
										(?:[0-9a-zA-Z$_-]|[\xC2-\xDF][\x80-\xBF])+
									)
								`?                      # Name can be escaped with a backtick.
								(?:                     # Optional sub part.
									\s*                 # Optional white space character between name and opening bracket.
									\(                  # Opening bracket for the sub part.
										\s*             # Optional white space character after opening bracket.
										(?P<sub_part>
											\d+         # 2) Number of indexed characters.
										)
										\s*             # Optional white space character before closing bracket.
									\)                  # Closing bracket for the sub part.
								)?
							/x',
                            $index_column,
                            $index_column_matches
                        );

                        // Escape the column name with backticks.
                        $index_column = '`'.$index_column_matches['column_name'].'`';

                        // We don't need to add the subpart to $index_columns_without_subparts
                        $index_columns_without_subparts[$id] = $index_column;

                        // Append the optional sup part with the number of indexed characters.
                        if (isset($index_column_matches['sub_part'])) {
                            $index_column .= '('.$index_column_matches['sub_part'].')';
                        }
                    }

                    // Build the normalized index definition and add it to the list of indices.
                    $indices[] = "{$index_type} {$index_name} (".implode(',', $index_columns).')';
                    $indices_without_subparts[] = "{$index_type} {$index_name} (".implode(',', $index_columns_without_subparts).')';

                    // Destroy no longer needed variables.
                    unset($index_column, $index_column_matches, $index_matches, $index_type, $index_name, $index_columns, $index_columns_without_subparts);

                    break;
            }

            // If it's a valid field, add it to the field array.
            if ($validfield) {
                $cfields[$fieldname_lowercased] = $fld;
            }
        }

        // For every field in the table.
        foreach ($tablefields as $tablefield) {
            $tablefield_field_lowercased = strtolower($tablefield->Field);
            $tablefield_type_lowercased = strtolower($tablefield->Type);

            $tablefield_type_without_parentheses = preg_replace(
                '/'
                .'(.+)'       // Field type, e.g. `int`.
                .'\(\d*\)'    // Display width.
                .'(.*)'       // Optional attributes, e.g. `unsigned`.
                .'/',
                '$1$2',
                $tablefield_type_lowercased
            );

            // Get the type without attributes, e.g. `int`.
            $tablefield_type_base = strtok($tablefield_type_without_parentheses, ' ');

            // If the table field exists in the field array...
            if (array_key_exists($tablefield_field_lowercased, $cfields)) {

                // Get the field type from the query.
                preg_match('|`?'.$tablefield->Field.'`? ([^ ]*( unsigned)?)|i', $cfields[$tablefield_field_lowercased], $matches);
                $fieldtype = $matches[1];
                $fieldtype_lowercased = strtolower($fieldtype);

                $fieldtype_without_parentheses = preg_replace(
                    '/'
                    .'(.+)'       // Field type, e.g. `int`.
                    .'\(\d*\)'    // Display width.
                    .'(.*)'       // Optional attributes, e.g. `unsigned`.
                    .'/',
                    '$1$2',
                    $fieldtype_lowercased
                );

                // Get the type without attributes, e.g. `int`.
                $fieldtype_base = strtok($fieldtype_without_parentheses, ' ');

                // Is actual field type different from the field type in query?
                if ($tablefield->Type !== $fieldtype_lowercased) {
                    $do_change = true;
                    if (in_array($fieldtype_lowercased, $text_fields, true) && in_array($tablefield_type_lowercased, $text_fields, true)) {
                        if (array_search($fieldtype_lowercased, $text_fields, true) < array_search($tablefield_type_lowercased, $text_fields, true)) {
                            $do_change = false;
                        }
                    }

                    if (in_array($fieldtype_lowercased, $blob_fields, true) && in_array($tablefield_type_lowercased, $blob_fields, true)) {
                        if (array_search($fieldtype_lowercased, $blob_fields, true) < array_search($tablefield_type_lowercased, $blob_fields, true)) {
                            $do_change = false;
                        }
                    }

                    if (in_array($fieldtype_base, $int_fields, true) && in_array($tablefield_type_base, $int_fields, true)
                        && $fieldtype_without_parentheses === $tablefield_type_without_parentheses
                    ) {
                        /*
                         * MySQL 8.0.17 or later does not support display width for integer data types,
                         * so if display width is the only difference, it can be safely ignored.
                         * Note: This is specific to MySQL and does not affect MariaDB.
                         */
                        if (version_compare($db_version, '8.0.17', '>=')
                            && ! str_contains($db_server_info, 'MariaDB')
                        ) {
                            $do_change = false;
                        }
                    }

                    if ($do_change) {
                        // Add a query to change the column type.
                        $cqueries[] = "ALTER TABLE {$table} CHANGE COLUMN `{$tablefield->Field}` ".$cfields[$tablefield_field_lowercased];

                        $for_update[$table.'.'.$tablefield->Field] = "Changed type of {$table}.{$tablefield->Field} from {$tablefield->Type} to {$fieldtype}";
                    }
                }

                // Get the default value from the array.
                if (preg_match("| DEFAULT '(.*?)'|i", $cfields[$tablefield_field_lowercased], $matches)) {
                    $default_value = $matches[1];
                    if ($tablefield->Default !== $default_value) {
                        // Add a query to change the column's default value
                        $cqueries[] = "ALTER TABLE {$table} ALTER COLUMN `{$tablefield->Field}` SET DEFAULT '{$default_value}'";

                        $for_update[$table.'.'.$tablefield->Field] = "Changed default value of {$table}.{$tablefield->Field} from {$tablefield->Default} to {$default_value}";
                    }
                }

                // Remove the field from the array (so it's not added).
                unset($cfields[$tablefield_field_lowercased]);
            } else {
                // This field exists in the table, but not in the creation queries?
            }
        }

        // For every remaining field specified for the table.
        foreach ($cfields as $fieldname => $fielddef) {
            // Push a query line into $cqueries that adds the field to that table.
            $cqueries[] = "ALTER TABLE {$table} ADD COLUMN $fielddef";

            $for_update[$table.'.'.$fieldname] = 'Added column '.$table.'.'.$fieldname;
        }

        // Index stuff goes here. Fetch the table index structure from the database.
        $tableindices = $wpdb->get_results("SHOW INDEX FROM {$table};");

        if ($tableindices) {
            // Clear the index array.
            $index_ary = [];

            // For every index in the table.
            foreach ($tableindices as $tableindex) {
                $keyname = strtolower($tableindex->Key_name);

                // Add the index to the index data array.
                $index_ary[$keyname]['columns'][] = [
                    'fieldname' => $tableindex->Column_name,
                    'subpart' => $tableindex->Sub_part,
                ];
                $index_ary[$keyname]['unique'] = (string) $tableindex->Non_unique === '0';
                $index_ary[$keyname]['index_type'] = $tableindex->Index_type;
            }

            // For each actual index in the index array.
            foreach ($index_ary as $index_name => $index_data) {

                // Build a create string to compare to the query.
                $index_string = '';
                if ($index_name === 'primary') {
                    $index_string .= 'PRIMARY ';
                } elseif ($index_data['unique']) {
                    $index_string .= 'UNIQUE ';
                }

                if (strtoupper($index_data['index_type']) === 'FULLTEXT') {
                    $index_string .= 'FULLTEXT ';
                }

                if (strtoupper($index_data['index_type']) === 'SPATIAL') {
                    $index_string .= 'SPATIAL ';
                }

                $index_string .= 'KEY ';
                if ($index_name !== 'primary') {
                    $index_string .= '`'.$index_name.'`';
                }

                $index_columns = '';

                // For each column in the index.
                foreach ($index_data['columns'] as $column_data) {
                    if ($index_columns !== '') {
                        $index_columns .= ',';
                    }

                    // Add the field to the column list string.
                    $index_columns .= '`'.$column_data['fieldname'].'`';
                }

                // Add the column list to the index create string.
                $index_string .= " ($index_columns)";

                // Check if the index definition exists, ignoring subparts.
                $aindex = array_search($index_string, $indices_without_subparts, true);
                if ($aindex !== false) {
                    // If the index already exists (even with different subparts), we don't need to create it.
                    unset($indices_without_subparts[$aindex]);
                    unset($indices[$aindex]);
                }
            }
        }

        // For every remaining index specified for the table.
        foreach ((array) $indices as $index) {
            // Push a query line into $cqueries that adds the index to that table.
            $cqueries[] = "ALTER TABLE {$table} ADD $index";

            $for_update[] = 'Added index '.$table.' '.$index;
        }

        // Remove the original table creation query from processing.
        unset($cqueries[$table], $for_update[$table]);
    }

    $allqueries = array_merge($cqueries, $iqueries);
    if ($execute) {
        foreach ($allqueries as $query) {
            $wpdb->query($query);
        }
    }

    return $for_update;
}

/**
 * Determine if global tables should be upgraded.
 *
 * This function performs a series of checks to ensure the environment allows
 * for the safe upgrading of global WordPress database tables. It is necessary
 * because global tables will commonly grow to millions of rows on large
 * installations, and the ability to control their upgrade routines can be
 * critical to the operation of large networks.
 *
 * In a future iteration, this function may use `wp_is_large_network()` to more-
 * intelligently prevent global table upgrades. Until then, we make sure
 * WordPress is on the main site of the main network, to avoid running queries
 * more than once in multi-site or multi-network environments.
 *
 * @since WM-0.2.0
 * @since WP-4.3.0
 *
 * @return bool Whether to run the upgrade routines on global tables.
 */
function wp_should_upgrade_global_tables()
{

    // Return false early if explicitly not upgrading.
    if (defined('DO_NOT_UPGRADE_GLOBAL_TABLES')) {
        return false;
    }

    // Assume global tables should be upgraded.
    $should_upgrade = true;

    // Set to false if not on main network (does not matter if not multi-network).
    if (! is_main_network()) {
        $should_upgrade = false;
    }

    // Set to false if not on main site of current network (does not matter if not multi-site).
    if (! is_main_site()) {
        $should_upgrade = false;
    }

    /**
     * Filters if upgrade routines should be run on global tables.
     *
     * @since WM-0.2.0
     * @since WP-4.3.0
     *
     * @param  bool  $should_upgrade  Whether to run the upgrade routines on global tables.
     */
    return apply_filters('wp_should_upgrade_global_tables', $should_upgrade);
}
