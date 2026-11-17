<?php

use App\Enums\ScopeType;
use App\Support\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'user_id', 'users');
            Columns::fk($table, 'role_id', 'roles');
            $table->string('scope_type', 20)->default(ScopeType::Global->value);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->dateTime('valid_from');
            $table->dateTime('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            Columns::fk($table, 'granted_by', 'users', nullable: true);
            $table->dateTime('granted_at')->nullable();
            Columns::fk($table, 'revoked_by', 'users', nullable: true);
            $table->dateTime('revoked_at')->nullable();
            $table->string('revoke_reason', 255)->nullable();
            Columns::std($table);
            $table->unique(['user_id', 'role_id', 'scope_type', 'scope_id', 'valid_from'], 'uq_user_roles_assignment');
            $table->index(['user_id', 'is_active']);
        });

        Columns::enumCheck('user_roles', 'scope_type', ScopeType::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
