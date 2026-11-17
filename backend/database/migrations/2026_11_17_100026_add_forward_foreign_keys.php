<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Circular / forward foreign keys (Section 14.1 step 4), added once both
 * sides exist: officer links on departments/stores, granted_by on
 * role_permissions, and created_by/updated_by → users on every [std] table.
 */
return new class extends Migration
{
    /** Tables created with Columns::std / master / append. */
    private const AUDITED_TABLES = [
        'modules' => ['created_by', 'updated_by'],
        'permissions' => ['created_by', 'updated_by'],
        'roles' => ['created_by', 'updated_by'],
        'designations' => ['created_by', 'updated_by'],
        'public_services' => ['created_by', 'updated_by'],
        'service_grades' => ['created_by', 'updated_by'],
        'divisions' => ['created_by', 'updated_by'],
        'sites' => ['created_by', 'updated_by'],
        'departments' => ['created_by', 'updated_by'],
        'stores' => ['created_by', 'updated_by'],
        'locations' => ['created_by', 'updated_by'],
        'officers' => ['created_by', 'updated_by'],
        'officer_assignments' => ['created_by', 'updated_by'],
        'users' => ['created_by', 'updated_by'],
        'user_roles' => ['created_by', 'updated_by'],
        'user_permissions' => ['created_by', 'updated_by'],
        'attachments' => ['created_by', 'updated_by'],
        'delegations' => ['created_by', 'updated_by'],
        'auth_events' => ['created_by'],
        'audit_logs' => ['created_by'],
        'system_settings' => ['created_by', 'updated_by'],
        'document_sequences' => ['created_by', 'updated_by'],
    ];

    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('head_officer_id')->references('id')->on('officers')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('coordinator_officer_id')->references('id')->on('officers')->restrictOnDelete()->cascadeOnUpdate();
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->foreign('responsible_officer_id')->references('id')->on('officers')->restrictOnDelete()->cascadeOnUpdate();
        });

        Schema::table('role_permissions', function (Blueprint $table) {
            $table->foreign('granted_by')->references('id')->on('users')->restrictOnDelete()->cascadeOnUpdate();
        });

        foreach (self::AUDITED_TABLES as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    $table->foreign($column)->references('id')->on('users')->restrictOnDelete()->cascadeOnUpdate();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::AUDITED_TABLES as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    $table->dropForeign([$column]);
                }
            });
        }

        Schema::table('role_permissions', fn (Blueprint $table) => $table->dropForeign(['granted_by']));
        Schema::table('stores', fn (Blueprint $table) => $table->dropForeign(['responsible_officer_id']));
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['head_officer_id']);
            $table->dropForeign(['coordinator_officer_id']);
        });
    }
};
