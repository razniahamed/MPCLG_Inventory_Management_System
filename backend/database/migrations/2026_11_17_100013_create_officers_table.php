<?php

use App\Enums\EmploymentStatus;
use App\Support\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The PERSON. A user account is separate and optional.
        // Do NOT store NIC or personal data unless Finance/HR formally requires it.
        Schema::create('officers', function (Blueprint $table) {
            $table->id();
            $table->string('officer_no', 30)->unique();
            $table->string('full_name', 190);
            $table->string('name_with_initials', 150)->nullable();
            Columns::fk($table, 'designation_id', 'designations');
            Columns::fk($table, 'public_service_id', 'public_services', nullable: true);
            Columns::fk($table, 'service_grade_id', 'service_grades', nullable: true);
            Columns::fk($table, 'department_id', 'departments');
            $table->string('work_phone', 30)->nullable();
            $table->string('work_email', 190)->nullable();
            $table->string('employment_status', 20)->default('active')->index();
            $table->date('joined_on')->nullable();
            $table->date('left_on')->nullable();
            Columns::master($table);
        });

        Columns::enumCheck('officers', 'employment_status', EmploymentStatus::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('officers');
    }
};
