<?php

use App\Enums\SiteType;
use App\Support\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('site_type', 30);
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            Columns::master($table);
        });

        Columns::enumCheck('sites', 'site_type', SiteType::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
