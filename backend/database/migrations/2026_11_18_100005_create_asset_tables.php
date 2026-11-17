<?php

use App\Support\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Section 7.8 — assets, custody, transfers, warranty, repairs, servicing,
 * verification, valuation and disposal (Phases 6–7). Created before the A4
 * tables because machines may be owned assets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            Columns::publicId($table);
            $table->string('asset_no', 40)->unique();
            $table->char('qr_token', 32)->unique(); // random; NEVER encodes department / location
            Columns::fk($table, 'item_id', 'items');
            Columns::fk($table, 'category_id', 'item_categories');
            $table->string('brand', 100)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('serial_no', 100)->nullable();
            Columns::fk($table, 'photo_attachment_id', 'attachments', nullable: true);
            $table->string('legacy_code', 40)->nullable();
            $table->string('source_type', 30);
            Columns::fk($table, 'purchase_order_id', 'purchase_orders', nullable: true);
            Columns::fk($table, 'grn_line_id', 'grn_lines', nullable: true);
            $table->string('invoice_ref', 100)->nullable();
            $table->string('book_no', 30)->nullable();
            $table->string('page_no', 30)->nullable();
            $table->date('acquired_on')->nullable();
            $table->string('acquired_on_quality', 10)->default('unknown');
            $table->date('in_service_on')->nullable();
            $table->string('value_reference', 190)->nullable();
            $table->string('condition', 20)->default('good');
            $table->string('operational_status', 20)->default('in_store');
            Columns::fk($table, 'store_id', 'stores', nullable: true);
            Columns::fk($table, 'responsible_department_id', 'departments', nullable: true);
            Columns::fk($table, 'location_id', 'locations', nullable: true);
            Columns::officer($table, 'custodian_officer_id', nullable: true);
            Columns::fk($table, 'expected_location_id', 'locations', nullable: true);
            $table->dateTime('last_verified_at')->nullable();
            Columns::fk($table, 'replaces_asset_id', 'assets', nullable: true);
            $table->text('remarks')->nullable();
            Columns::stdLinked($table);
            $table->unique(['item_id', 'serial_no']);
            $table->index('operational_status');
        });
        Columns::check('assets', 'chk_assets_source', "`source_type` IN ('new_purchase','existing_in_house','import','donation','transfer_in')");
        Columns::check('assets', 'chk_assets_quality', "`acquired_on_quality` IN ('known','estimated','unknown')");
        Columns::check('assets', 'chk_assets_condition', "`condition` IN ('new','good','fair','poor','damaged')");
        Columns::check('assets', 'chk_assets_status', "`operational_status` IN ('in_store','in_use','in_transit','under_repair','on_loan','lost','disposed')");

        Schema::create('asset_custody_history', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'asset_id', 'assets');
            Columns::fk($table, 'department_id', 'departments', nullable: true);
            Columns::fk($table, 'location_id', 'locations', nullable: true);
            Columns::officer($table, 'custodian_officer_id', nullable: true);
            $table->string('operational_status', 20)->nullable();
            $table->string('department_snapshot', 150)->nullable();
            $table->string('custodian_name_snapshot', 190)->nullable();
            $table->string('location_snapshot', 150)->nullable();
            $table->dateTime('from_at');
            $table->string('reason', 100)->nullable();
            $table->string('source_type', 40)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            Columns::appendLinked($table);
            $table->index(['asset_id', 'from_at']);
        });

        // Transfers AND temporary loans. An asset cannot be in two open transfers.
        Schema::create('asset_transfers', function (Blueprint $table) {
            $table->id();
            Columns::publicId($table);
            $table->string('transfer_no', 30)->unique();
            Columns::fk($table, 'asset_id', 'assets', cascadeUpdate: false); // base column of open_key
            $table->string('transfer_type', 10)->default('transfer');
            Columns::fk($table, 'from_department_id', 'departments', nullable: true);
            Columns::fk($table, 'from_location_id', 'locations', nullable: true);
            Columns::officer($table, 'from_custodian_officer_id', nullable: true);
            Columns::fk($table, 'to_department_id', 'departments', nullable: true);
            Columns::fk($table, 'to_location_id', 'locations', nullable: true);
            Columns::officer($table, 'to_custodian_officer_id', nullable: true);
            $table->string('status', 20)->default('submitted');
            Columns::officer($table, 'requested_by_officer_id');
            Columns::officer($table, 'approved_by_officer_id', nullable: true);
            Columns::officer($table, 'dispatched_by_officer_id', nullable: true);
            $table->dateTime('dispatched_at')->nullable();
            $table->string('condition_on_dispatch', 30)->nullable();
            Columns::officer($table, 'received_by_officer_id', nullable: true);
            $table->dateTime('received_at')->nullable();
            $table->string('condition_on_receipt', 30)->nullable();
            $table->boolean('serial_verified')->nullable();
            $table->text('accessories')->nullable();
            $table->string('authority_ref', 190)->nullable();
            $table->string('reason', 255)->nullable();
            $table->date('expected_return_on')->nullable();
            $table->dateTime('returned_at')->nullable();
            Columns::officer($table, 'return_acknowledged_by_officer_id', nullable: true);
            $table->unsignedBigInteger('open_key')->nullable()->storedAs("IF(`status` IN ('submitted','approved','dispatched'), `asset_id`, NULL)");
            Columns::stdLinked($table);
            $table->unique('open_key');
        });
        Columns::check('asset_transfers', 'chk_transfers_type', "`transfer_type` IN ('transfer','loan')");
        Columns::check('asset_transfers', 'chk_transfers_status', "`status` IN ('draft','submitted','approved','dispatched','received','refused','missing','cancelled','returned')");

        Schema::create('warranties', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'asset_id', 'assets');
            $table->string('component', 120)->nullable();
            Columns::fk($table, 'provider_supplier_id', 'suppliers', nullable: true);
            $table->string('coverage_type', 40)->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->text('terms')->nullable();
            Columns::fk($table, 'certificate_attachment_id', 'attachments', nullable: true);
            Columns::fk($table, 'extends_warranty_id', 'warranties', nullable: true);
            $table->string('status', 20)->default('active');
            Columns::stdLinked($table);
        });
        Columns::check('warranties', 'chk_warranties_period', '`ends_on` >= `starts_on`');
        Columns::check('warranties', 'chk_warranties_status', "`status` IN ('active','expired','void')");

        // Repair changes physical custody but NOT the responsible department.
        Schema::create('repair_cases', function (Blueprint $table) {
            $table->id();
            Columns::publicId($table);
            $table->string('case_no', 30)->unique();
            Columns::fk($table, 'asset_id', 'assets', cascadeUpdate: false); // base column of open_key
            $table->text('fault_description');
            Columns::officer($table, 'reported_by_officer_id');
            Columns::officer($table, 'assessed_by_officer_id', nullable: true);
            $table->text('assessment')->nullable();
            Columns::fk($table, 'warranty_id', 'warranties', nullable: true);
            $table->boolean('under_warranty')->default(false);
            Columns::fk($table, 'provider_supplier_id', 'suppliers', nullable: true);
            $table->text('provider_address_snapshot')->nullable();
            $table->string('provider_phone_snapshot', 30)->nullable();
            $table->text('accessories_sent')->nullable();
            $table->decimal('quotation_amount', 18, 2)->nullable();
            $table->string('expenditure_approval_ref', 190)->nullable();
            $table->string('status', 20)->default('reported');
            $table->dateTime('dispatched_at')->nullable();
            $table->date('expected_return_on')->nullable();
            $table->dateTime('returned_at')->nullable();
            Columns::officer($table, 'approved_by_officer_id', nullable: true);
            $table->string('test_result', 20)->nullable();
            Columns::officer($table, 'tested_by_officer_id', nullable: true);
            $table->dateTime('tested_at')->nullable();
            $table->decimal('total_cost', 18, 2)->nullable();
            $table->string('outcome', 100)->nullable();
            $table->string('previous_status', 20)->nullable();
            $table->unsignedBigInteger('open_key')->nullable()->storedAs("IF(`status` <> 'closed', `asset_id`, NULL)");
            Columns::stdLinked($table);
            $table->unique('open_key');
        });
        Columns::check('repair_cases', 'chk_repairs_status', "`status` IN ('reported','assessed','awaiting_approval','approved','dispatched','in_repair','returned','testing','closed','test_failed')");

        Schema::create('repair_updates', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'repair_case_id', 'repair_cases');
            $table->string('update_source', 20)->default('officer');
            $table->text('note')->nullable();
            $table->string('part_description', 190)->nullable();
            $table->decimal('cost', 18, 2)->nullable();
            Columns::officer($table, 'updated_by_officer_id', nullable: true);
            $table->dateTime('event_at');
            Columns::appendLinked($table);
        });

        Schema::create('service_schedules', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'asset_id', 'assets');
            $table->string('service_type', 60);
            $table->integer('interval_days')->nullable();
            $table->bigInteger('usage_threshold')->nullable();
            $table->date('last_service_on')->nullable();
            $table->date('next_due_on')->nullable();
            Columns::officer($table, 'responsible_officer_id', nullable: true);
            $table->boolean('is_active')->default(true);
            Columns::stdLinked($table);
        });

        Schema::create('asset_verification_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_no', 30)->unique();
            $table->string('scope_note', 255)->nullable();
            $table->date('conducted_on');
            Columns::officer($table, 'lead_officer_id');
            $table->string('status', 20)->default('open');
            Columns::stdLinked($table);
        });

        Schema::create('asset_verifications', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'session_id', 'asset_verification_sessions');
            Columns::fk($table, 'asset_id', 'assets');
            Columns::officer($table, 'verified_by_officer_id');
            $table->dateTime('verified_at');
            Columns::fk($table, 'expected_location_id', 'locations', nullable: true);
            Columns::fk($table, 'found_location_id', 'locations', nullable: true);
            Columns::officer($table, 'expected_custodian_officer_id', nullable: true);
            Columns::officer($table, 'found_custodian_officer_id', nullable: true);
            $table->string('condition_found', 20)->nullable();
            Columns::fk($table, 'evidence_attachment_id', 'attachments', nullable: true);
            $table->string('result', 20);
            $table->string('follow_up', 255)->nullable();
            Columns::stdLinked($table);
        });
        Columns::check('asset_verifications', 'chk_verifications_result', "`result` IN ('match','mismatch','missing','found_unrecorded')");

        // A new valuation is a new row; earlier values are never overwritten.
        Schema::create('valuations', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'asset_id', 'assets');
            $table->decimal('previous_value', 18, 2)->nullable();
            $table->decimal('new_value', 18, 2);
            $table->date('effective_on');
            Columns::fk($table, 'assessment_attachment_id', 'attachments', nullable: true);
            $table->string('decision_ref', 190);
            Columns::officer($table, 'assessed_by_officer_id', inCheck: true);
            Columns::officer($table, 'approved_by_officer_id', nullable: true, inCheck: true);
            $table->dateTime('approved_at')->nullable();
            $table->string('status', 20)->default('submitted');
            Columns::stdLinked($table);
        });
        Columns::check('valuations', 'chk_valuations_status', "`status` IN ('submitted','approved','rejected')");
        Columns::check('valuations', 'chk_valuations_independent', '`approved_by_officer_id` IS NULL OR `approved_by_officer_id` <> `assessed_by_officer_id`');

        // Recording damage (assets.condition) and formally retiring (disposal) are separate decisions.
        Schema::create('disposals', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'asset_id', 'assets');
            $table->string('method', 30);
            $table->string('decision_ref', 190);
            $table->string('reason', 255)->nullable();
            $table->string('outcome', 255)->nullable();
            Columns::officer($table, 'proposed_by_officer_id');
            Columns::officer($table, 'approved_by_officer_id', nullable: true);
            $table->dateTime('approved_at')->nullable();
            $table->date('disposed_on')->nullable();
            $table->string('status', 20)->default('proposed');
            Columns::stdLinked($table);
        });
        Columns::check('disposals', 'chk_disposals_method', "`method` IN ('auction','transfer_out','scrap','donation','write_off')");
        Columns::check('disposals', 'chk_disposals_status', "`status` IN ('proposed','approved','rejected','completed')");

        Schema::table('issue_lines', function (Blueprint $table) {
            $table->foreign('asset_id')->references('id')->on('assets')->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('issue_lines', fn (Blueprint $table) => $table->dropForeign(['asset_id']));
        foreach (['disposals', 'valuations', 'asset_verifications', 'asset_verification_sessions', 'service_schedules', 'repair_updates', 'repair_cases', 'warranties', 'asset_transfers', 'asset_custody_history', 'assets'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
