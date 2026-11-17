<?php

namespace App\Services\Stock;

use App\Enums\AuditAction;
use App\Exceptions\ApiException;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\StockCountLine;
use App\Models\StockCountSession;
use App\Services\Access\Actor;
use App\Services\Access\Scope;
use App\Services\Approvals\ApprovalService;
use App\Services\Audit\AuditLogger;
use App\Support\Dec;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;

/**
 * Physical counts. A variance becomes an adjustment only after a recount,
 * evidence (remarks) and independent approval (rule 11).
 */
class CountService
{
    public function __construct(
        private readonly Actor $actor,
        private readonly AuditLogger $audit,
        private readonly ApprovalService $approvals,
    ) {}

    public function create(array $data): StockCountSession
    {
        $this->actor->authorize('count.create', Scope::store((int) $data['store_id']));
        $officer = $this->actor->officer();

        return DB::transaction(function () use ($data, $officer) {
            $session = StockCountSession::create([
                'session_no' => DocumentNumber::next('CNT'),
                'store_id' => $data['store_id'],
                'scope_note' => $data['scope_note'] ?? null,
                'cut_off_at' => now(),
                'counted_by_officer_id' => $officer->id,
                'status' => 'counting',
            ]);

            $balances = StockBalance::query()
                ->where('store_id', $data['store_id'])
                ->when(! empty($data['item_ids']), fn ($q) => $q->whereIn('item_id', $data['item_ids']))
                ->get();

            foreach ($balances as $b) {
                StockCountLine::create([
                    'session_id' => $session->id,
                    'item_id' => $b->item_id,
                    'batch_no' => $b->batch_no === '' ? null : $b->batch_no,
                    'expected_qty' => $b->on_hand_usable,
                ]);
            }

            $this->audit->log(AuditAction::Created, $session, null, ['session_no' => $session->session_no, 'lines' => $balances->count()]);

            return $session->load('lines.item');
        });
    }

    /** @param  array<int, array{id: int, counted_qty?: mixed, recount_qty?: mixed, remarks?: ?string}>  $lines */
    public function record(StockCountSession $session, array $lines): StockCountSession
    {
        $this->assertStatus($session, ['counting', 'recount']);
        $this->actor->authorize('count.record', Scope::store($session->store_id));

        return DB::transaction(function () use ($session, $lines) {
            foreach ($lines as $input) {
                $line = StockCountLine::where('session_id', $session->id)->findOrFail($input['id']);
                $line->fill(array_intersect_key($input, array_flip(['counted_qty', 'recount_qty', 'remarks'])));
                $final = $line->recount_qty ?? $line->counted_qty;
                $line->variance_qty = $final === null ? null : Dec::sub($final, $line->expected_qty);
                $line->save();
            }

            $needsRecount = $session->lines()->get()->contains(fn ($l) => $l->counted_qty !== null && Dec::cmp($l->counted_qty, $l->expected_qty) !== 0 && $l->recount_qty === null);
            $session->update(['status' => $needsRecount ? 'recount' : 'counting']);

            return $session->load('lines.item');
        });
    }

    public function submit(StockCountSession $session): StockCountSession
    {
        $this->assertStatus($session, ['counting', 'recount']);
        $this->actor->authorize('count.record', Scope::store($session->store_id));

        foreach ($session->lines as $line) {
            if ($line->counted_qty === null) {
                throw ApiException::businessRule("Every line must be counted ({$line->item?->item_code} is missing).");
            }
            if (Dec::cmp($line->counted_qty, $line->expected_qty) !== 0 && ($line->recount_qty === null || blank($line->remarks))) {
                throw ApiException::businessRule("Variance on {$line->item?->item_code}: a recount and an explanation are required before submitting.");
            }
        }

        $session->update(['status' => 'submitted']);
        $this->audit->log(AuditAction::StatusChanged, $session, null, ['status' => 'submitted']);

        return $session;
    }

    /** Independent approval: variances become adjustments awaiting their own approval. */
    public function approve(StockCountSession $session, ?string $remarks): StockCountSession
    {
        $this->assertStatus($session, ['submitted']);
        $this->actor->authorize('count.approve', Scope::store($session->store_id));
        $this->actor->assertNotSelf($session->counted_by_officer_id, 'a count you recorded');
        $officer = $this->actor->officer();

        return DB::transaction(function () use ($session, $remarks, $officer) {
            foreach ($session->lines as $line) {
                if ($line->variance_qty === null || Dec::isZero($line->variance_qty)) {
                    continue;
                }
                $adjustment = StockAdjustment::create([
                    'adjustment_no' => DocumentNumber::next('ADJ'),
                    'count_line_id' => $line->id,
                    'item_id' => $line->item_id,
                    'store_id' => $session->store_id,
                    'batch_no' => $line->batch_no,
                    'qty_delta' => $line->variance_qty,
                    'reason' => "Count {$session->session_no}: ".($line->remarks ?? 'variance'),
                    'recorded_by_officer_id' => $session->counted_by_officer_id,
                    'status' => 'submitted',
                ]);
                $this->approvals->start($adjustment, 'STOCK_ADJUSTMENT', 1);
            }

            $session->update(['status' => 'approved', 'approved_by_officer_id' => $officer->id, 'approved_at' => now()]);
            $this->audit->log(AuditAction::Approved, $session, null, ['status' => 'approved'], $remarks);

            return $session;
        });
    }

    private function assertStatus(StockCountSession $session, array $allowed): void
    {
        if (! in_array($session->status, $allowed, true)) {
            throw ApiException::businessRule("Count {$session->session_no} is {$session->status}.");
        }
    }
}
