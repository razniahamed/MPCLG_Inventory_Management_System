<?php

use App\Support\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_grades', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'public_service_id', 'public_services');
            $table->string('code', 20);
            $table->integer('rank')->nullable();
            Columns::master($table);
            $table->unique(['public_service_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_grades');
    }
};
