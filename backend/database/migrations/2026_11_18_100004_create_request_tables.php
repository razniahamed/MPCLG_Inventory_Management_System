<?php

use App\Support\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Section 7.6 — requests, approvals, reservations, issues and returns (Phase 4). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            Columns::publicId($table);
            $table->string('request_no', 30)->unique();
            $table->string('request_type', 20)->default('goods');
            Columns::fk($table, 'department_id', 'departments');
            $table->string('department_name_snapshot', 150)->nullable();
            Columns::fk($table, 'store_id', 'stores');
            $table->date('period_from_on')->nullable();
            $table->date('period_to_on')->nullable();
            $table->string('purpose', 255)->nullable();
            $table->date('required_on')->nullable();
            $table->integer('staffing_count')->nullable();
            Columns::officer($table, 'submitted_by_officer_id');
            $table->string('submitted_designation_snapshot', 120)->nullable();
            Columns::officer($table, 'certified_by_officer_id', nullable: true);
            $table->string('status', 20)->default('draft');
            $table->string('receipt_status', 20)->default('pending');
            $table->integer('current_version_no')->default(1);
            $table->dateTime('submitted_at')->nullable();
            $table->unsignedBigInteger('paper_statement_id')->nullable()->index(); // FK added in the A4 migration
            Columns::stdLinked($table);
            $table->index(['department_id', 'status']);
            $table->index(['store_id', 'status']);
        });
        Columns::check('requests', 'chk_requests_type', "`request_type` IN ('goods','a4_paper')");
        Columns::check('requests', 'chk_requests_status', "`status` IN ('draft','submitted','under_review','returned','approved','partly_issued','fully_issued','rejected','cancelled')");
        Columns::check('requests', 'chk_requests_receipt', "`receipt_status` IN ('pending','confirmed','discrepancy')");

        // Editing quantities after approval creates a NEW version_no and restarts approval.
        Schema::create('request_lines', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'request_id', 'requests');
            $table->integer('version_no');
            $table->integer('line_no');
            Columns::fk($table, 'item_id', 'items');
            Columns::fk($table, 'unit_id', 'units');
            $table->decimal('requested_qty', 18, 3);
            $table->decimal('approved_qty', 18, 3)->nullable();
            $table->decimal('issued_qty', 18, 3)->default(0);
            $table->string('remarks', 255)->nullable();
            Columns::stdLinked($table);
            $table->unique(['request_id', 'version_no', 'line_no']);
        });
        Columns::check('request_lines', 'chk_request_lines_qty', '`requested_qty` > 0 AND (`approved_qty` IS NULL OR (`approved_qty` >= 0 AND `approved_qty` <= `requested_qty`)) AND `issued_qty` >= 0 AND `issued_qty` <= COALESCE(`approved_qty`, 0)');

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'request_line_id', 'request_lines');
            Columns::fk($table, 'item_id', 'items');
            Columns::fk($table, 'store_id', 'stores');
            $table->string('batch_no', 60)->nullable();
            $table->decimal('qty', 18, 3);
            $table->string('status', 20)->default('active')->index();
            $table->string('released_reason', 255)->nullable();
            $table->dateTime('released_at')->nullable();
            Columns::stdLinked($table);
        });
        Columns::check('reservations', 'chk_reservations_status', "`status` IN ('active','consumed','released')");

        Schema::create('approval_flows', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            Columns::masterLinked($table);
        });

        Schema::create('approval_flow_steps', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'flow_id', 'approval_flows');
            $table->integer('step_no');
            $table->string('name', 100);
            Columns::fk($table, 'required_permission_id', 'permissions');
            $table->decimal('min_amount', 18, 2)->nullable();
            $table->decimal('max_amount', 18, 2)->nullable();
            $table->boolean('allow_self_approval')->default(false);
            $table->boolean('can_reduce_qty')->default(false);
            $table->boolean('is_required')->default(true);
            Columns::stdLinked($table);
            $table->unique(['flow_id', 'step_no']);
        });

        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->string('approvable_type', 40);
            $table->unsignedBigInteger('approvable_id');
            $table->integer('version_no');
            Columns::fk($table, 'flow_step_id', 'approval_flow_steps');
            $table->string('status', 20)->default('pending');
            Columns::stdLinked($table);
            $table->index(['approvable_type', 'approvable_id']);
        });
        Columns::check('approvals', 'chk_approvals_status', "`status` IN ('pending','approved','reduced','returned','rejected','superseded')");

        Schema::create('approval_decisions', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'approval_id', 'approvals');
            $table->string('decision', 25);
            Columns::officer($table, 'decided_by_officer_id');
            Columns::fk($table, 'decided_by_user_id', 'users');
            $table->string('designation_snapshot', 120);
            $table->string('department_snapshot', 150)->nullable();
            Columns::fk($table, 'acting_under_delegation_id', 'delegations', nullable: true);
            $table->string('authority_ref', 190)->nullable();
            $table->json('approved_qty_snapshot')->nullable();
            $table->string('remarks', 500)->nullable();
            $table->dateTime('decided_at');
            Columns::appendLinked($table);
        });
        Columns::check('approval_decisions', 'chk_decisions_decision', "`decision` IN ('approve','reduce','return_for_clarification','reject')");

        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            Columns::publicId($table);
            $table->string('issue_no', 30)->unique();
            Columns::fk($table, 'request_id', 'requests');
            Columns::fk($table, 'store_id', 'stores');
            $table->date('issue_on');
            Columns::officer($table, 'issued_by_officer_id');
            Columns::officer($table, 'received_by_officer_id', nullable: true);
            $table->string('status', 20)->default('draft');
            $table->dateTime('posted_at')->nullable();
            Columns::fk($table, 'reversal_of_id', 'issues', nullable: true);
            $table->string('idempotency_key', 80)->nullable()->unique();
            $table->string('remarks', 255)->nullable();
            Columns::stdLinked($table);
        });
        Columns::check('issues', 'chk_issues_status', "`status` IN ('draft','posted','reversed')");

        Schema::create('issue_lines', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'issue_id', 'issues');
            Columns::fk($table, 'request_line_id', 'request_lines');
            Columns::fk($table, 'item_id', 'items');
            Columns::fk($table, 'unit_id', 'units');
            $table->decimal('qty', 18, 3);
            $table->string('batch_no', 60)->nullable();
            $table->unsignedBigInteger('asset_id')->nullable()->index(); // FK added in the assets migration
            Columns::stdLinked($table);
        });
        Columns::check('issue_lines', 'chk_issue_lines_qty', '`qty` > 0');

        Schema::create('issue_acknowledgements', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'issue_id', 'issues');
            Columns::officer($table, 'received_by_officer_id');
            $table->dateTime('received_at');
            $table->string('status', 20);
            $table->string('remarks', 255)->nullable();
            Columns::stdLinked($table);
            $table->unique('issue_id');
        });
        Columns::check('issue_acknowledgements', 'chk_ack_status', "`status` IN ('confirmed','discrepancy')");

        // Open until resolved; never silently adjusted.
        Schema::create('discrepancies', function (Blueprint $table) {
            $table->id();
            $table->string('discrepancy_type', 30);
            $table->string('source_type', 40)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('expected_qty', 18, 3)->nullable();
            $table->decimal('actual_qty', 18, 3)->nullable();
            $table->string('description', 255)->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->string('resolution', 255)->nullable();
            Columns::officer($table, 'resolved_by_officer_id', nullable: true);
            $table->dateTime('resolved_at')->nullable();
            Columns::stdLinked($table);
            $table->index(['source_type', 'source_id']);
        });
        Columns::check('discrepancies', 'chk_discrepancies_type', "`discrepancy_type` IN ('receipt_shortfall','damaged_on_receipt','transfer_missing','transfer_refused','count_variance')");
        Columns::check('discrepancies', 'chk_discrepancies_status', "`status` IN ('open','under_review','resolved')");

        Schema::create('department_returns', function (Blueprint $table) {
            $table->id();
            Columns::publicId($table);
            $table->string('return_no', 30)->unique();
            Columns::fk($table, 'department_id', 'departments');
            Columns::fk($table, 'original_issue_id', 'issues');
            Columns::fk($table, 'store_id', 'stores');
            $table->date('return_on')->nullable();
            $table->string('reason', 255)->nullable();
            $table->string('status', 20)->default('submitted');
            Columns::officer($table, 'returned_by_officer_id');
            Columns::officer($table, 'inspected_by_officer_id', nullable: true);
            $table->dateTime('posted_at')->nullable();
            $table->string('idempotency_key', 80)->nullable()->unique();
            Columns::stdLinked($table);
        });
        Columns::check('department_returns', 'chk_dr_status', "`status` IN ('draft','submitted','inspected','posted')");

        // Only ACCEPTED qty goes back to stock.
        Schema::create('department_return_lines', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'department_return_id', 'department_returns');
            Columns::fk($table, 'issue_line_id', 'issue_lines');
            Columns::fk($table, 'item_id', 'items');
            $table->decimal('qty_returned', 18, 3);
            $table->decimal('qty_accepted', 18, 3)->nullable();
            $table->string('condition', 30)->nullable();
            $table->string('remarks', 255)->nullable();
            Columns::stdLinked($table);
        });
    }

    public function down(): void
    {
        foreach (['department_return_lines', 'department_returns', 'discrepancies', 'issue_acknowledgements', 'issue_lines', 'issues', 'approval_decisions', 'approvals', 'approval_flow_steps', 'approval_flows', 'reservations', 'request_lines', 'requests'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
