<?php

use App\Support\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A department is a working unit, NOT a physical location.
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name_en', 150);
            $table->string('name_si', 150)->nullable();
            $table->string('name_ta', 150)->nullable();
            Columns::fk($table, 'division_id', 'divisions');
            Columns::fk($table, 'parent_department_id', 'departments', nullable: true);
            // FKs to officers are added after the officers table exists.
            $table->unsignedBigInteger('head_officer_id')->nullable()->index();
            $table->unsignedBigInteger('coordinator_officer_id')->nullable()->index();
            Columns::fk($table, 'default_site_id', 'sites', nullable: true);
            $table->date('active_from');
            $table->date('active_to')->nullable();
            Columns::master($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
