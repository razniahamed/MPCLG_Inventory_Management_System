<?php

use App\Enums\DelegationStatus;
use App\Enums\ScopeType;
use App\Support\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delegations', function (Blueprint $table) {
            $table->id();
            Columns::fk($table, 'delegator_user_id', 'users');
            Columns::fk($table, 'delegate_user_id', 'users');
            // NULL = all of the delegator's approval (sensitive) permissions
            Columns::fk($table, 'permission_id', 'permissions', nullable: true);
            $table->string('scope_type', 20)->default(ScopeType::Global->value);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->dateTime('valid_from');
            $table->dateTime('valid_to');
            $table->string('written_reference', 190);
            Columns::fk($table, 'attachment_id', 'attachments', nullable: true);
            $table->string('status', 20)->default(DelegationStatus::Active->value)->index();
            Columns::fk($table, 'revoked_by', 'users', nullable: true);
            $table->dateTime('revoked_at')->nullable();
            Columns::std($table);
        });

        Columns::enumCheck('delegations', 'status', DelegationStatus::class);
        Columns::enumCheck('delegations', 'scope_type', ScopeType::class);
        Columns::check('delegations', 'chk_delegations_period', '`valid_to` > `valid_from`');
    }

    public function down(): void
    {
        Schema::dropIfExists('delegations');
    }
};
