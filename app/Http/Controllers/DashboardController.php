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
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Dashboard dimuat bertahap: halaman awal hanya kerangka, tiap widget diambil via AJAX
 * (route dashboard.widget) sehingga query dijalankan per widget dan bisa di-refresh sendiri.
 */
class DashboardController extends Controller
{
    /** Widget yang tersedia => apakah user boleh melihatnya. */
    private function widgets(User $user): array
    {
        return [
            'kpi' => true,
            'queue' => true,
            'overdue' => true,
            'categories' => $user->hasPermission('asset.view'),
            'my-assets' => (bool) $user->employee_id,
            'approvals' => true,
            'lifecycle' => true,
            'activity' => $user->hasPermission('asset.view'),
        ];
    }

    public function __invoke(Request $request): View
    {
        return view('dashboard', ['widgets' => array_keys(array_filter($this->widgets($request->user())))]);
    }

    public function widget(Request $request, string $widget): View
    {
        $user = $request->user();
        abort_unless($this->widgets($user)[$widget] ?? false, 404);

        $data = match ($widget) {
            'kpi' => [
                'statusCounts' => $this->statusCounts(),
                'totalValue' => (float) Asset::where('status', '!=', AssetStatus::Disposed->value)->sum('purchase_cost'),
                'myApprovalCount' => $this->myApprovalsQuery($user)?->count() ?? 0,
                'overdueCount' => AssetLoan::query()->active()->where('due_at', '<', now())->count(),
                'dueSoonCount' => AssetLoan::query()->active()->whereBetween('due_at', [now(), now()->addDays(3)])->count(),
                'warrantySoon' => Asset::query()->where('status', '!=', AssetStatus::Disposed->value)
                    ->whereBetween('warranty_end_date', [today(), today()->addDays(30)])->count(),
            ],
            'queue' => ['workQueue' => $this->workQueue($user)],
            'overdue' => [
                'overdueLoans' => AssetLoan::query()->active()->where('due_at', '<', now())
                    ->with('asset', 'borrower')->orderBy('due_at')->limit(5)->get(),
            ],
            'categories' => [
                'byCategory' => Asset::query()
                    ->join('asset_categories', 'asset_categories.id', '=', 'assets.asset_category_id')
                    ->where('assets.status', '!=', AssetStatus::Disposed->value)
                    ->groupBy('asset_categories.name')
                    ->orderByDesc('total')
                    ->limit(6)
                    ->get(['asset_categories.name', DB::raw('count(*) as total')]),
            ],
            'my-assets' => [
                'myAssets' => Asset::query()->with('category')
                    ->where(fn ($q) => $q
                        ->whereHas('activeAssignment', fn ($a) => $a->where('employee_id', $user->employee_id))
                        ->orWhereHas('activeLoan', fn ($l) => $l->where('borrower_employee_id', $user->employee_id)))
                    ->limit(6)->get(),
            ],
            'approvals' => [
                'myApprovals' => $this->myApprovalsQuery($user)?->with('approvalRequest.requester')->latest('id')->limit(5)->get() ?? collect(),
            ],
            'lifecycle' => ['statusCounts' => $this->statusCounts()],
            'activity' => [
                'recentEvents' => AssetEvent::query()->with('asset', 'performedBy')->latest('occurred_at')->latest('id')->limit(8)->get(),
            ],
        };

        return view("dashboard.widgets.{$widget}", $data);
    }

    private function statusCounts(): Collection
    {
        return Asset::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');
    }

    private function myApprovalsQuery(User $user)
    {
        return $user->employee_id
            ? ApprovalStep::query()->where('approver_employee_id', $user->employee_id)->where('status', 'PENDING')
            : null;
    }

    /** Jumlah pekerjaan menunggu tindak lanjut; null = user tidak berwenang. */
    private function workQueue(User $user): array
    {
        return [
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
    }
}
