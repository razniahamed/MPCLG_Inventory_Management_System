<?php

namespace App\Services\Assets;

use App\Enums\AuditAction;
use App\Exceptions\ApiException;
use App\Models\Asset;
use App\Models\GrnLine;
use App\Models\Item;
use App\Services\Access\Actor;
use App\Services\Access\Scope;
use App\Services\Audit\AuditLogger;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;

/**
 * The asset register. The asset number and QR token are permanent for the
 * asset's life (rule 14); a replacement machine is a NEW asset linked to the old.
 */
class AssetService
{
    private const EDITABLE = ['brand', 'model', 'serial_no', 'legacy_code', 'invoice_ref', 'book_no', 'page_no', 'acquired_on', 'acquired_on_quality', 'value_reference', 'condition', 'remarks'];

    public function __construct(
        private readonly Actor $actor,
        private readonly CustodyService $custody,
        private readonly AuditLogger $audit,
    ) {}

    public function create(array $data): Asset
    {
        $item = Item::findOrFail($data['item_id']);
        if (! $item->isAsset()) {
            throw ApiException::businessRule("{$item->item_code} is tracked by quantity, not as an individual asset.");
        }

        if (! empty($data['store_id'])) {
            $this->actor->authorize('asset.create', Scope::store((int) $data['store_id']));
        }

        if (($data['acquired_on'] ?? null) === null) {
            $data['acquired_on_quality'] = 'unknown'; // never invent historical dates (principle 6)
        }

        if (! empty($data['grn_line_id'])) {
            $grnLine = GrnLine::with('grn')->findOrFail($data['grn_line_id']);
            if ($grnLine->item_id !== $item->id || $grnLine->grn->status !== 'posted') {
                throw ApiException::businessRule('The GRN line must be a posted receipt of the same item.');
            }
            $registered = Asset::where('grn_line_id', $grnLine->id)->count();
            if ($registered >= (int) $grnLine->accepted_qty) {
                throw ApiException::businessRule("All {$grnLine->accepted_qty} accepted units on that GRN line are already registered.");
            }
            $data['purchase_order_id'] ??= $grnLine->grn->purchase_order_id;
            $data['store_id'] ??= $grnLine->grn->store_id;
            $data['source_type'] = 'new_purchase';
            $data['acquired_on'] ??= $grnLine->grn->receipt_on->toDateString();
            $data['acquired_on_quality'] = 'known';
        }

        $inUse = ! empty($data['responsible_department_id']);

        return DB::transaction(function () use ($data, $item, $inUse) {
            $asset = Asset::create([
                'asset_no' => DocumentNumber::next('AST', pad: 6),
                'qr_token' => bin2hex(random_bytes(16)),
                'item_id' => $item->id,
                'category_id' => $item->category_id,
                'brand' => $data['brand'] ?? $item->brand,
                'model' => $data['model'] ?? null,
                'serial_no' => $data['serial_no'] ?? null,
                'legacy_code' => $data['legacy_code'] ?? null,
                'source_type' => $data['source_type'] ?? 'existing_in_house',
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'grn_line_id' => $data['grn_line_id'] ?? null,
                'invoice_ref' => $data['invoice_ref'] ?? null,
                'book_no' => $data['book_no'] ?? null,
                'page_no' => $data['page_no'] ?? null,
                'acquired_on' => $data['acquired_on'] ?? null,
                'acquired_on_quality' => $data['acquired_on_quality'] ?? 'unknown',
                'value_reference' => $data['value_reference'] ?? null,
                'condition' => $data['condition'] ?? 'good',
                'operational_status' => $inUse ? 'in_use' : 'in_store',
                'store_id' => $inUse ? null : ($data['store_id'] ?? null),
                'responsible_department_id' => $data['responsible_department_id'] ?? null,
                'location_id' => $data['location_id'] ?? null,
                'custodian_officer_id' => $data['custodian_officer_id'] ?? null,
                'replaces_asset_id' => $data['replaces_asset_id'] ?? null,
                'remarks' => $data['remarks'] ?? null,
            ]);

            $this->custody->record($asset, 'Registered');
            $this->audit->log(AuditAction::Created, $asset, null, $asset->only(['asset_no', 'item_id', 'serial_no', 'source_type', 'operational_status']));

            return $asset;
        });
    }

    /** Descriptive fields only — custody changes go through transfers / issues / repairs. */
    public function update(Asset $asset, array $data): Asset
    {
        if ($asset->operational_status === 'disposed') {
            throw ApiException::businessRule('Disposed assets cannot be changed.');
        }

        return DB::transaction(function () use ($asset, $data) {
            $original = $asset->getAttributes();
            $asset->fill(array_intersect_key($data, array_flip(self::EDITABLE)))->save();
            $this->audit->logChanges($asset, $original, $data['reason'] ?? null);

            return $asset;
        });
    }

    /** Payload for the QR label: a URL carrying only the random token. */
    public function qr(Asset $asset): array
    {
        $base = rtrim((string) config('mpclg.frontend_url'), '/');

        return [
            'asset_no' => $asset->asset_no,
            'qr_token' => $asset->qr_token,
            'url' => "{$base}/scan/{$asset->qr_token}",
        ];
    }

    /** What a scan shows: permitted basics only (Section 12). */
    public function scan(string $token): array
    {
        $asset = Asset::where('qr_token', $token)->with(['item:id,item_code,name_en', 'responsibleDepartment:id,name_en', 'location:id,name', 'store:id,name'])->firstOrFail();

        return [
            'id' => $asset->public_id,
            'asset_no' => $asset->asset_no,
            'description' => $asset->item?->name_en,
            'brand' => $asset->brand,
            'model' => $asset->model,
            'responsible_department' => $asset->responsibleDepartment?->name_en,
            'recorded_location' => $asset->location?->name ?? $asset->store?->name,
            'operational_status' => $asset->operational_status,
            'condition' => $asset->condition,
            'last_update' => $asset->updated_at?->toIso8601String(),
        ];
    }
}
