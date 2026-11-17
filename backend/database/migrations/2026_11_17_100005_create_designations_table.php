<?php

use App\Enums\DesignationCategory;
use App\Support\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 120);
            $table->string('category', 30)->nullable();
            $table->boolean('is_head_post')->default(false);
            Columns::master($table);
        });

        Columns::enumCheck('designations', 'category', DesignationCategory::class, nullable: true);
    }

    public function down(): void
    {
        Schema::dropIfExists('designations');
    }
};
