<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Services\ApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function __construct(private ApprovalService $approvals)
    {
    }

    /** Approval inbox: antrean milik approver yang login + riwayat keputusannya. */
    public function index(Request $request): View
    {
        $employeeId = $request->user()->employee_id ?? 0;
        $tab = $request->query('tab') === 'history' ? 'history' : 'pending';

        $steps = ApprovalStep::query()
            ->where('approver_employee_id', $employeeId)
            ->when($tab === 'pending',
                fn ($q) => $q->where('status', ApprovalStatus::Pending->value)->orderBy('id'),
                fn ($q) => $q->where('status', '!=', ApprovalStatus::Pending->value)->orderByDesc('decided_at')->orderByDesc('id'))
            ->when($request->query('type'), fn ($q, $t) => $q->whereHas('approvalRequest', fn ($r) => $r->where('request_type', $t)))
            ->with('approvalRequest.requester.department')
            ->paginate(10)
            ->withQueryString();

        // Muat permintaan sumber beserta relasi untuk ringkasan dampak
        $steps->getCollection()->each(function (ApprovalStep $step) {
            $relation = ApprovalRequest::subjectRelation($step->approvalRequest->request_type);
            $step->approvalRequest->load([
                $relation => fn ($q) => $q->with(match ($relation) {
                    'procurementRequest' => ['requester', 'department', 'items', 'attachments'],
                    'assignmentRequest' => ['requester', 'recipient', 'assets', 'location'],
                    'loanRequest' => ['requester', 'borrower', 'assets'],
                    'loanExtensionRequest' => ['loan.asset', 'loan.borrower'],
                    'disposalRequest' => ['requester', 'asset', 'attachments'],
                }),
            ]);
        });

        $pendingCount = ApprovalStep::where('approver_employee_id', $employeeId)->where('status', ApprovalStatus::Pending->value)->count();

        return view('approvals.index', compact('steps', 'tab', 'pendingCount'));
    }

    public function approve(Request $request, ApprovalStep $step): RedirectResponse
    {
        $this->authorize('decide', $step);
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:2000']]);

        $this->approvals->decide($step, $request->user(), true, $data['comment'] ?? null);

        return back()->with('success', 'Permintaan disetujui.');
    }

    public function reject(Request $request, ApprovalStep $step): RedirectResponse
    {
        $this->authorize('decide', $step);
        $data = $request->validate(['comment' => ['required', 'string', 'min:5', 'max:2000']]);

        $this->approvals->decide($step, $request->user(), false, $data['comment']);

        return back()->with('success', 'Permintaan ditolak.');
    }
}
