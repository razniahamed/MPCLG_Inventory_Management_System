<?php

use App\Support\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name_en', 150);
            $table->string('name_si', 150)->nullable();
            $table->string('name_ta', 150)->nullable();
            Columns::fk($table, 'head_designation_id', 'designations', nullable: true);
            $table->integer('sort_order')->default(0);
            Columns::master($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('divisions');
    }
};
