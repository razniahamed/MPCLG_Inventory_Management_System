<?php

namespace App\Services\Organisation;

use App\Enums\AuditAction;
use App\Models\Store;
use App\Models\StoreOfficer;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class StoreService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Replace the list of officers working in a store.
     *
     * @param  array<int, array{officer_id: int, role?: string, valid_from?: ?string, valid_to?: ?string}>  $officers
     */
    public function syncOfficers(Store $store, array $officers, ?string $reason): Store
    {
        return DB::transaction(function () use ($store, $officers, $reason) {
            $before = $store->storeOfficers()->get(['officer_id', 'role'])->map(fn ($o) => $o->officer_id.':'.$o->role)->all();

            $store->storeOfficers()->delete();

            foreach ($officers as $entry) {
                StoreOfficer::create([
                    'store_id' => $store->id,
                    'officer_id' => $entry['officer_id'],
                    'role' => $entry['role'] ?? 'storekeeper',
                    'valid_from' => $entry['valid_from'] ?? null,
                    'valid_to' => $entry['valid_to'] ?? null,
                ]);
            }

            $after = $store->storeOfficers()->get(['officer_id', 'role'])->map(fn ($o) => $o->officer_id.':'.$o->role)->all();
            $this->audit->log(AuditAction::Updated, $store, ['officers' => $before], ['officers' => $after], $reason);

            return $store->load('storeOfficers.officer');
        });
    }
}
