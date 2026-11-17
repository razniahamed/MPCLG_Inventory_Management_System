<?php

use App\Support\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // History, so old transactions keep the correct responsibility.
        Schema::create('officer_assignments', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'officer_id', 'officers');
            Columns::fk($table, 'designation_id', 'designations');
            Columns::fk($table, 'department_id', 'departments');
            Columns::fk($table, 'public_service_id', 'public_services', nullable: true);
            Columns::fk($table, 'service_grade_id', 'service_grades', nullable: true);
            $table->date('from_on');
            $table->date('to_on')->nullable();
            $table->string('authority_ref', 190)->nullable();
            Columns::std($table);
            $table->index(['officer_id', 'from_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('officer_assignments');
    }
};
