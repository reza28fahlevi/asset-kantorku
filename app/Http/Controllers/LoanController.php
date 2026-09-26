<?php

namespace App\Http\Controllers;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\LoanStatus;
use App\Enums\RequestStatus;
use App\Models\AssetLoan;
use App\Models\AssetLoanRequest;
use App\Models\Employee;
use App\Models\LoanExtensionRequest;
use App\Models\Location;
use App\Models\Setting;
use App\Services\AttachmentService;
use App\Services\LoanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Support\Like;

class LoanController extends Controller
{
    public function __construct(private LoanService $service)
    {
    }

    public function index(Request $request): View
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'department_id' => ['nullable', 'integer'],
        ]);

        // Cakupan dasar (visibilitas + pencarian + tanggal + departemen peminjam), tanpa filter status
        $base = AssetLoanRequest::query()
            ->visibleTo($request->user())
            ->when($request->query('q'), function ($q, $t) {
                $like = Like::contains($t);
                $q->where(fn ($w) => $w
                    ->where('request_no', 'ilike', $like)
                    ->orWhereHas('requester', fn ($e) => $e->where('name', 'ilike', $like))
                    ->orWhereHas('borrower', fn ($e) => $e->where('name', 'ilike', $like))
                    ->orWhereHas('assets', fn ($a) => $a->search($t)));
            })
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($request->query('department_id'), fn ($q, $d) => $q->whereHas('borrower', fn ($e) => $e->where('department_id', $d)));

        $statusCounts = (clone $base)->toBase()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($c) => (int) $c);

        $requests = (clone $base)
            ->with('requester', 'borrower.department', 'usageLocation')
            ->withCount('assets')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('loans.index', [
            'requests' => $requests,
            'statusCounts' => $statusCounts,
            'totalCount' => $statusCounts->sum(),
            'departments' => \App\Models\Department::active()->orderBy('name')->get(),
        ]);
    }

    /** Peminjaman aktif, termasuk jatuh tempo & terlambat. */
    public function active(Request $request): View
    {
        $user = $request->user();
        $filter = $request->query('filter');

        $loans = AssetLoan::query()
            ->active()
            ->with('asset.category', 'borrower.department', 'loanRequest', 'extensions')
            ->unless($user->hasPermission('loan.view_all'), fn ($q) => $q->where('borrower_employee_id', $user->employee_id ?? 0))
            ->when($filter === 'overdue', fn ($q) => $q->where('due_at', '<', now()))
            ->when($filter === 'due_soon', fn ($q) => $q->whereBetween('due_at', [now(), now()->addDays(3)]))
            ->orderBy('due_at')
            ->paginate(15)
            ->withQueryString();

        return view('loans.active', [
            'loans' => $loans,
            'filter' => $filter,
            'locations' => Location::active()->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', AssetLoanRequest::class);

        return view('loans.create', [
            'employees' => $request->user()->hasPermission('loan.create_for_others')
                ? Employee::active()->with('department')->orderBy('name')->get()
                : collect(),
            'locations' => Location::active()->orderBy('name')->get(),
            'maxDays' => (int) Setting::get('loan.max_duration_days', 30),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', AssetLoanRequest::class);

        $data = $request->validate([
            'borrower_employee_id' => ['nullable', 'exists:employees,id'],
            'usage_location_id' => ['nullable', Rule::exists('locations', 'id')->whereNull('deleted_at')],
            'purpose' => ['required', 'string', 'max:2000'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'due_date' => ['required', 'date', 'after_or_equal:start_date'],
            'asset_ids' => ['required', 'array', 'min:1', 'max:20'],
            'asset_ids.*' => ['integer', 'distinct', 'exists:assets,id'],
        ]);

        $loan = $this->service->create($data, $request->user(), $request->input('action') === 'submit');

        return redirect()->route('loans.show', $loan)->with('success', $loan->status === RequestStatus::Draft
            ? "Draft {$loan->request_no} tersimpan."
            : "{$loan->request_no} berhasil diajukan dan menunggu approval manager peminjam.");
    }

    public function show(AssetLoanRequest $loan): View
    {
        $this->authorize('view', $loan);

        $loan->load([
            'requester', 'borrower.department', 'borrower.manager', 'usageLocation', 'assets.category', 'assets.location',
            'loans.asset', 'loans.checkedOutBy', 'loans.returnedBy', 'loans.attachments',
            'loans.extensions.approvalRequest.steps.approver',
            'approvalRequest.steps.approver', 'approvalRequest.steps.decidedBy',
        ]);

        return view('loans.show', [
            'loanRequest' => $loan,
            'locations' => Location::active()->orderBy('name')->get(),
        ]);
    }

    public function submit(Request $request, AssetLoanRequest $loan): RedirectResponse
    {
        $this->authorize('submit', $loan);
        $this->service->submit($loan, $request->user());

        return back()->with('success', "{$loan->request_no} berhasil diajukan.");
    }

    public function cancel(Request $request, AssetLoanRequest $loan): RedirectResponse
    {
        $this->authorize('cancel', $loan);
        $data = $request->validate(['cancel_reason' => ['nullable', 'string', 'max:1000']]);
        $this->service->cancel($loan, $request->user(), $data['cancel_reason'] ?? null);

        return back()->with('success', "{$loan->request_no} dibatalkan.");
    }

    public function checkout(Request $request, AssetLoanRequest $loan): RedirectResponse
    {
        $this->authorize('checkout', $loan);

        $data = $request->validate([
            'checked_out_at' => ['required', 'date', 'before_or_equal:now'],
            'condition_out' => ['required', Rule::enum(AssetCondition::class)],
            'checkout_notes' => ['nullable', 'string', 'max:2000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => AttachmentService::rules(),
        ]);
        $this->service->checkout($loan, $data, $request->user());

        return back()->with('success', 'Serah-terima peminjaman berhasil dicatat.');
    }

    public function returnLoan(Request $request, AssetLoan $assetLoan): RedirectResponse
    {
        $this->authorize('return', $assetLoan);

        $data = $request->validate([
            'returned_at' => ['required', 'date', 'before_or_equal:now'],
            'condition_in' => ['required', Rule::enum(AssetCondition::class)],
            'next_status' => ['required', Rule::in([AssetStatus::Available->value, AssetStatus::InRepair->value, AssetStatus::Lost->value])],
            'location_id' => ['nullable', Rule::exists('locations', 'id')->whereNull('deleted_at')],
            'return_notes' => ['nullable', 'string', 'max:2000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => AttachmentService::rules(),
        ]);
        $this->service->returnLoan($assetLoan, $data, $request->user());

        return back()->with('success', 'Pengembalian peminjaman berhasil dicatat.');
    }

    public function extend(Request $request, AssetLoan $assetLoan): RedirectResponse
    {
        $this->authorize('extend', $assetLoan);

        $data = $request->validate([
            'requested_due_date' => ['required', 'date', 'after:today'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $extension = $this->service->requestExtension($assetLoan, $data, $request->user());

        return back()->with('success', "Pengajuan perpanjangan {$extension->request_no} menunggu approval.");
    }

    public function cancelExtension(LoanExtensionRequest $extension): RedirectResponse
    {
        $this->authorize('cancel', $extension);
        $this->service->cancelExtension($extension);

        return back()->with('success', 'Pengajuan perpanjangan dibatalkan.');
    }
}
