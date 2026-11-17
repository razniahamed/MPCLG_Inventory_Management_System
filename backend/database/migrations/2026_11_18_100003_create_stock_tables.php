<?php

use App\Support\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Section 7.5 — stock ledger, balances, counts and adjustments (Phase 3). */
return new class extends Migration
{
    public function up(): void
    {
        // THE source of truth. Never updated or deleted. created_at = entry date.
        Schema::create('stock_ledger', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'item_id', 'items');
            Columns::fk($table, 'store_id', 'stores');
            $table->string('batch_no', 60)->nullable();
            $table->date('expiry_on')->nullable();
            $table->string('movement_type', 30);
            $table->decimal('qty_signed', 18, 3);
            $table->string('stock_state', 20)->default('usable');
            $table->date('event_on');
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->unsignedBigInteger('source_line_id')->nullable();
            $table->string('document_no', 40)->nullable();
            Columns::officer($table, 'officer_id');
            $table->string('designation_snapshot', 120)->nullable();
            Columns::fk($table, 'reverses_ledger_id', 'stock_ledger', nullable: true);
            $table->string('remarks', 255)->nullable();
            Columns::appendLinked($table);
            $table->index(['item_id', 'store_id', 'event_on']);
            $table->index(['source_type', 'source_id']);
        });
        Columns::check('stock_ledger', 'chk_ledger_movement', "`movement_type` IN ('opening','receipt','issue','return_in','supplier_return','transfer_in','transfer_out','adjustment_plus','adjustment_minus','reversal')");
        Columns::check('stock_ledger', 'chk_ledger_state', "`stock_state` IN ('usable','awaiting_inspection','damaged')");
        Columns::check('stock_ledger', 'chk_ledger_qty', '`qty_signed` <> 0');

        // Cached running balance, updated in the same transaction as the ledger insert.
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'item_id', 'items');
            Columns::fk($table, 'store_id', 'stores');
            $table->string('batch_no', 60)->default('');
            $table->decimal('on_hand_usable', 18, 3)->default(0);
            $table->decimal('on_hand_damaged', 18, 3)->default(0);
            $table->decimal('awaiting_inspection', 18, 3)->default(0);
            $table->decimal('reserved', 18, 3)->default(0);
            $table->date('expiry_on')->nullable();
            Columns::stdLinked($table);
            $table->unique(['item_id', 'store_id', 'batch_no']);
        });
        Columns::check('stock_balances', 'chk_balances_non_negative', '`on_hand_usable` >= 0 AND `on_hand_damaged` >= 0 AND `reserved` >= 0 AND `reserved` <= `on_hand_usable`');

        Schema::create('stock_count_sessions', function (Blueprint $table) {
            $table->id();
            Columns::publicId($table);
            $table->string('session_no', 30)->unique();
            Columns::fk($table, 'store_id', 'stores');
            $table->string('scope_note', 255)->nullable();
            $table->dateTime('cut_off_at');
            Columns::officer($table, 'counted_by_officer_id');
            Columns::officer($table, 'approved_by_officer_id', nullable: true);
            $table->dateTime('approved_at')->nullable();
            $table->string('status', 20)->default('counting');
            Columns::stdLinked($table);
        });
        Columns::check('stock_count_sessions', 'chk_count_status', "`status` IN ('planned','counting','recount','submitted','approved','closed')");

        Schema::create('stock_count_lines', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'session_id', 'stock_count_sessions');
            Columns::fk($table, 'item_id', 'items');
            $table->string('batch_no', 60)->nullable();
            $table->decimal('expected_qty', 18, 3);
            $table->decimal('counted_qty', 18, 3)->nullable();
            $table->decimal('recount_qty', 18, 3)->nullable();
            $table->decimal('variance_qty', 18, 3)->nullable();
            Columns::fk($table, 'evidence_attachment_id', 'attachments', nullable: true);
            $table->string('remarks', 255)->nullable();
            Columns::stdLinked($table);
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            Columns::publicId($table);
            $table->string('adjustment_no', 30)->unique();
            Columns::fk($table, 'count_line_id', 'stock_count_lines', nullable: true);
            Columns::fk($table, 'item_id', 'items');
            Columns::fk($table, 'store_id', 'stores');
            $table->string('batch_no', 60)->nullable();
            $table->decimal('qty_delta', 18, 3);
            $table->string('reason', 255);
            Columns::officer($table, 'recorded_by_officer_id', inCheck: true);
            Columns::officer($table, 'approved_by_officer_id', nullable: true, inCheck: true);
            $table->dateTime('approved_at')->nullable();
            $table->string('status', 20)->default('submitted');
            Columns::fk($table, 'ledger_id', 'stock_ledger', nullable: true);
            $table->string('idempotency_key', 80)->nullable()->unique();
            Columns::stdLinked($table);
        });
        Columns::check('stock_adjustments', 'chk_adjustments_status', "`status` IN ('draft','submitted','approved','rejected','posted')");
        Columns::check('stock_adjustments', 'chk_adjustments_independent', '`approved_by_officer_id` IS NULL OR `approved_by_officer_id` <> `recorded_by_officer_id`');
        Columns::check('stock_adjustments', 'chk_adjustments_qty', '`qty_delta` <> 0');
    }

    public function down(): void
    {
        foreach (['stock_adjustments', 'stock_count_lines', 'stock_count_sessions', 'stock_balances', 'stock_ledger'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
