<?php

namespace App\Services\Paper;

use App\Enums\AuditAction;
use App\Exceptions\ApiException;
use App\Models\A4Exception;
use App\Models\GoodsRequest;
use App\Models\Item;
use App\Models\Machine;
use App\Models\PaperStatement;
use App\Models\SharedUsageAllocation;
use App\Models\SharedUsageAllocationLine;
use App\Services\Access\Actor;
use App\Services\Access\Scope;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/** Paper statements, shared-machine usage allocations and A4 exceptions (Phase 5). */
class PaperService
{
    public function __construct(
        private readonly Actor $actor,
        private readonly MeterService $meters,
        private readonly AuditLogger $audit,
    ) {}

    // ---- Paper statements ----------------------------------------------------

    public function createStatement(array $data): PaperStatement
    {
        $this->actor->authorize('paper_statement.manage', Scope::department((int) $data['department_id']));
        $item = Item::findOrFail($data['item_id']);
        if (! $item->is_paper) {
            throw ApiException::businessRule("{$item->item_code} is not a paper item.");
        }

        return DB::transaction(function () use ($data) {
            $statement = PaperStatement::create([
                'department_id' => $data['department_id'],
                'item_id' => $data['item_id'],
                'period_from_on' => $data['period_from_on'],
                'period_to_on' => $data['period_to_on'],
                'opening_balance' => $data['opening_balance'] ?? 0,
                'receipts' => $data['receipts'] ?? 0,
                'transfers' => $data['transfers'] ?? 0,
                'returns' => $data['returns'] ?? 0,
                'usage_or_loss' => $data['usage_or_loss'] ?? 0,
                'counted_closing' => $data['counted_closing'] ?? 0,
                'explanation' => $data['explanation'] ?? null,
                'prepared_by_officer_id' => $this->actor->officerId(),
                'is_baseline' => (bool) ($data['is_baseline'] ?? false),
                'status' => 'submitted',
            ]);

            if (bccomp($statement->unexplainedDifference(), '0', 3) !== 0 && blank($statement->explanation)) {
                throw ApiException::businessRule('Counted closing balance does not match opening + receipts + transfers − returns − usage. Explain the difference.');
            }

            $this->audit->log(AuditAction::Created, $statement, null, $statement->only(['department_id', 'period_from_on', 'period_to_on', 'counted_closing']));

            return $statement;
        });
    }

    public function certifyStatement(PaperStatement $statement, ?string $remarks): PaperStatement
    {
        $this->actor->authorize('request.certify', Scope::department($statement->department_id));
        $this->actor->assertNotSelf($statement->prepared_by_officer_id, 'a statement you prepared');

        $statement->update(['status' => 'certified', 'certified_by_officer_id' => $this->actor->officerId(), 'certified_at' => now()]);
        $this->audit->log(AuditAction::Approved, $statement, null, ['status' => 'certified'], $remarks);

        return $statement;
    }

    // ---- Shared machine usage -----------------------------------------------

    /**
     * Split a shared machine's measured usage between departments. The total
     * allocated can never exceed the measured total (no double counting).
     *
     * @param  array<int, array{department_id: int, allocated_usage: int, basis?: string}>  $lines
     */
    public function allocate(array $data): SharedUsageAllocation
    {
        $machine = Machine::findOrFail($data['machine_id']);
        $this->actor->authorize('meter.record', Scope::department($machine->responsible_department_id));
        if (! $machine->is_shared) {
            throw ApiException::businessRule('Only shared machines need usage allocation.');
        }

        $total = $data['total_usage'] ?? $this->meters->usageBetween($machine, $data['counter_type'] ?? 'printed_sides', $data['period_from_on'], $data['period_to_on']);
        if ($total === null) {
            throw ApiException::businessRule('The usage for this period cannot be measured (missing or unknown readings). Record readings or enter a declared total with evidence.');
        }

        $allocated = array_sum(array_map(fn ($l) => (int) $l['allocated_usage'], $data['lines']));
        if ($allocated > $total) {
            throw ApiException::businessRule("Allocated usage ({$allocated}) exceeds the machine's measured usage ({$total}). Shared usage must never be counted twice.");
        }

        return DB::transaction(function () use ($machine, $data, $total) {
            $allocation = SharedUsageAllocation::create([
                'machine_id' => $machine->id,
                'period_from_on' => $data['period_from_on'],
                'period_to_on' => $data['period_to_on'],
                'total_usage' => $total,
                'coordinator_officer_id' => $this->actor->officerId(),
                'status' => 'submitted',
            ]);

            foreach ($data['lines'] as $line) {
                SharedUsageAllocationLine::create([
                    'allocation_id' => $allocation->id,
                    'department_id' => $line['department_id'],
                    'allocated_usage' => $line['allocated_usage'],
                    'basis' => $line['basis'] ?? 'declared',
                ]);
            }

            $this->audit->log(AuditAction::Created, $allocation, null, ['machine' => $machine->code, 'total' => $total]);

            return $allocation->load('lines.department');
        });
    }

    public function reviewAllocation(SharedUsageAllocation $allocation, bool $approve, ?string $remarks): SharedUsageAllocation
    {
        $this->actor->authorize('shared_usage.review');
        $this->actor->assertNotSelf($allocation->coordinator_officer_id, 'an allocation you prepared');

        $allocation->update([
            'status' => $approve ? 'approved' : 'returned',
            'reviewer_officer_id' => $this->actor->officerId(),
            'reviewed_at' => now(),
        ]);
        $this->audit->log(AuditAction::StatusChanged, $allocation, null, ['status' => $allocation->status], $remarks);

        return $allocation;
    }

    // ---- A4 exceptions --------------------------------------------------------

    public function raiseException(array $data): A4Exception
    {
        $request = GoodsRequest::findOrFail($data['request_id']);
        if ($request->request_type !== 'a4_paper') {
            throw ApiException::businessRule('Exceptions apply to A4 paper requests only.');
        }
        $this->actor->authorize('request.create', Scope::department($request->department_id));

        return A4Exception::create([
            'request_id' => $request->id,
            'exception_type' => $data['exception_type'],
            'explanation' => $data['explanation'],
            'raised_by_officer_id' => $this->actor->officerId(),
            'status' => 'pending',
        ]);
    }

    public function decideException(A4Exception $exception, bool $approve, ?string $remarks): A4Exception
    {
        $this->actor->authorize('a4.exception_approve');
        $this->actor->assertNotSelf($exception->raised_by_officer_id, 'an exception you raised');
        if ($exception->status !== 'pending') {
            throw ApiException::businessRule('This exception has already been decided.');
        }

        $exception->update([
            'status' => $approve ? 'approved' : 'rejected',
            'approved_by_officer_id' => $this->actor->officerId(),
            'decided_at' => now(),
        ]);
        $this->audit->log(AuditAction::StatusChanged, $exception, null, ['status' => $exception->status], $remarks);

        return $exception;
    }
}
