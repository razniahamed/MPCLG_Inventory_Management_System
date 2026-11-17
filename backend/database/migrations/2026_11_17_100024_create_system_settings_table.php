<?php

use App\Enums\SettingValueType;
use App\Support\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group_key', 50)->index();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('value_type', 20)->default(SettingValueType::String->value);
            $table->string('description', 255)->nullable();
            Columns::std($table);
        });

        Columns::enumCheck('system_settings', 'value_type', SettingValueType::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
