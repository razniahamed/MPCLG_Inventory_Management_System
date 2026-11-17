<?php

use App\Enums\StoreType;
use App\Support\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            Columns::fk($table, 'site_id', 'sites');
            $table->string('store_type', 30)->nullable();
            $table->unsignedBigInteger('responsible_officer_id')->nullable()->index(); // FK added later
            Columns::master($table);
        });

        Columns::enumCheck('stores', 'store_type', StoreType::class, nullable: true);
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
