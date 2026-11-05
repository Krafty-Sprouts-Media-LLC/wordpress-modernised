<?php

use App\Core\Database\WordPressSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Creates WordPress's 12 single-site tables, exactly as WordPress 7.1.4 does,
 * under the configured prefix (config/wordpress.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection();
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
