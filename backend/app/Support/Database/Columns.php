<?php

namespace App\Support\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/**
 * Column bundles from Section 7.0.
 *
 * [std]    id + created_at/updated_at + created_by/updated_by
 * [master] [std] + is_active + deleted_at (soft delete)
 * [append] id + created_at + created_by (no updated_*)
 *
 * created_by / updated_by foreign keys to users are added in a later
 * migration (users is created after most master tables).
 */
class Columns
{
    /** Tables that carry created_by/updated_by, so the FK migration can find them. */
    public const AUDIT_USER_COLUMNS = ['created_by', 'updated_by'];

    public static function std(Blueprint $table): void
    {
        $table->dateTime('created_at')->nullable();
        $table->dateTime('updated_at')->nullable();
        $table->unsignedBigInteger('created_by')->nullable()->index();
        $table->unsignedBigInteger('updated_by')->nullable()->index();
    }

    public static function master(Blueprint $table): void
    {
        $table->boolean('is_active')->default(true)->index();
        self::std($table);
        $table->dateTime('deleted_at')->nullable();
    }

    public static function append(Blueprint $table): void
    {
        $table->dateTime('created_at')->nullable();
        $table->unsignedBigInteger('created_by')->nullable()->index();
    }

    /**
     * FK with the project default: ON DELETE RESTRICT, ON UPDATE CASCADE.
     */
    public static function fk(Blueprint $table, string $column, string $on, bool $nullable = false): void
    {
        $definition = $table->foreignId($column);
        if ($nullable) {
            $definition->nullable();
        }
        $definition->constrained($on)->restrictOnDelete()->cascadeOnUpdate();
    }

    /**
     * CHECK constraint (MySQL 8 enforces them). Skipped on other drivers.
     */
    public static function check(string $table, string $name, string $expression): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` CHECK ({$expression})");
    }

    /**
     * CHECK that a VARCHAR column only holds the values of a backed enum.
     *
     * @param  class-string<\BackedEnum>  $enum
     */
    public static function enumCheck(string $table, string $column, string $enum, bool $nullable = false): void
    {
        $list = $enum::sqlList();
        $expr = $nullable ? "`{$column}` IS NULL OR `{$column}` IN ({$list})" : "`{$column}` IN ({$list})";
        self::check($table, "chk_{$table}_{$column}", $expr);
    }
}
