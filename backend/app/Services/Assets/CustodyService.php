<?php

namespace App\Services\Assets;

use App\Models\Asset;
use App\Models\AssetCustodyHistory;
use App\Models\Department;
use App\Models\Location;
use App\Models\Officer;

/**
 * Every change of WHO is accountable / WHERE it is / WHO holds it is written
 * to the append-only custody history with name snapshots.
 */
class CustodyService
{
    /**
     * @param  array<string, mixed>  $changes  asset columns to change
     */
    public function move(Asset $asset, array $changes, string $reason, ?string $sourceType = null, ?int $sourceId = null): Asset
    {
        foreach (['condition', 'in_service_on'] as $optional) {
            if (array_key_exists($optional, $changes) && $changes[$optional] === null) {
                unset($changes[$optional]);
            }
        }

        $asset->fill($changes)->save();
        $this->record($asset, $reason, $sourceType, $sourceId);

        return $asset;
    }

    public function record(Asset $asset, string $reason, ?string $sourceType = null, ?int $sourceId = null): void
    {
        AssetCustodyHistory::create([
            'asset_id' => $asset->id,
            'department_id' => $asset->responsible_department_id,
            'location_id' => $asset->location_id,
            'custodian_officer_id' => $asset->custodian_officer_id,
            'operational_status' => $asset->operational_status,
            'department_snapshot' => $asset->responsible_department_id ? Department::whereKey($asset->responsible_department_id)->value('name_en') : null,
            'custodian_name_snapshot' => $asset->custodian_officer_id ? Officer::whereKey($asset->custodian_officer_id)->value('full_name') : null,
            'location_snapshot' => $asset->location_id ? Location::whereKey($asset->location_id)->value('name') : null,
            'from_at' => now(),
            'reason' => mb_substr($reason, 0, 100),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);
    }
}
