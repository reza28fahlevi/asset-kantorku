<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Enums\ProcurementStatus;
use App\Enums\RequestStatus;
use App\Models\ApprovalStep;
use App\Models\Asset;
use App\Models\AssetEvent;
use App\Models\AssetLoan;
use App\Models\AssetLoanRequest;
use App\Models\AssignmentRequest;
use App\Models\DisposalRequest;
use App\Models\ProcurementRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $statusCounts = Asset::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalValue = (float) Asset::where('status', '!=', AssetStatus::Disposed->value)->sum('purchase_cost');

        $byCategory = Asset::query()
            ->join('asset_categories', 'asset_categories.id', '=', 'assets.asset_category_id')
            ->where('assets.status', '!=', AssetStatus::Disposed->value)
            ->groupBy('asset_categories.name')
            ->orderByDesc('total')
            ->limit(6)
            ->get(['asset_categories.name', DB::raw('count(*) as total')]);

        $myApprovals = $user->employee_id
            ? ApprovalStep::query()
                ->with('approvalRequest.requester')
                ->where('approver_employee_id', $user->employee_id)
                ->where('status', 'PENDING')
                ->latest('id')->limit(5)->get()
            : collect();

        $overdueLoans = AssetLoan::query()->active()->where('due_at', '<', now())
            ->with('asset', 'borrower')->orderBy('due_at')->limit(5)->get();
        $dueSoonCount = AssetLoan::query()->active()->whereBetween('due_at', [now(), now()->addDays(3)])->count();
        $warrantySoon = Asset::query()->where('status', '!=', AssetStatus::Disposed->value)
            ->whereBetween('warranty_end_date', [today(), today()->addDays(30)])->count();

        $workQueue = [
            'procurement_order' => $user->hasPermission('procurement.order')
                ? ProcurementRequest::where('status', ProcurementStatus::Approved->value)->count() : null,
            'procurement_receive' => $user->hasPermission('procurement.receive')
                ? ProcurementRequest::whereIn('status', [ProcurementStatus::Ordered->value, ProcurementStatus::PartiallyReceived->value])->count() : null,
            'assignment_handover' => $user->hasPermission('assignment.handover')
                ? AssignmentRequest::where('status', RequestStatus::Approved->value)->count() : null,
            'loan_checkout' => $user->hasPermission('loan.checkout')
                ? AssetLoanRequest::where('status', RequestStatus::Approved->value)->count() : null,
            'disposal_execute' => $user->hasPermission('disposal.execute')
                ? DisposalRequest::where('status', 'APPROVED')->count() : null,
        ];

        $myAssets = $user->employee_id
            ? Asset::query()->with('category')
                ->where(fn ($q) => $q
                    ->whereHas('activeAssignment', fn ($a) => $a->where('employee_id', $user->employee_id))
                    ->orWhereHas('activeLoan', fn ($l) => $l->where('borrower_employee_id', $user->employee_id)))
                ->limit(6)->get()
            : collect();

        $recentEvents = $user->hasPermission('asset.view')
            ? AssetEvent::query()->with('asset', 'performedBy')->latest('occurred_at')->latest('id')->limit(8)->get()
            : collect();

        return view('dashboard', compact(
            'statusCounts', 'totalValue', 'byCategory', 'myApprovals', 'overdueLoans', 'dueSoonCount',
            'warrantySoon', 'workQueue', 'myAssets', 'recentEvents',
        ));
    }
}
