<?php

use App\Support\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Section 7.4 — purchase references, goods receiving and supplier returns (Phase 3). */
return new class extends Migration
{
    public function up(): void
    {
        // Reference copy of approved POs (the PO process itself is not built here).
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            Columns::publicId($table);
            $table->string('po_number', 40);
            $table->date('po_date');
            Columns::fk($table, 'supplier_id', 'suppliers');
            $table->string('approval_doc_ref', 190)->nullable();
            $table->date('expected_delivery_on')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->integer('version_no')->default(1);
            Columns::fk($table, 'amended_from_id', 'purchase_orders', nullable: true);
            $table->text('remarks')->nullable();
            Columns::stdLinked($table);
            $table->unique(['supplier_id', 'po_number']);
        });
        Columns::check('purchase_orders', 'chk_po_status', "`status` IN ('open','partly_received','fully_received','closed','cancelled')");

        // Outstanding qty is CALCULATED: ordered − Σ accepted in posted GRN lines.
        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'purchase_order_id', 'purchase_orders');
            $table->integer('line_no');
            Columns::fk($table, 'item_id', 'items');
            $table->decimal('ordered_qty', 18, 3);
            Columns::fk($table, 'unit_id', 'units');
            $table->decimal('unit_price', 18, 2)->nullable();
            $table->decimal('discount', 18, 2)->default(0);
            $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('other_charges', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->nullable();
            Columns::stdLinked($table);
            $table->unique(['purchase_order_id', 'line_no']);
        });
        Columns::check('purchase_order_lines', 'chk_pol_qty', '`ordered_qty` > 0');

        Schema::create('grns', function (Blueprint $table) {
            $table->id();
            Columns::publicId($table);
            $table->string('grn_no', 30)->unique();
            $table->string('receipt_authority_type', 20);
            Columns::fk($table, 'purchase_order_id', 'purchase_orders', nullable: true);
            $table->string('receipt_authority_ref', 190)->nullable();
            Columns::fk($table, 'supplier_id', 'suppliers', nullable: true);
            Columns::fk($table, 'store_id', 'stores');
            $table->date('receipt_on');
            $table->date('posting_on')->nullable();
            $table->string('invoice_no', 60)->nullable();
            $table->date('invoice_on')->nullable();
            $table->string('delivery_note_no', 60)->nullable();
            Columns::officer($table, 'received_by_officer_id');
            Columns::officer($table, 'inspected_by_officer_id', nullable: true);
            Columns::officer($table, 'authorised_by_officer_id', nullable: true);
            $table->string('authorised_designation_snapshot', 120)->nullable();
            $table->string('status', 25)->default('draft')->index();
            foreach (['subtotal', 'discount_total', 'tax_total', 'other_charges_total', 'grand_total'] as $money) {
                $table->decimal($money, 18, 2)->default(0);
            }
            Columns::fk($table, 'reversal_of_id', 'grns', nullable: true);
            $table->string('idempotency_key', 80)->nullable()->unique();
            $table->dateTime('posted_at')->nullable();
            $table->string('legacy_book_no', 30)->nullable();
            $table->string('legacy_page_no', 30)->nullable();
            $table->integer('version_no')->default(1);
            $table->text('remarks')->nullable();
            Columns::stdLinked($table);
            $table->unique(['supplier_id', 'invoice_no'], 'uq_grns_supplier_invoice');
            $table->unique(['supplier_id', 'delivery_note_no'], 'uq_grns_supplier_delivery_note');
        });
        Columns::check('grns', 'chk_grns_status', "`status` IN ('draft','awaiting_inspection','awaiting_approval','posted','rejected','reversed')");
        Columns::check('grns', 'chk_grns_authority', "`receipt_authority_type` IN ('po','donation','transfer_in','opening_balance')");

        Schema::create('grn_lines', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'grn_id', 'grns');
            Columns::fk($table, 'po_line_id', 'purchase_order_lines', nullable: true);
            $table->integer('line_no');
            Columns::fk($table, 'item_id', 'items');
            Columns::fk($table, 'unit_id', 'units');
            $table->decimal('delivered_qty', 18, 3);
            $table->decimal('accepted_qty', 18, 3)->default(0);
            $table->decimal('rejected_qty', 18, 3)->default(0);
            $table->decimal('pending_qty', 18, 3)->default(0);
            $table->string('batch_no', 60)->nullable();
            $table->date('expiry_on')->nullable();
            foreach (['unit_price', 'discount', 'tax', 'other_charges', 'line_total'] as $money) {
                $table->decimal($money, 18, 2)->default(0);
            }
            $table->string('condition_note', 255)->nullable();
            $table->string('rejection_reason', 255)->nullable();
            Columns::stdLinked($table);
        });
        Columns::check('grn_lines', 'chk_grn_lines_balance', '`accepted_qty` + `rejected_qty` + `pending_qty` = `delivered_qty` AND `accepted_qty` >= 0 AND `rejected_qty` >= 0 AND `pending_qty` >= 0');

        Schema::create('supplier_returns', function (Blueprint $table) {
            $table->id();
            Columns::publicId($table);
            $table->string('return_no', 30)->unique();
            Columns::fk($table, 'grn_id', 'grns');
            Columns::fk($table, 'supplier_id', 'suppliers');
            Columns::fk($table, 'store_id', 'stores');
            $table->date('return_on')->nullable();
            $table->string('reason', 255);
            $table->string('replacement_or_claim_ref', 190)->nullable();
            $table->string('status', 20)->default('draft');
            Columns::officer($table, 'approved_by_officer_id', nullable: true);
            $table->dateTime('posted_at')->nullable();
            $table->string('idempotency_key', 80)->nullable()->unique();
            Columns::stdLinked($table);
        });
        Columns::check('supplier_returns', 'chk_sr_status', "`status` IN ('draft','approved','posted','closed')");

        Schema::create('supplier_return_lines', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'supplier_return_id', 'supplier_returns');
            Columns::fk($table, 'grn_line_id', 'grn_lines');
            Columns::fk($table, 'item_id', 'items');
            $table->decimal('qty', 18, 3);
            Columns::fk($table, 'unit_id', 'units');
            $table->string('batch_no', 60)->nullable();
            Columns::stdLinked($table);
        });
    }

    public function down(): void
    {
        foreach (['supplier_return_lines', 'supplier_returns', 'grn_lines', 'grns', 'purchase_order_lines', 'purchase_orders'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
