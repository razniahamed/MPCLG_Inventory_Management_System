<?php

use App\Support\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'role_id', 'roles');
            Columns::fk($table, 'permission_id', 'permissions');
            $table->unsignedBigInteger('granted_by')->nullable()->index(); // FK added once users exists
            $table->dateTime('granted_at')->nullable();
            $table->unique(['role_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
