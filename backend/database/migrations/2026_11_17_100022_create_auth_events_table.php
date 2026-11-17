<?php

use App\Enums\AuthEventType;
use App\Support\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // [append] — no updates, no deletes.
        Schema::create('auth_events', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'user_id', 'users', nullable: true);
            $table->string('username_tried', 60)->nullable();
            $table->string('event', 30);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            Columns::append($table);
            $table->index(['user_id', 'created_at']);
        });

        Columns::enumCheck('auth_events', 'event', AuthEventType::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_events');
    }
};
