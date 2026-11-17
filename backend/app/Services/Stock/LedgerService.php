<?php

namespace App\Services\Stock;

use App\Exceptions\ApiException;
use App\Models\Item;
use App\Models\Officer;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Support\Dec;
use Illuminate\Support\Facades\DB;

/**
 * Posts movements to the append-only stock ledger and keeps stock_balances in
 * step inside the same transaction, serialised on the balance row lock
 * (SELECT … FOR UPDATE). Stock never goes negative; reserved ≤ usable.
 */
class LedgerService
{
    /**
     * @param  array{
     *   item_id: int, store_id: int, qty: mixed, movement_type: string, event_on: mixed,
     *   source_type: string, source_id: int, source_line_id?: ?int, document_no?: ?string,
     *   batch_no?: ?string, expiry_on?: mixed, stock_state?: string, remarks?: ?string,
     *   reverses_ledger_id?: ?int, consume_reserved?: mixed
     * }  $m  qty is signed and in the item's BASE unit
     */
    public function post(array $m, Officer $officer): StockLedger
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Ledger postings must run inside a DB transaction.');
        }

        $qty = Dec::n($m['qty']);
        if (Dec::isZero($qty)) {
            throw ApiException::businessRule('A stock movement cannot be zero.');
        }

        $state = $m['stock_state'] ?? 'usable';
        $batch = (string) ($m['batch_no'] ?? '');
        $balance = $this->lockBalance((int) $m['item_id'], (int) $m['store_id'], $batch);

        $column = match ($state) {
            'usable' => 'on_hand_usable',
            'damaged' => 'on_hand_damaged',
            'awaiting_inspection' => 'awaiting_inspection',
        };

        $consume = Dec::n($m['consume_reserved'] ?? 0);
        $newReserved = Dec::sub($balance->reserved, $consume);
        $newOnHand = Dec::add($balance->{$column}, $qty);

        if (Dec::cmp($newOnHand, 0) < 0) {
            $item = Item::find($m['item_id']);
            throw ApiException::businessRule("Not enough stock of {$item?->item_code}: on hand {$balance->{$column}}, movement {$qty}.");
        }

        if ($state === 'usable' && Dec::cmp($newOnHand, $newReserved) < 0) {
            $item = Item::find($m['item_id']);
            throw ApiException::businessRule("Stock of {$item?->item_code} is reserved for approved requests: available ".Dec::sub($balance->on_hand_usable, $balance->reserved).'.');
        }

        $balance->{$column} = $newOnHand;
        $balance->reserved = Dec::cmp($newReserved, 0) < 0 ? Dec::n(0) : $newReserved;
        if (! empty($m['expiry_on'])) {
            $balance->expiry_on = $m['expiry_on'];
        }
        $balance->save();

        return StockLedger::create([
            'item_id' => $m['item_id'],
            'store_id' => $m['store_id'],
            'batch_no' => $batch === '' ? null : $batch,
            'expiry_on' => $m['expiry_on'] ?? null,
            'movement_type' => $m['movement_type'],
            'qty_signed' => $qty,
            'stock_state' => $state,
            'event_on' => $m['event_on'],
            'source_type' => $m['source_type'],
            'source_id' => $m['source_id'],
            'source_line_id' => $m['source_line_id'] ?? null,
            'document_no' => $m['document_no'] ?? null,
            'officer_id' => $officer->id,
            'designation_snapshot' => $officer->designation?->name,
            'reverses_ledger_id' => $m['reverses_ledger_id'] ?? null,
            'remarks' => $m['remarks'] ?? null,
        ]);
    }

    /** Linked reversal of every ledger row a document posted (rule 8). */
    public function reverseSource(string $sourceType, int $sourceId, Officer $officer, string $remarks, ?string $documentNo = null): int
    {
        $rows = StockLedger::query()
            ->where(['source_type' => $sourceType, 'source_id' => $sourceId])
            ->whereNull('reverses_ledger_id')
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            if (StockLedger::where('reverses_ledger_id', $row->id)->exists()) {
                continue;
            }
            $this->post([
                'item_id' => $row->item_id,
                'store_id' => $row->store_id,
                'batch_no' => $row->batch_no,
                'qty' => Dec::neg($row->qty_signed),
                'movement_type' => 'reversal',
                'stock_state' => $row->stock_state,
                'event_on' => now()->toDateString(),
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_line_id' => $row->source_line_id,
                'document_no' => $documentNo ?? $row->document_no,
                'reverses_ledger_id' => $row->id,
                'remarks' => $remarks,
            ], $officer);
        }

        return $rows->count();
    }

    /** Reserve usable stock for an approved request line. */
    public function reserve(int $itemId, int $storeId, mixed $qty, ?string $batch = null): void
    {
        $balance = $this->lockBalance($itemId, $storeId, (string) ($batch ?? ''));
        $reserved = Dec::add($balance->reserved, $qty);
        if (Dec::cmp($reserved, $balance->on_hand_usable) > 0) {
            throw ApiException::businessRule('Not enough available stock to reserve.');
        }
        $balance->reserved = $reserved;
        $balance->save();
    }

    public function release(int $itemId, int $storeId, mixed $qty, ?string $batch = null): void
    {
        $balance = $this->lockBalance($itemId, $storeId, (string) ($batch ?? ''));
        $reserved = Dec::sub($balance->reserved, $qty);
        $balance->reserved = Dec::cmp($reserved, 0) < 0 ? Dec::n(0) : $reserved;
        $balance->save();
    }

    public function available(int $itemId, int $storeId): string
    {
        $row = StockBalance::query()
            ->where(['item_id' => $itemId, 'store_id' => $storeId])
            ->selectRaw('COALESCE(SUM(on_hand_usable),0) AS usable, COALESCE(SUM(reserved),0) AS reserved')
            ->first();

        return Dec::sub($row->usable ?? 0, $row->reserved ?? 0);
    }

    /** Rebuild-check: does the cached balance equal Σ ledger? (nightly job / tests) */
    public function verify(int $itemId, int $storeId): bool
    {
        $ledger = Dec::n(StockLedger::where(['item_id' => $itemId, 'store_id' => $storeId, 'stock_state' => 'usable'])->sum('qty_signed'));
        $cached = Dec::n(StockBalance::where(['item_id' => $itemId, 'store_id' => $storeId])->sum('on_hand_usable'));

        return Dec::cmp($ledger, $cached) === 0;
    }

    private function lockBalance(int $itemId, int $storeId, string $batch): StockBalance
    {
        $key = ['item_id' => $itemId, 'store_id' => $storeId, 'batch_no' => $batch];

        $balance = StockBalance::where($key)->lockForUpdate()->first();
        if ($balance) {
            return $balance;
        }

        StockBalance::query()->insertOrIgnore($key + ['created_at' => now(), 'updated_at' => now()]);

        return StockBalance::where($key)->lockForUpdate()->firstOrFail();
    }
}
