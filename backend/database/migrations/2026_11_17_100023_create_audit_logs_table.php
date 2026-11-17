<?php

use App\Enums\AuditAction;
use App\Support\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // [append] — no updates, no deletes. created_at = entry date (may differ from event_at).
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->dateTime('event_at');
            Columns::fk($table, 'user_id', 'users', nullable: true);
            Columns::fk($table, 'officer_id', 'officers', nullable: true);
            $table->string('action', 40);
            $table->string('auditable_type', 60);
            $table->unsignedBigInteger('auditable_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('reason', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->char('request_id', 26)->nullable();
            $table->char('prev_hash', 64)->nullable();
            $table->char('row_hash', 64)->nullable();
            Columns::append($table);
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'event_at']);
        });

        Columns::enumCheck('audit_logs', 'action', AuditAction::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
