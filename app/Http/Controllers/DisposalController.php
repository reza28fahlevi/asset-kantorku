<?php

namespace App\Http\Controllers;

use App\Enums\DisposalMethod;
use App\Enums\DisposalReason;
use App\Enums\DisposalStatus;
use App\Models\Asset;
use App\Models\DisposalRequest;
use App\Services\AttachmentService;
use App\Services\DisposalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Support\Like;

class DisposalController extends Controller
{
    public function __construct(private DisposalService $service)
    {
    }

    public function index(Request $request): View
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'department_id' => ['nullable', 'integer'],
        ]);

        // Cakupan dasar (visibilitas + pencarian + tanggal + departemen aset), tanpa filter status
        $base = DisposalRequest::query()
            ->visibleTo($request->user())
            ->when($request->query('q'), function ($q, $t) {
                $like = Like::contains($t);
                $q->where(fn ($w) => $w
                    ->where('request_no', 'ilike', $like)
                    ->orWhereHas('asset', fn ($a) => $a->search($t))
                    ->orWhereHas('requester', fn ($e) => $e->where('name', 'ilike', $like)));
            })
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($request->query('department_id'), fn ($q, $d) => $q->whereHas('asset', fn ($a) => $a->where('department_id', $d)));

        $statusCounts = (clone $base)->toBase()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($c) => (int) $c);

        $requests = (clone $base)
            ->with('requester', 'asset.category', 'asset.department')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('disposals.index', [
            'requests' => $requests,
            'statusCounts' => $statusCounts,
            'totalCount' => $statusCounts->sum(),
            'departments' => \App\Models\Department::active()->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', DisposalRequest::class);

        $assetId = $request->query('asset_id', $request->old('asset_id'));

        return view('disposals.create', [
            'asset' => $assetId ? Asset::with('category')->find($assetId) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', DisposalRequest::class);

        $data = $request->validate([
            'asset_id' => ['required', 'exists:assets,id'],
            'reason_type' => ['required', Rule::enum(DisposalReason::class)],
            'reason' => ['required', 'string', 'max:2000'],
            'condition_description' => ['nullable', 'string', 'max:2000'],
            'planned_method' => ['required', Rule::enum(DisposalMethod::class)],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => AttachmentService::rules(),
        ]);

        $disposal = $this->service->create($data, $request->user(), $request->input('action') === 'submit');

        return redirect()->route('disposals.show', $disposal)->with('success', $disposal->status === DisposalStatus::Draft
            ? "Draft {$disposal->request_no} tersimpan."
            : "{$disposal->request_no} diajukan. Aset dikunci sebagai Menunggu Disposal.");
    }

    public function show(DisposalRequest $disposal): View
    {
        $this->authorize('view', $disposal);

        $disposal->load([
            'requester', 'asset.category', 'asset.location', 'executedBy', 'attachments.uploadedBy',
            'approvalRequest.steps.approver', 'approvalRequest.steps.decidedBy',
        ]);

        return view('disposals.show', compact('disposal'));
    }

    public function submit(Request $request, DisposalRequest $disposal): RedirectResponse
    {
        $this->authorize('submit', $disposal);
        $this->service->submit($disposal, $request->user());

        return back()->with('success', "{$disposal->request_no} berhasil diajukan.");
    }

    public function cancel(Request $request, DisposalRequest $disposal): RedirectResponse
    {
        $this->authorize('cancel', $disposal);
        $data = $request->validate(['cancel_reason' => ['nullable', 'string', 'max:1000']]);
        $this->service->cancel($disposal, $request->user(), $data['cancel_reason'] ?? null);

        return back()->with('success', "{$disposal->request_no} dibatalkan.");
    }

    public function execute(Request $request, DisposalRequest $disposal): RedirectResponse
    {
        $this->authorize('execute', $disposal);

        $data = $request->validate([
            'executed_at' => ['required', 'date', 'before_or_equal:today'],
            'actual_method' => ['required', Rule::enum(DisposalMethod::class)],
            'disposal_recipient' => ['nullable', 'string', 'max:200'],
            'proceeds_amount' => ['nullable', 'numeric', 'min:0'],
            'execution_notes' => ['nullable', 'string', 'max:2000'],
            'attachments' => ['required', 'array', 'min:1', 'max:5'],
            'attachments.*' => AttachmentService::rules(true),
        ]);
        $this->service->execute($disposal, $data, $request->user());

        return back()->with('success', 'Disposal selesai. Aset berstatus Dihapus dan histori tetap tersimpan.');
    }
}
