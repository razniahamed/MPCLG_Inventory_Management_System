<?php

use App\Enums\PermissionEffect;
use App\Enums\ScopeType;
use App\Support\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-user ON/OFF overrides. Rows are never deleted; "inherit" revokes them.
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'user_id', 'users');
            Columns::fk($table, 'permission_id', 'permissions');
            $table->string('effect', 10);
            $table->string('scope_type', 20)->default(ScopeType::Global->value);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->dateTime('valid_from');
            $table->dateTime('valid_to')->nullable();
            $table->string('reason', 255);
            Columns::fk($table, 'granted_by', 'users', nullable: true);
            $table->dateTime('granted_at')->nullable();
            Columns::fk($table, 'revoked_by', 'users', nullable: true);
            $table->dateTime('revoked_at')->nullable();
            Columns::std($table);
            $table->index(['user_id', 'permission_id']);
        });

        Columns::enumCheck('user_permissions', 'effect', PermissionEffect::class);
        Columns::enumCheck('user_permissions', 'scope_type', ScopeType::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
    }
};
