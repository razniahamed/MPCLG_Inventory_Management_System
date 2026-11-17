<?php

use App\Support\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which officers (Storekeepers etc.) work in which store.
        Schema::create('store_officers', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'store_id', 'stores');
            Columns::fk($table, 'officer_id', 'officers');
            $table->string('role', 30)->default('storekeeper');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->unique(['store_id', 'officer_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_officers');
    }
};
