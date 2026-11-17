<?php

use App\Enums\UserStatus;
use App\Support\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // NO soft delete, NO hard delete. Accounts are disabled via status.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            Columns::fk($table, 'officer_id', 'officers', nullable: true);
            $table->string('username', 60)->unique();
            $table->string('email', 190)->unique();
            $table->string('password', 255);
            $table->string('status', 20)->default(UserStatus::Pending->value)->index();
            $table->boolean('must_change_password')->default(true);
            $table->dateTime('password_changed_at')->nullable();
            $table->unsignedSmallInteger('failed_login_count')->default(0);
            $table->dateTime('locked_until')->nullable();
            $table->dateTime('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->boolean('mfa_enabled')->default(false);
            $table->text('mfa_secret')->nullable(); // encrypted
            $table->unsignedInteger('permission_version')->default(1);
            $table->dateTime('deactivated_at')->nullable();
            $table->foreignId('deactivated_by')->nullable()->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('deactivation_reason', 255)->nullable();
            Columns::std($table);
            $table->unique('officer_id');
        });

        Columns::enumCheck('users', 'status', UserStatus::class);

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email', 190)->primary();
            $table->string('token');
            $table->dateTime('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
