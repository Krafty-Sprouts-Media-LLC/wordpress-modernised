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
    public function up(): void
    {
        $connection = DB::connection();

        if ($connection->getDriverName() === 'pgsql') {
            // Text compared without case, as MySQL's _ci collations do (PostgreSQL 18+ for LIKE).
            DB::statement("CREATE COLLATION IF NOT EXISTS wp_ci (provider = icu, locale = 'und-u-ks-level2', deterministic = false)");

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
