<?php

use App\Support\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'module_id', 'modules');
            $table->string('code', 100)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('resource', 60);
            $table->string('action', 40);
            $table->boolean('is_sensitive')->default(false);
            $table->boolean('supports_scope')->default(false);
            $table->boolean('is_enabled')->default(true);
            Columns::std($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
