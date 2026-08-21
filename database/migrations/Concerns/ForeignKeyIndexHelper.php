<?php

declare(strict_types=1);

namespace Database\Migrations\Concerns;

use Illuminate\Database\Schema\Blueprint;

/**
 * MySQL binds foreign keys to supporting indexes. Drop FKs before dropping a composite unique they share.
 */
final class ForeignKeyIndexHelper
{
    /**
     * @param  list<string>  $columns
     */
    public static function dropForeignKeys(Blueprint $table, array $columns): void
    {
        foreach ($columns as $column) {
            $table->dropForeign([$column]);
        }
    }
}
