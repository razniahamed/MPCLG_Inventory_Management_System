<?php

use App\Enums\LocationType;
use App\Support\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'site_id', 'sites');
            Columns::fk($table, 'store_id', 'stores', nullable: true);
            Columns::fk($table, 'parent_location_id', 'locations', nullable: true);
            $table->string('location_type', 20);
            $table->string('code', 40);
            $table->string('name', 120);
            Columns::master($table);
            $table->unique(['site_id', 'code']);
        });

        Columns::enumCheck('locations', 'location_type', LocationType::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
