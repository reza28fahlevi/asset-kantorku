<?php

namespace App\Http\Controllers;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\RequestStatus;
use App\Models\AssetAssignment;
use App\Models\AssignmentRequest;
use App\Models\Employee;
use App\Models\Location;
use App\Services\AssignmentService;
use App\Services\AttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Support\Like;

class AssignmentController extends Controller
{
    public function __construct(private AssignmentService $service)
    {
    }

    public function index(Request $request): View
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'department_id' => ['nullable', 'integer'],
        ]);

        // Cakupan dasar (visibilitas + pencarian + tanggal + departemen penerima), tanpa filter status
        $base = AssignmentRequest::query()
            ->visibleTo($request->user())
            ->when($request->query('q'), function ($q, $t) {
                $like = Like::contains($t);
                $q->where(fn ($w) => $w
                    ->where('request_no', 'ilike', $like)
                    ->orWhereHas('requester', fn ($e) => $e->where('name', 'ilike', $like))
                    ->orWhereHas('recipient', fn ($e) => $e->where('name', 'ilike', $like))
                    ->orWhereHas('assets', fn ($a) => $a->search($t)));
            })
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($request->query('department_id'), fn ($q, $d) => $q->whereHas('recipient', fn ($e) => $e->where('department_id', $d)));

        $statusCounts = (clone $base)->toBase()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($c) => (int) $c);

        $requests = (clone $base)
            ->with('requester', 'recipient.department', 'location')
            ->withCount('assets')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('assignments.index', [
            'requests' => $requests,
            'statusCounts' => $statusCounts,
            'totalCount' => $statusCounts->sum(),
            'departments' => \App\Models\Department::active()->orderBy('name')->get(),
        ]);
    }

    /** Daftar assignment aktif (aset yang sedang dipegang karyawan). */
    public function active(Request $request): View
    {
        $user = $request->user();
        $assignments = AssetAssignment::query()
            ->active()
            ->with('asset.category', 'employee.department', 'location', 'assignmentRequest')
            ->unless($user->hasPermission('assignment.view_all'), fn ($q) => $q->where('employee_id', $user->employee_id ?? 0))
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w
                ->whereHas('asset', fn ($a) => $a->search($t))
                ->orWhereHas('employee', fn ($e) => $e->where('name', 'ilike', Like::contains($t)))))
            ->orderByDesc('assigned_at')
            ->paginate(15)
            ->withQueryString();

        return view('assignments.active', [
            'assignments' => $assignments,
            'locations' => Location::active()->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', AssignmentRequest::class);

        return view('assignments.create', [
            'employees' => $request->user()->hasPermission('assignment.create_for_others')
                ? Employee::active()->with('department')->orderBy('name')->get()
                : collect(),
            'locations' => Location::active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', AssignmentRequest::class);

        $data = $request->validate([
            'recipient_employee_id' => ['nullable', 'exists:employees,id'],
            'location_id' => ['required', Rule::exists('locations', 'id')->whereNull('deleted_at')],
            'purpose' => ['required', 'string', 'max:2000'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'asset_ids' => ['required', 'array', 'min:1', 'max:50'],
            'asset_ids.*' => ['integer', 'distinct', 'exists:assets,id'],
        ]);

        $assignment = $this->service->create($data, $request->user(), $request->input('action') === 'submit');

        return $this->respond($request, $assignment->status === RequestStatus::Draft
            ? "Draft {$assignment->request_no} tersimpan."
            : "{$assignment->request_no} berhasil diajukan dan menunggu approval manager penerima.", route('assignments.show', $assignment));
    }

    public function show(AssignmentRequest $assignment): View
    {
        $this->authorize('view', $assignment);

        $assignment->load([
            'requester', 'recipient.department', 'recipient.manager', 'location', 'assets.category', 'assets.location',
            'assignments.asset', 'assignments.assignedBy', 'assignments.attachments',
            'approvalRequest.steps.approver', 'approvalRequest.steps.decidedBy',
        ]);

        return view('assignments.show', [
            'assignment' => $assignment,
            'locations' => Location::active()->orderBy('name')->get(),
        ]);
    }

    public function submit(Request $request, AssignmentRequest $assignment): JsonResponse|RedirectResponse
    {
        $this->authorize('submit', $assignment);
        $this->service->submit($assignment, $request->user());

        return $this->respond($request, "{$assignment->request_no} berhasil diajukan.");
    }

    public function cancel(Request $request, AssignmentRequest $assignment): JsonResponse|RedirectResponse
    {
        $this->authorize('cancel', $assignment);
        $data = $request->validate(['cancel_reason' => ['nullable', 'string', 'max:1000']]);
        $this->service->cancel($assignment, $request->user(), $data['cancel_reason'] ?? null);

        return $this->respond($request, "{$assignment->request_no} dibatalkan.");
    }

    public function handover(Request $request, AssignmentRequest $assignment): JsonResponse|RedirectResponse
    {
        $this->authorize('handover', $assignment);

        $data = $request->validate([
            'assigned_at' => ['required', 'date', 'before_or_equal:now'],
            'condition_out' => ['required', Rule::enum(AssetCondition::class)],
            'handover_document_no' => ['nullable', 'string', 'max:50'],
            'handover_notes' => ['nullable', 'string', 'max:2000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => AttachmentService::rules(),
        ]);
        $this->service->handover($assignment, $data, $request->user());

        return $this->respond($request, 'Serah-terima berhasil dicatat. Aset kini berstatus Ditugaskan.');
    }

    public function returnAsset(Request $request, AssetAssignment $assetAssignment): JsonResponse|RedirectResponse
    {
        $this->authorize('return', $assetAssignment);

        $data = $request->validate([
            'returned_at' => ['required', 'date', 'before_or_equal:now'],
            'condition_in' => ['required', Rule::enum(AssetCondition::class)],
            'next_status' => ['required', Rule::in([AssetStatus::Available->value, AssetStatus::InRepair->value, AssetStatus::Lost->value])],
            'location_id' => ['required', Rule::exists('locations', 'id')->whereNull('deleted_at')],
            'return_notes' => ['nullable', 'string', 'max:2000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => AttachmentService::rules(),
        ]);
        $this->service->returnAsset($assetAssignment, $data, $request->user());

        return $this->respond($request, 'Pengembalian aset berhasil dicatat.');
    }
}
