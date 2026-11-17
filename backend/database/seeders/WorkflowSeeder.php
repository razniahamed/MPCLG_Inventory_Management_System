<?php

namespace Database\Seeders;

use App\Models\ApprovalFlow;
use App\Models\ApprovalFlowStep;
use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Approval workflows (Section 7.6). Approval limits per tier are configured
 * here, not hard-coded (Section 16, decision 13). Idempotent.
 */
class WorkflowSeeder extends Seeder
{
    /** code => [name, [[step name, permission, can_reduce, min_amount]]] */
    private const FLOWS = [
        'GOODS_REQUEST' => ['Department goods request', [
            ['Department head certification', 'request.certify', false, null],
            ['Stores approval', 'request.approve', true, null],
        ]],
        'A4_REQUEST' => ['A4 paper request', [
            ['Department head certification', 'request.certify', false, null],
            ['Supply approval with meter evidence', 'request.approve', true, null],
        ]],
        'GRN' => ['Goods received note', [['Approve and post', 'grn.approve_post', false, null]]],
        'STOCK_ADJUSTMENT' => ['Stock adjustment', [['Independent approval', 'adjustment.approve', false, null]]],
        'ASSET_TRANSFER' => ['Asset transfer / loan', [['Approve transfer', 'transfer.approve', false, null]]],
        'REPAIR_DISPATCH' => ['Repair dispatch', [['Approve repair expenditure', 'repair.approve', false, null]]],
        'VALUE_CHANGE' => ['Asset valuation', [['Approve valuation', 'valuation.approve', false, null]]],
        'DISPOSAL' => ['Asset disposal', [['Approve disposal', 'disposal.approve', false, null]]],
    ];

    public function run(): void
    {
        $permissions = Permission::pluck('id', 'code');

        foreach (self::FLOWS as $code => [$name, $steps]) {
            $flow = ApprovalFlow::updateOrCreate(['code' => $code], ['name' => $name]);

            foreach ($steps as $i => [$stepName, $permission, $canReduce, $min]) {
                ApprovalFlowStep::updateOrCreate(
                    ['flow_id' => $flow->id, 'step_no' => $i + 1],
                    [
                        'name' => $stepName,
                        'required_permission_id' => $permissions[$permission],
                        'can_reduce_qty' => $canReduce,
                        'min_amount' => $min,
                        'is_required' => true,
                    ],
                );
            }
        }
    }
}
