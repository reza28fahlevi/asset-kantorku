<?php

namespace App\Http\Controllers;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Department;
use App\Models\Location;
use App\Models\Vendor;
use App\Services\AssetExporter;
use App\Services\AssetService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetController extends Controller
{
    public function __construct(private AssetService $assets)
    {
    }

    public function index(Request $request): View
    {
        $assets = $this->filtered($request)
            ->with('category', 'location', 'activeAssignment.employee', 'activeLoan.borrower')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('assets.index', [
            'assets' => $assets,
            'categories' => AssetCategory::orderBy('name')->get(),
            'locations' => Location::orderBy('name')->get(),
        ]);
    }

    /** Export register aset (?format=csv|xlsx|pdf) sesuai filter & hak akses. */
    public function export(Request $request): Response|StreamedResponse
    {
        abort_unless($request->user()->hasPermission('report.view'), 403);
        $format = $request->validate(['format' => ['nullable', Rule::in(['csv', 'xlsx', 'pdf'])]])['format'] ?? 'csv';

        // Ringkasan filter aktif untuk judul dokumen
        $filters = array_filter([
            $request->query('q') ? 'Cari "'.$request->query('q').'"' : null,
            $request->query('status') ? 'Status '.(AssetStatus::tryFrom($request->query('status'))?->label() ?? $request->query('status')) : null,
            $request->query('category_id') ? 'Kategori '.AssetCategory::find($request->query('category_id'))?->name : null,
            $request->query('location_id') ? 'Lokasi '.Location::find($request->query('location_id'))?->name : null,
        ]);

        return (new AssetExporter($this->filtered($request), array_values($filters)))->download($format);
    }

    public function show(Asset $asset): View
    {
        $asset->load([
            'category', 'location', 'department', 'vendor', 'procurementItem.procurementRequest',
            'activeAssignment.employee', 'activeLoan.borrower', 'activeLoan.loanRequest',
            'events.performedBy', 'events.relatedEmployee', 'events.fromLocation', 'events.toLocation',
            'assignments.employee', 'loans.borrower', 'disposalRequests', 'attachments',
        ]);

        return view('assets.show', [
            'asset' => $asset,
            'locations' => Location::active()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Asset(['condition' => AssetCondition::Good]));
    }

    public function store(Request $request): RedirectResponse
    {
        $asset = $this->assets->register($this->validated($request, null));

        return redirect()->route('assets.show', $asset)->with('success', "Aset {$asset->asset_tag} berhasil diregistrasi.");
    }

    public function edit(Asset $asset): View
    {
        abort_if($asset->isDisposed(), 403, 'Aset yang sudah dihapus tidak dapat diubah.');

        return $this->form($asset);
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $this->assets->update($asset, $this->validated($request, $asset));

        return redirect()->route('assets.show', $asset)->with('success', 'Data aset berhasil diperbarui.');
    }

    /** Aksi status operasional: repair | repaired | lost | found */
    public function changeStatus(Request $request, Asset $asset): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['repair', 'repaired', 'lost', 'found'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'condition' => ['nullable', Rule::enum(AssetCondition::class)],
        ]);

        $this->assets->changeOperationalStatus($asset, $data['action'], $data['notes'] ?? null, $data['condition'] ?? null);

        return back()->with('success', 'Status aset berhasil diperbarui.');
    }

    /** Pencarian aset tersedia (AJAX) untuk form assignment/peminjaman/disposal. */
    public function search(Request $request)
    {
        $statuses = $request->query('scope') === 'disposable'
            ? [AssetStatus::Available->value, AssetStatus::InRepair->value, AssetStatus::Lost->value]
            : [AssetStatus::Available->value];

        $assets = Asset::query()
            ->with('category', 'location')
            ->whereIn('status', $statuses)
            ->search($request->query('q'))
            ->when($request->query('category_id'), fn ($q, $c) => $q->where('asset_category_id', $c))
            ->orderBy('asset_tag')
            ->limit(30)
            ->get()
            ->map(fn (Asset $a) => [
                'id' => $a->id,
                'asset_tag' => $a->asset_tag,
                'name' => $a->name,
                'serial_number' => $a->serial_number,
                'category' => $a->category->name,
                'location' => $a->location->name,
                'status' => $a->status->label(),
            ]);

        return response()->json(['data' => $assets]);
    }

    private function filtered(Request $request): Builder
    {
        return Asset::query()
            ->search($request->query('q'))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('category_id'), fn ($q, $c) => $q->where('asset_category_id', $c))
            ->when($request->query('location_id'), fn ($q, $l) => $q->where('location_id', $l));
    }

    private function form(Asset $asset): View
    {
        return view('assets.form', [
            'asset' => $asset,
            'categories' => AssetCategory::active()->orderBy('name')->get(),
            'locations' => Location::active()->orderBy('name')->get(),
            'departments' => Department::active()->orderBy('name')->get(),
            'vendors' => Vendor::active()->orderBy('name')->get(),
        ]);
    }

    private function validated(Request $request, ?Asset $asset): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:200'],
            'location_id' => ['required', Rule::exists('locations', 'id')->whereNull('deleted_at')],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'vendor_id' => ['nullable', Rule::exists('vendors', 'id')->whereNull('deleted_at')],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100', Rule::unique('assets')->ignore($asset)],
            'specification' => ['nullable', 'string', 'max:2000'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'warranty_end_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        // Kategori & kondisi awal hanya saat registrasi; kondisi berikutnya berubah lewat transaksi
        if (! $asset) {
            $rules['asset_category_id'] = ['required', Rule::exists('asset_categories', 'id')->whereNull('deleted_at')];
            $rules['condition'] = ['required', Rule::enum(AssetCondition::class)];
        }

        return $request->validate($rules);
    }
}
