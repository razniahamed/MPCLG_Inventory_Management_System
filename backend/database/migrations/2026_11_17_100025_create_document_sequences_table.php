<?php

use App\Support\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Race-safe document numbers (row locked with SELECT ... FOR UPDATE).
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('doc_type', 20);
            $table->smallInteger('year');
            $table->string('scope_key', 30)->default('ALL');
            $table->string('prefix', 20)->nullable();
            $table->unsignedInteger('last_number')->default(0);
            Columns::std($table);
            $table->unique(['doc_type', 'year', 'scope_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
