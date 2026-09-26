<?php

namespace App\Http\Controllers;

use App\Enums\AssetCondition;
use App\Enums\ProcurementStatus;
use App\Models\AssetCategory;
use App\Models\Department;
use App\Models\Location;
use App\Models\ProcurementRequest;
use App\Models\Vendor;
use App\Services\AttachmentService;
use App\Services\ProcurementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProcurementController extends Controller
{
    public function __construct(private ProcurementService $service)
    {
    }

    public function index(Request $request): View
    {
        $requests = ProcurementRequest::query()
            ->visibleTo($request->user())
            ->with('requester', 'department')
            ->withSum('items', 'quantity')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w
                ->where('request_no', 'ilike', "%{$t}%")->orWhere('title', 'ilike', "%{$t}%")))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('procurements.index', ['requests' => $requests]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', ProcurementRequest::class);

        return view('procurements.create', [
            'categories' => AssetCategory::active()->orderBy('name')->get(),
            'departments' => Department::active()->orderBy('name')->get(),
            'employee' => $request->user()->employee,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ProcurementRequest::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'justification' => ['required', 'string', 'max:5000'],
            'department_id' => ['required', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'needed_by' => ['nullable', 'date', 'after_or_equal:today'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.asset_category_id' => ['required', Rule::exists('asset_categories', 'id')->whereNull('deleted_at')],
            'items.*.item_name' => ['required', 'string', 'max:200'],
            'items.*.specification' => ['nullable', 'string', 'max:2000'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'items.*.estimated_unit_price' => ['required', 'numeric', 'min:0'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => AttachmentService::rules(),
        ]);

        $procurement = $this->service->create($data, $request->user(), $request->input('action') === 'submit');

        return redirect()->route('procurements.show', $procurement)->with('success', $procurement->status === ProcurementStatus::Draft
            ? "Draft {$procurement->request_no} tersimpan."
            : "{$procurement->request_no} berhasil diajukan dan menunggu approval.");
    }

    public function show(ProcurementRequest $procurement): View
    {
        $this->authorize('view', $procurement);

        $procurement->load([
            'requester', 'department', 'vendor', 'orderedBy', 'items.category', 'items.assets',
            'receipts.receivedBy', 'receipts.items.requestItem', 'receipts.attachments',
            'approvalRequest.steps.approver', 'approvalRequest.steps.decidedBy', 'attachments',
        ]);

        return view('procurements.show', [
            'procurement' => $procurement,
            'vendors' => Vendor::active()->orderBy('name')->get(),
            'locations' => Location::active()->orderBy('name')->get(),
        ]);
    }

    public function submit(Request $request, ProcurementRequest $procurement): RedirectResponse
    {
        $this->authorize('submit', $procurement);
        $this->service->submit($procurement, $request->user());

        return back()->with('success', "{$procurement->request_no} berhasil diajukan.");
    }

    public function cancel(ProcurementRequest $procurement): RedirectResponse
    {
        $this->authorize('cancel', $procurement);
        $this->service->cancel($procurement);

        return back()->with('success', "{$procurement->request_no} dibatalkan.");
    }

    public function order(Request $request, ProcurementRequest $procurement): RedirectResponse
    {
        $this->authorize('order', $procurement);

        $data = $request->validate([
            'vendor_id' => ['required', Rule::exists('vendors', 'id')->whereNull('deleted_at')],
            'po_number' => ['required', 'string', 'max:50'],
            'ordered_at' => ['required', 'date', 'before_or_equal:now'],
        ]);
        $this->service->order($procurement, $data);

        return back()->with('success', 'Pemesanan berhasil dicatat.');
    }

    public function receiveForm(ProcurementRequest $procurement): View
    {
        $this->authorize('receive', $procurement);

        return view('procurements.receive', [
            'procurement' => $procurement->load('items.category', 'vendor'),
            'locations' => Location::active()->orderBy('name')->get(),
        ]);
    }

    public function receive(Request $request, ProcurementRequest $procurement): RedirectResponse
    {
        $this->authorize('receive', $procurement);

        $data = $request->validate([
            'received_at' => ['required', 'date', 'before_or_equal:now'],
            'delivery_note_no' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array'],
            'items.*.accepted' => ['nullable', 'integer', 'min:0'],
            'items.*.rejected' => ['nullable', 'integer', 'min:0'],
            'items.*.exception_notes' => ['nullable', 'string', 'max:1000'],
            'items.*.serial_numbers' => ['nullable', 'string', 'max:10000'],
            'items.*.location_id' => ['required_with:items.*.accepted', 'nullable', Rule::exists('locations', 'id')->whereNull('deleted_at')],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.warranty_end_date' => ['nullable', 'date'],
            'items.*.condition' => ['nullable', Rule::enum(AssetCondition::class)],
            'items.*.brand' => ['nullable', 'string', 'max:100'],
            'items.*.model' => ['nullable', 'string', 'max:100'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => AttachmentService::rules(),
        ]);

        $receipt = $this->service->receive($procurement, $data, $request->user());

        return redirect()->route('procurements.show', $procurement)
            ->with('success', "Penerimaan {$receipt->receipt_no} tercatat; {$receipt->assets()->count()} aset baru teregistrasi.");
    }

    public function close(Request $request, ProcurementRequest $procurement): RedirectResponse
    {
        $this->authorize('close', $procurement);
        $data = $request->validate(['closing_note' => ['required', 'string', 'max:2000']]);
        $this->service->close($procurement, $data['closing_note']);

        return back()->with('success', 'Procurement ditutup.');
    }
}
