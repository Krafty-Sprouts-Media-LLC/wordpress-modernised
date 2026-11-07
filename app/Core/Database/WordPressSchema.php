<?php

declare(strict_types=1);

namespace App\Core\Database;

/**
 * WordPress's single-site tables, exactly as WordPress 7.1.4 creates them on
 * MySQL and MariaDB. Used by the migration that creates them; table design
 * changes are made one at a time later, each recorded in docs/divergences.md.
 *
 * Contains code from WordPress 7.1.4: src/wp-admin/includes/schema.php (wp_get_db_schema()).
 */
final class WordPressSchema
{
    /** WordPress's index prefix length for utf8mb4 text columns. */
    private const MAX_INDEX_LENGTH = 191;

    /** The 12 single-site tables, without prefix, in WordPress's order. */
    public const TABLES = [
        'termmeta', 'terms', 'term_taxonomy', 'term_relationships', 'commentmeta', 'comments',
        'links', 'options', 'postmeta', 'posts', 'users', 'usermeta',
    ];

    /**
     * One CREATE TABLE statement per table, keyed by unprefixed table name.
     *
     * @return array<string, string>
     */
    public static function statements(string $prefix, string $charsetCollate): array
    {
        $i = self::MAX_INDEX_LENGTH;

        $tables = [
            'termmeta' => "(
	meta_id bigint(20) unsigned NOT NULL auto_increment,
	term_id bigint(20) unsigned NOT NULL default '0',
	meta_key varchar(255) default NULL,
	meta_value longtext,
	PRIMARY KEY  (meta_id),
	KEY term_id (term_id),
	KEY meta_key (meta_key({$i}))
)",
            'terms' => "(
 term_id bigint(20) unsigned NOT NULL auto_increment,
 name varchar(200) NOT NULL default '',
 slug varchar(200) NOT NULL default '',
 term_group bigint(10) NOT NULL default 0,
 PRIMARY KEY  (term_id),
 KEY slug (slug({$i})),
 KEY name (name({$i}))
)",
            'term_taxonomy' => "(
 term_taxonomy_id bigint(20) unsigned NOT NULL auto_increment,
 term_id bigint(20) unsigned NOT NULL default 0,
 taxonomy varchar(32) NOT NULL default '',
 description longtext NOT NULL,
 parent bigint(20) unsigned NOT NULL default 0,
 count bigint(20) NOT NULL default 0,
 PRIMARY KEY  (term_taxonomy_id),
 UNIQUE KEY term_id_taxonomy (term_id,taxonomy),
 KEY taxonomy (taxonomy)
)",
            'term_relationships' => '(
 object_id bigint(20) unsigned NOT NULL default 0,
 term_taxonomy_id bigint(20) unsigned NOT NULL default 0,
 term_order int(11) NOT NULL default 0,
 PRIMARY KEY  (object_id,term_taxonomy_id),
 KEY term_taxonomy_id (term_taxonomy_id)
)',
            'commentmeta' => "(
	meta_id bigint(20) unsigned NOT NULL auto_increment,
	comment_id bigint(20) unsigned NOT NULL default '0',
	meta_key varchar(255) default NULL,
	meta_value longtext,
	PRIMARY KEY  (meta_id),
	KEY comment_id (comment_id),
	KEY meta_key (meta_key({$i}))
)",
            'comments' => "(
	comment_ID bigint(20) unsigned NOT NULL auto_increment,
	comment_post_ID bigint(20) unsigned NOT NULL default '0',
	comment_author tinytext NOT NULL,
	comment_author_email varchar(100) NOT NULL default '',
	comment_author_url varchar(200) NOT NULL default '',
	comment_author_IP varchar(100) NOT NULL default '',
	comment_date datetime NOT NULL default '0000-00-00 00:00:00',
	comment_date_gmt datetime NOT NULL default '0000-00-00 00:00:00',
	comment_content text NOT NULL,
	comment_karma int(11) NOT NULL default '0',
	comment_approved varchar(20) NOT NULL default '1',
	comment_agent varchar(255) NOT NULL default '',
	comment_type varchar(20) NOT NULL default 'comment',
	comment_parent bigint(20) unsigned NOT NULL default '0',
	user_id bigint(20) unsigned NOT NULL default '0',
	PRIMARY KEY  (comment_ID),
	KEY comment_post_ID (comment_post_ID),
	KEY comment_approved_date_gmt (comment_approved,comment_date_gmt),
	KEY comment_date_gmt (comment_date_gmt),
	KEY comment_parent (comment_parent),
	KEY comment_author_email (comment_author_email(10))
)",
            'links' => "(
	link_id bigint(20) unsigned NOT NULL auto_increment,
	link_url varchar(255) NOT NULL default '',
	link_name varchar(255) NOT NULL default '',
	link_image varchar(255) NOT NULL default '',
	link_target varchar(25) NOT NULL default '',
	link_description varchar(255) NOT NULL default '',
	link_visible varchar(20) NOT NULL default 'Y',
	link_owner bigint(20) unsigned NOT NULL default '1',
	link_rating int(11) NOT NULL default '0',
	link_updated datetime NOT NULL default '0000-00-00 00:00:00',
	link_rel varchar(255) NOT NULL default '',
	link_notes mediumtext NOT NULL,
	link_rss varchar(255) NOT NULL default '',
	PRIMARY KEY  (link_id),
	KEY link_visible (link_visible)
)",
            'options' => "(
	option_id bigint(20) unsigned NOT NULL auto_increment,
	option_name varchar(191) NOT NULL default '',
	option_value longtext NOT NULL,
	autoload varchar(20) NOT NULL default 'yes',
	PRIMARY KEY  (option_id),
	UNIQUE KEY option_name (option_name),
	KEY autoload (autoload)
)",
            'postmeta' => "(
	meta_id bigint(20) unsigned NOT NULL auto_increment,
	post_id bigint(20) unsigned NOT NULL default '0',
	meta_key varchar(255) default NULL,
	meta_value longtext,
	PRIMARY KEY  (meta_id),
	KEY post_id (post_id),
	KEY meta_key (meta_key({$i}))
)",
            'posts' => "(
	ID bigint(20) unsigned NOT NULL auto_increment,
	post_author bigint(20) unsigned NOT NULL default '0',
	post_date datetime NOT NULL default '0000-00-00 00:00:00',
	post_date_gmt datetime NOT NULL default '0000-00-00 00:00:00',
	post_content longtext NOT NULL,
	post_title text NOT NULL,
	post_excerpt text NOT NULL,
	post_status varchar(20) NOT NULL default 'publish',
	comment_status varchar(20) NOT NULL default 'open',
	ping_status varchar(20) NOT NULL default 'open',
	post_password varchar(255) NOT NULL default '',
	post_name varchar(200) NOT NULL default '',
	to_ping text NOT NULL,
	pinged text NOT NULL,
	post_modified datetime NOT NULL default '0000-00-00 00:00:00',
	post_modified_gmt datetime NOT NULL default '0000-00-00 00:00:00',
	post_content_filtered longtext NOT NULL,
	post_parent bigint(20) unsigned NOT NULL default '0',
	guid varchar(255) NOT NULL default '',
	menu_order int(11) NOT NULL default '0',
	post_type varchar(20) NOT NULL default 'post',
	post_mime_type varchar(100) NOT NULL default '',
	comment_count bigint(20) NOT NULL default '0',
	PRIMARY KEY  (ID),
	KEY post_name (post_name({$i})),
	KEY type_status_date (post_type,post_status,post_date,ID),
	KEY post_parent (post_parent),
	KEY post_author (post_author),
	KEY type_status_author (post_type,post_status,post_author)
)",
            'users' => "(
	ID bigint(20) unsigned NOT NULL auto_increment,
	user_login varchar(60) NOT NULL default '',
	user_pass varchar(255) NOT NULL default '',
	user_nicename varchar(50) NOT NULL default '',
	user_email varchar(100) NOT NULL default '',
	user_url varchar(100) NOT NULL default '',
	user_registered datetime NOT NULL default '0000-00-00 00:00:00',
	user_activation_key varchar(255) NOT NULL default '',
	user_status int(11) NOT NULL default '0',
	display_name varchar(250) NOT NULL default '',
	PRIMARY KEY  (ID),
	KEY user_login_key (user_login),
	KEY user_nicename (user_nicename),
	KEY user_email (user_email)
)",
            'usermeta' => "(
	umeta_id bigint(20) unsigned NOT NULL auto_increment,
	user_id bigint(20) unsigned NOT NULL default '0',
	meta_key varchar(255) default NULL,
	meta_value longtext,
	PRIMARY KEY  (umeta_id),
	KEY user_id (user_id),
	KEY meta_key (meta_key({$i}))
)",
        ];

        $statements = [];

        foreach ($tables as $table => $definition) {
            $statements[$table] = "CREATE TABLE {$prefix}{$table} {$definition} {$charsetCollate}";
        }

        return $statements;
    }

    /**
     * The same 12 tables for PostgreSQL, converted from WordPress's MySQL
     * definitions above: one CREATE TABLE plus its CREATE INDEX statements per
     * table, keyed by unprefixed table name. See docs/divergences.md,
     * "WordPress's tables on PostgreSQL".
     *
     * - Column names are lowercase (PostgreSQL folds unquoted names); the doors
     *   give results back with WordPress's casing.
     * - Text compares without case (collation wp_ci), as in MySQL.
     * - Empty dates are NULL instead of 0000-00-00 (owner's decision B).
     * - Unsigned is not enforced; index names are prefixed with the table.
     *
     * @return array<string, list<string>>
     */
    public static function postgresStatements(string $prefix): array
    {
        $statements = [];

        foreach (self::statements('', '') as $table => $mysql) {
            $statements[$table] = self::toPostgres($prefix.$table, $mysql);
        }

        return $statements;
    }

    /**
     * Converts one of the MySQL definitions above (a known, simple format) to PostgreSQL.
     *
     * @return list<string>
     */
    private static function toPostgres(string $table, string $mysql): array
    {
        $columns = [];
        $indexes = [];

        preg_match('/\((.*)\)/s', $mysql, $body);

        foreach (array_map('trim', explode("\n", trim($body[1]))) as $line) {
            $line = rtrim($line, ',');

            if (preg_match('/^PRIMARY KEY\s+\((.+)\)$/', $line, $m)) {
                $columns[] = 'PRIMARY KEY ('.self::columnList($m[1]).')';
            } elseif (preg_match('/^(UNIQUE )?KEY (\w+) \((.+)\)$/', $line, $m)) {
                $unique = $m[1] !== '' ? 'UNIQUE ' : '';
                $indexes[] = "CREATE {$unique}INDEX {$table}_{$m[2]} ON {$table} (".self::columnList($m[3]).')';
            } elseif (preg_match('/^(\w+) (\w+)(?:\((\d+)\))?( unsigned)?(.*)$/', $line, $m)) {
                $columns[] = strtolower($m[1]).' '.self::columnType($m[2], $m[3], $m[5]);
            }
        }

        return ["CREATE TABLE {$table} (\n  ".implode(",\n  ", $columns)."\n)", ...$indexes];
    }

    /**
     * A column's PostgreSQL type, default and nullability.
     */
    private static function columnType(string $type, string $length, string $rest): string
    {
        $type = strtolower($type);

        if (str_contains($rest, 'auto_increment')) {
            return 'bigint GENERATED BY DEFAULT AS IDENTITY';
        }

        if ($type === 'datetime') {
            return 'timestamp(0) NULL DEFAULT NULL';
        }

        $pgType = match ($type) {
            'bigint' => 'bigint',
            'int' => 'integer',
            'tinyint' => 'smallint',
            'varchar' => "varchar({$length}) COLLATE wp_ci",
            'tinytext', 'text', 'mediumtext', 'longtext' => 'text COLLATE wp_ci',
        };

        $notNull = str_contains($rest, 'NOT NULL') ? ' NOT NULL' : '';
        $default = preg_match("/default ('[^']*'|\\d+|NULL)/i", $rest, $d) ? ' DEFAULT '.$d[1] : '';

        // MySQL, in WordPress's relaxed SQL modes, stores '' in a NOT NULL text
        // column an INSERT leaves out; plugins rely on it, so PostgreSQL gets it
        // as a default.
        if ($notNull !== '' && $default === '' && str_ends_with($type, 'text')) {
            $default = " DEFAULT ''";
        }

        return $pgType.$notNull.$default;
    }

    /**
     * Index columns, lowercase, without MySQL's prefix lengths ("meta_key(191)").
     */
    private static function columnList(string $columns): string
    {
        return strtolower(preg_replace('/\(\d+\)/', '', str_replace(' ', '', $columns)));
    }
}
