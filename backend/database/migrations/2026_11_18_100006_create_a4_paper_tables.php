<?php

use App\Support\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Section 7.7 — A4 paper control (Phase 5). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            Columns::fk($table, 'asset_id', 'assets', nullable: true, cascadeUpdate: false);
            $table->string('leased_reference', 100)->nullable();
            $table->string('model', 120)->nullable();
            Columns::fk($table, 'location_id', 'locations', nullable: true);
            Columns::fk($table, 'responsible_department_id', 'departments');
            $table->boolean('is_shared')->default(false);
            Columns::masterLinked($table);
            $table->unique('asset_id');
        });
        Columns::check('machines', 'chk_machines_identity', '`asset_id` IS NOT NULL OR `leased_reference` IS NOT NULL');

        Schema::create('machine_counters', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'machine_id', 'machines');
            $table->string('counter_type', 30);
            Columns::stdLinked($table);
            $table->unique(['machine_id', 'counter_type']);
        });
        Columns::check('machine_counters', 'chk_counters_type', "`counter_type` IN ('printed_sides','physical_sheets','a4_mono','a4_colour','a3_mono','a3_colour')");

        Schema::create('machine_department_assignments', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'machine_id', 'machines');
            Columns::fk($table, 'department_id', 'departments');
            $table->date('from_on');
            $table->date('to_on')->nullable();
            Columns::stdLinked($table);
        });

        Schema::create('meter_observations', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'machine_id', 'machines');
            $table->dateTime('observed_at');
            Columns::officer($table, 'observed_by_officer_id');
            $table->boolean('is_baseline')->default(false);
            $table->string('baseline_reason', 30)->nullable(); // first_reading|reset|replacement
            Columns::fk($table, 'photo_attachment_id', 'attachments', nullable: true);
            Columns::fk($table, 'meter_report_attachment_id', 'attachments', nullable: true);
            Columns::fk($table, 'previous_observation_id', 'meter_observations', nullable: true);
            $table->string('status', 20)->default('submitted');
            $table->json('flag_reasons')->nullable();
            Columns::officer($table, 'verified_by_officer_id', nullable: true);
            $table->dateTime('verified_at')->nullable();
            $table->string('remarks', 255)->nullable();
            Columns::stdLinked($table);
            $table->index(['machine_id', 'observed_at']);
        });
        Columns::check('meter_observations', 'chk_observations_status', "`status` IN ('submitted','accepted','flagged','corrected')");

        // Unknown is NOT zero: if is_unknown = 1, reading_value MUST be NULL.
        Schema::create('meter_observation_readings', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'observation_id', 'meter_observations');
            Columns::fk($table, 'counter_id', 'machine_counters');
            $table->unsignedBigInteger('reading_value')->nullable();
            $table->boolean('is_unknown')->default(false);
            Columns::stdLinked($table);
            $table->unique(['observation_id', 'counter_id']);
        });
        Columns::check('meter_observation_readings', 'chk_readings_unknown', '(`is_unknown` = 1 AND `reading_value` IS NULL) OR (`is_unknown` = 0 AND `reading_value` IS NOT NULL)');

        Schema::create('shared_usage_allocations', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'machine_id', 'machines');
            $table->date('period_from_on');
            $table->date('period_to_on');
            $table->unsignedBigInteger('total_usage');
            Columns::officer($table, 'coordinator_officer_id');
            Columns::officer($table, 'reviewer_officer_id', nullable: true);
            $table->dateTime('reviewed_at')->nullable();
            $table->string('status', 20)->default('submitted');
            Columns::stdLinked($table);
        });
        Columns::check('shared_usage_allocations', 'chk_allocations_status', "`status` IN ('submitted','approved','returned')");

        // Σ allocated_usage must not exceed total_usage: never count the full reading twice.
        Schema::create('shared_usage_allocation_lines', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'allocation_id', 'shared_usage_allocations');
            Columns::fk($table, 'department_id', 'departments');
            $table->unsignedBigInteger('allocated_usage');
            $table->string('basis', 20)->default('declared');
            Columns::fk($table, 'evidence_attachment_id', 'attachments', nullable: true);
            Columns::stdLinked($table);
        });
        Columns::check('shared_usage_allocation_lines', 'chk_allocation_lines_basis', "`basis` IN ('measured','declared')");

        Schema::create('paper_statements', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'department_id', 'departments');
            Columns::fk($table, 'item_id', 'items');
            $table->date('period_from_on');
            $table->date('period_to_on');
            foreach (['opening_balance', 'receipts', 'transfers', 'returns', 'usage_or_loss', 'counted_closing'] as $qty) {
                $table->decimal($qty, 18, 3)->default(0);
            }
            $table->text('explanation')->nullable();
            Columns::officer($table, 'prepared_by_officer_id');
            Columns::officer($table, 'certified_by_officer_id', nullable: true);
            $table->dateTime('certified_at')->nullable();
            $table->string('status', 20)->default('draft');
            $table->boolean('is_baseline')->default(false);
            Columns::stdLinked($table);
        });
        Columns::check('paper_statements', 'chk_statements_status', "`status` IN ('draft','submitted','certified','returned')");

        Schema::create('request_meter_links', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'request_id', 'requests');
            Columns::fk($table, 'observation_id', 'meter_observations');
            $table->unique(['request_id', 'observation_id']);
        });

        // No machine / reset / replacement / breakdown / manual / external printing.
        Schema::create('a4_exceptions', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'request_id', 'requests');
            $table->string('exception_type', 30);
            $table->text('explanation');
            Columns::officer($table, 'raised_by_officer_id');
            Columns::officer($table, 'approved_by_officer_id', nullable: true);
            $table->dateTime('decided_at')->nullable();
            $table->string('status', 20)->default('pending');
            Columns::stdLinked($table);
        });
        Columns::check('a4_exceptions', 'chk_a4_exceptions_type', "`exception_type` IN ('no_machine','reset','replacement','breakdown','manual','external_printing')");
        Columns::check('a4_exceptions', 'chk_a4_exceptions_status', "`status` IN ('pending','approved','rejected')");

        Schema::table('requests', function (Blueprint $table) {
            $table->foreign('paper_statement_id')->references('id')->on('paper_statements')->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('requests', fn (Blueprint $table) => $table->dropForeign(['paper_statement_id']));
        foreach (['a4_exceptions', 'request_meter_links', 'paper_statements', 'shared_usage_allocation_lines', 'shared_usage_allocations', 'meter_observation_readings', 'meter_observations', 'machine_department_assignments', 'machine_counters', 'machines'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
