<?php

/*
| Phases 2–7 routes. Included inside the authenticated group of api_v1.php
| (auth:sanctum, active, password.changed, throttle:api). Every route names the
| permission it needs; "a|b" accepts any one of those codes.
*/

use App\Http\Controllers\Api\V1\Assets\AssetController;
use App\Http\Controllers\Api\V1\Assets\MaintenanceController;
use App\Http\Controllers\Api\V1\Assets\TransferController;
use App\Http\Controllers\Api\V1\Catalogue\ItemCategoryController;
use App\Http\Controllers\Api\V1\Catalogue\ItemController;
use App\Http\Controllers\Api\V1\Catalogue\SupplierController;
use App\Http\Controllers\Api\V1\Catalogue\UnitController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\Paper\PaperController;
use App\Http\Controllers\Api\V1\Receiving\GrnController;
use App\Http\Controllers\Api\V1\Receiving\PurchaseOrderController;
use App\Http\Controllers\Api\V1\Receiving\SupplierReturnController;
use App\Http\Controllers\Api\V1\Requests\DepartmentReturnController;
use App\Http\Controllers\Api\V1\Requests\IssueController;
use App\Http\Controllers\Api\V1\Requests\RequestController;
use App\Http\Controllers\Api\V1\Stock\StockAdjustmentController;
use App\Http\Controllers\Api\V1\Stock\StockController;
use App\Http\Controllers\Api\V1\Stock\StockCountController;
use App\Models\RepairCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('dashboard', DashboardController::class)->middleware('permission:dashboard.view');

// ---- Phase 2: catalogue ----------------------------------------------------------
$catalogue = [
    'item-categories' => [ItemCategoryController::class, 'item_category', 'item.view', 'category.manage'],
    'units' => [UnitController::class, 'unit', 'item.view', 'unit.manage'],
    'items' => [ItemController::class, 'item', 'item.view', 'item.manage'],
    'suppliers' => [SupplierController::class, 'supplier', 'supplier.view', 'supplier.manage'],
];
foreach ($catalogue as $path => [$controller, $param, $view, $manage]) {
    Route::get($path, [$controller, 'index'])->middleware("permission:{$view}");
    Route::post($path, [$controller, 'store'])->middleware("permission:{$manage}");
    Route::get("{$path}/{{$param}}", [$controller, 'show'])->middleware("permission:{$view}");
    Route::patch("{$path}/{{$param}}", [$controller, 'update'])->middleware("permission:{$manage}");
    Route::delete("{$path}/{{$param}}", [$controller, 'destroy'])->middleware("permission:{$manage}");
}
Route::get('items/{item}/conversions', [ItemController::class, 'conversions'])->middleware('permission:item.view');
Route::post('items/{item}/conversions', [ItemController::class, 'addConversion'])->middleware('permission:item.manage');
Route::post('items/{item}/conversions/{conversion}/approve', [ItemController::class, 'approveConversion'])->middleware('permission:item.manage')->withoutScopedBindings();

// ---- Phase 3: receiving and stock -----------------------------------------------
Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])->middleware('permission:po.view');
Route::post('purchase-orders', [PurchaseOrderController::class, 'store'])->middleware('permission:po.manage');
Route::get('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->middleware('permission:po.view');
Route::patch('purchase-orders/{purchaseOrder}/status', [PurchaseOrderController::class, 'setStatus'])->middleware('permission:po.manage');

Route::get('grns', [GrnController::class, 'index'])->middleware('permission:grn.view');
Route::post('grns', [GrnController::class, 'store'])->middleware('permission:grn.create');
Route::get('grns/{grn}', [GrnController::class, 'show'])->middleware('permission:grn.view');
Route::patch('grns/{grn}', [GrnController::class, 'update'])->middleware('permission:grn.create');
Route::post('grns/{grn}/submit', [GrnController::class, 'submit'])->middleware('permission:grn.create');
Route::post('grns/{grn}/inspect', [GrnController::class, 'inspect'])->middleware('permission:grn.inspect');
Route::post('grns/{grn}/approve-post', [GrnController::class, 'approvePost'])->middleware('permission:grn.approve_post');
Route::post('grns/{grn}/reject', [GrnController::class, 'reject'])->middleware('permission:grn.approve_post');
Route::post('grns/{grn}/reverse', [GrnController::class, 'reverse'])->middleware('permission:grn.reverse');

Route::get('supplier-returns', [SupplierReturnController::class, 'index'])->middleware('permission:supplier_return.manage');
Route::post('supplier-returns', [SupplierReturnController::class, 'store'])->middleware('permission:supplier_return.manage');
Route::get('supplier-returns/{supplierReturn}', [SupplierReturnController::class, 'show'])->middleware('permission:supplier_return.manage');
Route::post('supplier-returns/{supplierReturn}/post', [SupplierReturnController::class, 'post'])->middleware('permission:supplier_return.manage');

Route::get('stock/balances', [StockController::class, 'balances'])->middleware('permission:stock.view');
Route::get('stock/ledger', [StockController::class, 'ledger'])->middleware('permission:ledger.view');

Route::get('stock-counts', [StockCountController::class, 'index'])->middleware('permission:stock.view');
Route::post('stock-counts', [StockCountController::class, 'store'])->middleware('permission:count.create');
Route::get('stock-counts/{stockCount}', [StockCountController::class, 'show'])->middleware('permission:stock.view');
Route::post('stock-counts/{stockCount}/record', [StockCountController::class, 'record'])->middleware('permission:count.record');
Route::post('stock-counts/{stockCount}/submit', [StockCountController::class, 'submit'])->middleware('permission:count.record');
Route::post('stock-counts/{stockCount}/approve', [StockCountController::class, 'approve'])->middleware('permission:count.approve');

Route::get('stock-adjustments', [StockAdjustmentController::class, 'index'])->middleware('permission:stock.view');
Route::post('stock-adjustments', [StockAdjustmentController::class, 'store'])->middleware('permission:adjustment.create');
Route::get('stock-adjustments/{stockAdjustment}', [StockAdjustmentController::class, 'show'])->middleware('permission:stock.view');
Route::post('stock-adjustments/{stockAdjustment}/approve', [StockAdjustmentController::class, 'approvePost'])->middleware('permission:adjustment.approve');
Route::post('stock-adjustments/{stockAdjustment}/reject', [StockAdjustmentController::class, 'reject'])->middleware('permission:adjustment.approve');

// ---- Phase 4: requests, issues, returns -----------------------------------------
$viewRequests = 'permission:request.view_own|request.view_department|request.view_all';
Route::get('requests', [RequestController::class, 'index'])->middleware($viewRequests);
Route::post('requests', [RequestController::class, 'store'])->middleware('permission:request.create');
Route::get('requests/{goodsRequest}', [RequestController::class, 'show'])->middleware($viewRequests);
Route::patch('requests/{goodsRequest}', [RequestController::class, 'update'])->middleware('permission:request.create');
Route::post('requests/{goodsRequest}/submit', [RequestController::class, 'submit'])->middleware('permission:request.submit');
Route::post('requests/{goodsRequest}/certify', [RequestController::class, 'certify'])->middleware('permission:request.certify');
Route::post('requests/{goodsRequest}/approve', [RequestController::class, 'approve'])->middleware('permission:request.approve');
Route::post('requests/{goodsRequest}/return', [RequestController::class, 'returnForClarification'])->middleware('permission:request.return');
Route::post('requests/{goodsRequest}/reject', [RequestController::class, 'reject'])->middleware('permission:request.reject');
Route::post('requests/{goodsRequest}/cancel', [RequestController::class, 'cancel'])->middleware('permission:request.cancel');

Route::get('issues', [IssueController::class, 'index'])->middleware('permission:request.view_all|issue.acknowledge');
Route::post('issues', [IssueController::class, 'store'])->middleware('permission:issue.create');
Route::get('issues/{issue}', [IssueController::class, 'show'])->middleware('permission:request.view_all|issue.acknowledge');
Route::post('issues/{issue}/post', [IssueController::class, 'post'])->middleware('permission:issue.post');
Route::post('issues/{issue}/reverse', [IssueController::class, 'reverse'])->middleware('permission:issue.reverse');
Route::post('issues/{issue}/acknowledge', [IssueController::class, 'acknowledge'])->middleware('permission:issue.acknowledge');

Route::get('department-returns', [DepartmentReturnController::class, 'index'])->middleware('permission:return.create|return.inspect');
Route::post('department-returns', [DepartmentReturnController::class, 'store'])->middleware('permission:return.create');
Route::get('department-returns/{departmentReturn}', [DepartmentReturnController::class, 'show'])->middleware('permission:return.create|return.inspect');
Route::post('department-returns/{departmentReturn}/inspect', [DepartmentReturnController::class, 'inspect'])->middleware('permission:return.inspect');
Route::post('department-returns/{departmentReturn}/post', [DepartmentReturnController::class, 'post'])->middleware('permission:return.inspect');

Route::get('discrepancies', [DepartmentReturnController::class, 'discrepancies'])->middleware('permission:stock.view');
Route::post('discrepancies/{discrepancy}/resolve', [DepartmentReturnController::class, 'resolveDiscrepancy'])->middleware('permission:adjustment.approve');

// ---- Phase 5: A4 paper control ----------------------------------------------------
Route::get('machines', [PaperController::class, 'machines'])->middleware('permission:machine.manage|meter.record|meter.verify');
Route::post('machines', [PaperController::class, 'storeMachine'])->middleware('permission:machine.manage');
Route::get('machines/{machine}', [PaperController::class, 'showMachine'])->middleware('permission:machine.manage|meter.record|meter.verify');
Route::get('meter-observations', [PaperController::class, 'observations'])->middleware('permission:meter.record|meter.verify');
Route::post('meter-observations', [PaperController::class, 'storeObservation'])->middleware('permission:meter.record');
Route::post('meter-observations/{meterObservation}/verify', [PaperController::class, 'verifyObservation'])->middleware('permission:meter.verify');
Route::get('shared-usage-allocations', [PaperController::class, 'allocations'])->middleware('permission:meter.record|shared_usage.review');
Route::post('shared-usage-allocations', [PaperController::class, 'storeAllocation'])->middleware('permission:meter.record');
Route::post('shared-usage-allocations/{sharedUsageAllocation}/review', [PaperController::class, 'reviewAllocation'])->middleware('permission:shared_usage.review');
Route::get('paper-statements', [PaperController::class, 'statements'])->middleware('permission:paper_statement.manage|meter.verify');
Route::post('paper-statements', [PaperController::class, 'storeStatement'])->middleware('permission:paper_statement.manage');
Route::post('paper-statements/{paperStatement}/certify', [PaperController::class, 'certifyStatement'])->middleware('permission:request.certify');
Route::get('a4-exceptions', [PaperController::class, 'exceptions'])->middleware('permission:request.create|a4.exception_approve');
Route::post('a4-exceptions', [PaperController::class, 'storeException'])->middleware('permission:request.create');
Route::post('a4-exceptions/{a4Exception}/decide', [PaperController::class, 'decideException'])->middleware('permission:a4.exception_approve');

// ---- Phase 6: assets, QR, transfers ----------------------------------------------
Route::get('assets', [AssetController::class, 'index'])->middleware('permission:asset.view');
Route::post('assets', [AssetController::class, 'store'])->middleware('permission:asset.create');
Route::get('assets/scan/{token}', [AssetController::class, 'scan'])->middleware('permission:asset.view')->where('token', '[a-f0-9]{32}');
Route::get('assets/{asset}', [AssetController::class, 'show'])->middleware('permission:asset.view');
Route::patch('assets/{asset}', [AssetController::class, 'update'])->middleware('permission:asset.update');
Route::get('assets/{asset}/qr', [AssetController::class, 'qr'])->middleware('permission:asset.print_qr');

$viewTransfers = 'permission:transfer.create|transfer.approve|transfer.dispatch|transfer.receive';
Route::get('asset-transfers', [TransferController::class, 'index'])->middleware($viewTransfers);
Route::post('asset-transfers', [TransferController::class, 'store'])->middleware('permission:transfer.create');
Route::get('asset-transfers/{assetTransfer}', [TransferController::class, 'show'])->middleware($viewTransfers);
Route::post('asset-transfers/{assetTransfer}/approve', [TransferController::class, 'approve'])->middleware('permission:transfer.approve');
Route::post('asset-transfers/{assetTransfer}/reject', [TransferController::class, 'reject'])->middleware('permission:transfer.approve');
Route::post('asset-transfers/{assetTransfer}/dispatch', [TransferController::class, 'dispatch'])->middleware('permission:transfer.dispatch');
Route::post('asset-transfers/{assetTransfer}/receive', [TransferController::class, 'receive'])->middleware('permission:transfer.receive');
Route::post('asset-transfers/{assetTransfer}/refuse', [TransferController::class, 'refuse'])->middleware('permission:transfer.receive');
Route::post('asset-transfers/{assetTransfer}/return', [TransferController::class, 'returnLoan'])->middleware('permission:loan.manage');

// ---- Phase 7: maintenance and verification ---------------------------------------
Route::get('warranties', [MaintenanceController::class, 'warranties'])->middleware('permission:asset.view');
Route::post('warranties', [MaintenanceController::class, 'storeWarranty'])->middleware('permission:warranty.manage');
Route::post('warranties/{warranty}/extend', [MaintenanceController::class, 'extendWarranty'])->middleware('permission:warranty.manage');

Route::get('repair-cases', [MaintenanceController::class, 'repairs'])->middleware('permission:asset.view');
Route::post('repair-cases', [MaintenanceController::class, 'storeRepair'])->middleware('permission:repair.create');
Route::get('repair-cases/{repairCase}', [MaintenanceController::class, 'showRepair'])->middleware('permission:asset.view');
Route::post('repair-cases/{repairCase}/updates', [MaintenanceController::class, 'addRepairUpdate'])->middleware('permission:repair.update');
foreach (['assess' => 'repair.update', 'approve' => 'repair.approve', 'dispatch' => 'repair.dispatch', 'return' => 'repair.update', 'test' => 'repair.update', 'close' => 'repair.close'] as $action => $code) {
    Route::post("repair-cases/{repairCase}/{$action}", fn (Request $r, RepairCase $repairCase) => app(MaintenanceController::class)->repairAction($r, $repairCase, $action))
        ->middleware("permission:{$code}");
}

Route::get('service-schedules', [MaintenanceController::class, 'schedules'])->middleware('permission:asset.view');
Route::post('service-schedules', [MaintenanceController::class, 'storeSchedule'])->middleware('permission:service.manage');
Route::post('service-schedules/{serviceSchedule}/record', [MaintenanceController::class, 'recordService'])->middleware('permission:service.manage');

Route::get('asset-verifications', [MaintenanceController::class, 'verificationSessions'])->middleware('permission:verification.perform');
Route::post('asset-verifications', [MaintenanceController::class, 'storeVerificationSession'])->middleware('permission:verification.perform');
Route::get('asset-verifications/{assetVerificationSession}', [MaintenanceController::class, 'showVerificationSession'])->middleware('permission:verification.perform');
Route::post('asset-verifications/{assetVerificationSession}/verify', [MaintenanceController::class, 'verifyAsset'])->middleware('permission:verification.perform');
Route::post('asset-verifications/{assetVerificationSession}/close', [MaintenanceController::class, 'closeVerificationSession'])->middleware('permission:verification.perform');

Route::get('valuations', [MaintenanceController::class, 'valuations'])->middleware('permission:valuation.manage|valuation.approve');
Route::post('valuations', [MaintenanceController::class, 'storeValuation'])->middleware('permission:valuation.manage');
Route::post('valuations/{valuation}/decide', [MaintenanceController::class, 'decideValuation'])->middleware('permission:valuation.approve');

Route::get('disposals', [MaintenanceController::class, 'disposals'])->middleware('permission:disposal.manage|disposal.approve');
Route::post('disposals', [MaintenanceController::class, 'storeDisposal'])->middleware('permission:disposal.manage');
Route::post('disposals/{disposal}/decide', [MaintenanceController::class, 'decideDisposal'])->middleware('permission:disposal.approve');
Route::post('disposals/{disposal}/complete', [MaintenanceController::class, 'completeDisposal'])->middleware('permission:disposal.manage');
