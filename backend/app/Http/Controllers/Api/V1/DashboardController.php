<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\Discrepancy;
use App\Models\GoodsRequest;
use App\Models\Grn;
use App\Models\Issue;
use App\Models\Item;
use App\Models\MeterObservation;
use App\Models\RepairCase;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\Warranty;
use App\Services\Access\Actor;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Work queues and headline figures for the signed-in user. Each block is
 * included only when the user holds the matching permission.
 */
class DashboardController extends Controller
{
    public function __invoke(Actor $actor): JsonResponse
    {
        $data = ['tasks' => [], 'figures' => []];
        $task = function (string $key, string $label, int $count, string $link) use (&$data) {
            $data['tasks'][] = compact('key', 'label', 'count', 'link');
        };

        if ($actor->can('request.certify')) {
            $task('certify', 'Requests to certify', GoodsRequest::where('status', 'submitted')->count(), '/requests?awaiting=certify');
        }
        if ($actor->can('request.approve')) {
            $task('approve', 'Requests to approve', GoodsRequest::where('status', 'under_review')->count(), '/requests?awaiting=approve');
        }
        if ($actor->can('issue.post')) {
            $task('issue', 'Approved requests to issue', GoodsRequest::whereIn('status', GoodsRequest::OPEN_FOR_ISSUE)->count(), '/requests?awaiting=issue');
            $task('draft_issues', 'Issues prepared, not posted', Issue::where('status', 'draft')->count(), '/issues?status=draft');
        }
        if ($actor->can('issue.acknowledge')) {
            $task('acknowledge', 'Issues awaiting receipt confirmation', Issue::where('status', 'posted')->whereDoesntHave('acknowledgement')->count(), '/issues?unacknowledged=true');
        }
        if ($actor->can('grn.inspect')) {
            $task('inspect', 'GRNs awaiting inspection', Grn::where('status', 'awaiting_inspection')->count(), '/grns?status=awaiting_inspection');
        }
        if ($actor->can('grn.approve_post')) {
            $task('grn_approve', 'GRNs awaiting approval', Grn::where('status', 'awaiting_approval')->count(), '/grns?status=awaiting_approval');
        }
        if ($actor->can('adjustment.approve')) {
            $task('adjustments', 'Stock adjustments awaiting approval', StockAdjustment::where('status', 'submitted')->count(), '/stock/adjustments?status=submitted');
        }
        if ($actor->can('transfer.approve')) {
            $task('transfers', 'Asset transfers awaiting approval', AssetTransfer::where('status', 'submitted')->count(), '/assets/transfers?status=submitted');
        }
        if ($actor->can('transfer.receive')) {
            $task('in_transit', 'Assets in transit to receive', AssetTransfer::where('status', 'dispatched')->count(), '/assets/transfers?status=dispatched');
        }
        if ($actor->can('meter.verify')) {
            $task('meters', 'Meter readings to verify', MeterObservation::whereIn('status', ['submitted', 'flagged'])->count(), '/a4/readings');
        }
        if ($actor->can('repair.update')) {
            $task('repairs', 'Open repair cases', RepairCase::where('status', '!=', 'closed')->count(), '/maintenance/repairs?open=true');
        }
        if ($actor->can('stock.view')) {
            $task('discrepancies', 'Open discrepancies', Discrepancy::where('status', '!=', 'resolved')->count(), '/discrepancies');

            $low = StockBalance::query()
                ->join('items', 'items.id', '=', 'stock_balances.item_id')
                ->whereNotNull('items.reorder_level')
                ->whereColumn('stock_balances.on_hand_usable', '<=', 'items.reorder_level')
                ->count();
            $data['figures']['low_stock_lines'] = $low;
            $data['figures']['stocked_items'] = StockBalance::where('on_hand_usable', '>', 0)->distinct('item_id')->count('item_id');
            $data['figures']['items'] = Item::count();
        }
        if ($actor->can('asset.view')) {
            $data['figures']['assets_by_status'] = Asset::query()->select('operational_status', DB::raw('COUNT(*) AS total'))->groupBy('operational_status')->pluck('total', 'operational_status');
            $data['figures']['assets'] = Asset::count();
            $data['figures']['warranties_expiring_60d'] = Warranty::where('status', 'active')->whereBetween('ends_on', [now()->toDateString(), now()->addDays(60)->toDateString()])->count();
        }
        if ($actor->can('request.view_all') || $actor->can('request.view_department')) {
            $data['figures']['requests_this_month'] = GoodsRequest::where('created_at', '>=', now()->startOfMonth())->count();
        }

        return ApiResponse::success($data);
    }
}
