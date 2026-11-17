<?php

use App\Support\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Section 7.3 — item catalogue and suppliers (Phase 2). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_categories', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'parent_id', 'item_categories', nullable: true);
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('default_tracking_method', 20)->default('quantity');
            Columns::masterLinked($table);
        });
        Columns::check('item_categories', 'chk_item_categories_tracking', "`default_tracking_method` IN ('quantity','asset')");

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 60);
            $table->string('kind', 20)->default('count');
            Columns::masterLinked($table);
        });
        Columns::check('units', 'chk_units_kind', "`kind` IN ('count','weight','volume','length','pack')");

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('item_code', 40)->unique();
            $table->string('legacy_code', 40)->nullable()->index();
            $table->string('register_book_no', 30)->nullable();
            $table->string('register_page_no', 30)->nullable();
            $table->string('name_en', 190);
            $table->string('name_si', 190)->nullable();
            $table->string('name_ta', 190)->nullable();
            Columns::fk($table, 'category_id', 'item_categories');
            $table->text('specification')->nullable();
            $table->string('brand', 100)->nullable();
            $table->string('tracking_method', 20);
            Columns::fk($table, 'base_unit_id', 'units');
            Columns::fk($table, 'purchase_unit_id', 'units');
            Columns::fk($table, 'issue_unit_id', 'units');
            $table->decimal('reorder_level', 18, 3)->nullable();
            $table->decimal('reorder_qty', 18, 3)->nullable();
            $table->boolean('has_batch')->default(false);
            $table->boolean('has_expiry')->default(false);
            $table->boolean('is_paper')->default(false);
            Columns::masterLinked($table);
        });
        Columns::check('items', 'chk_items_tracking', "`tracking_method` IN ('quantity','asset')");

        // NEVER assume sheets per ream: each conversion is approved.
        Schema::create('item_unit_conversions', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'item_id', 'items');
            Columns::fk($table, 'from_unit_id', 'units');
            Columns::fk($table, 'to_unit_id', 'units');
            $table->decimal('factor', 18, 6);
            Columns::fk($table, 'approved_by', 'users', nullable: true);
            $table->dateTime('approved_at')->nullable();
            Columns::stdLinked($table);
            $table->unique(['item_id', 'from_unit_id', 'to_unit_id']);
        });
        Columns::check('item_unit_conversions', 'chk_conversion_factor', '`factor` > 0');

        // Also service / repair providers. No bank details: payment stays with Finance.
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 190)->index();
            $table->string('supplier_type', 20);
            $table->string('contact_person', 150)->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('tax_ref', 60)->nullable();
            $table->string('legacy_code', 40)->nullable();
            Columns::fk($table, 'duplicate_of_id', 'suppliers', nullable: true);
            Columns::masterLinked($table);
        });
        Columns::check('suppliers', 'chk_suppliers_type', "`supplier_type` IN ('goods','service','both')");

        Schema::create('supplier_categories', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'supplier_id', 'suppliers');
            Columns::fk($table, 'item_category_id', 'item_categories');
            $table->unique(['supplier_id', 'item_category_id']);
        });
    }

    public function down(): void
    {
        foreach (['supplier_categories', 'suppliers', 'item_unit_conversions', 'items', 'units', 'item_categories'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
