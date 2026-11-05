<?php

use App\Core\Database\WordPressSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Creates WordPress's 12 single-site tables, exactly as WordPress 7.1.4 does
 * on MySQL and MariaDB, and their PostgreSQL version on PostgreSQL, under the
 * configured prefix (config/wordpress.php).
 */
return new class extends Migration
{
    /** ICU: compare at the base-letter level, ignoring case and accents. */
    private const COLLATION_LOCALE = 'und-u-ks-level1';

    public function up(): void
    {
        $connection = DB::connection();

        if ($connection->getDriverName() === 'pgsql') {
            // Text compared without case or accents, as MySQL's utf8mb4_unicode_520_ci
            // does (PostgreSQL 18+ for LIKE). An older definition is replaced; that
            // fails safely (PostgreSQL refuses) if tables still use it.
            $locale = DB::scalar("SELECT colllocale FROM pg_collation WHERE collname = 'wp_ci'");

            if ($locale !== self::COLLATION_LOCALE) {
                DB::statement('DROP COLLATION IF EXISTS wp_ci');
                DB::statement("CREATE COLLATION wp_ci (provider = icu, locale = '".self::COLLATION_LOCALE."', deterministic = false)");
            }

            foreach (WordPressSchema::postgresStatements($this->prefix()) as $statements) {
                foreach ($statements as $statement) {
                    DB::statement($statement);
                }
            }

            return;
        }

        $charsetCollate = sprintf(
            'DEFAULT CHARACTER SET %s COLLATE %s',
            $connection->getConfig('charset'),
            $connection->getConfig('collation'),
        );

        foreach (WordPressSchema::statements($this->prefix(), $charsetCollate) as $statement) {
            DB::statement($statement);
        }
    }

    public function down(): void
    {
        foreach (array_reverse(WordPressSchema::TABLES) as $table) {
            DB::statement('DROP TABLE IF EXISTS '.$this->prefix().$table);
        }
    }

    private function prefix(): string
    {
        return config('wordpress.table_prefix');
    }
};
