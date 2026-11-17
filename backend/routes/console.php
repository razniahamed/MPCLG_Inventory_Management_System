<?php

use App\Services\Access\DelegationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('delegations:expire', function (DelegationService $delegations) {
    $count = $delegations->expireDue();
    $this->info("Expired {$count} delegation(s).");
})->purpose('Mark delegations past their end date as expired');

Schedule::command('delegations:expire')->hourly();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
