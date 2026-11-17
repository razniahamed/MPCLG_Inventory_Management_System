<?php

use App\Support\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            $table->boolean('is_enabled')->default(true);
            $table->integer('sort_order')->default(0);
            Columns::std($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
