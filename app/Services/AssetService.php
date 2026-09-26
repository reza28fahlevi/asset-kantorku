<?php

namespace App\Services;

use App\Enums\AssetCondition;
use App\Enums\AssetEventType;
use App\Enums\AssetStatus;
use App\Enums\RequestStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetEvent;
use App\Models\AssetLoanRequest;
use App\Models\AssignmentRequest;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssetService
{
    public function __construct(private NumberGenerator $numbers)
    {
    }

    /** Catat kejadian domain pada timeline aset (append-only). */
    public function recordEvent(Asset $asset, AssetEventType $type, array $attributes = []): AssetEvent
    {
        $user = Auth::user();

        return AssetEvent::create(array_merge([
            'asset_id' => $asset->id,
            'event_type' => $type,
            'performed_by_user_id' => $user?->id,
            'performed_by_employee_id' => $user?->employee_id,
            'occurred_at' => now(),
        ], $attributes));
    }

    /**
     * Ubah status aset + catat event dalam satu langkah.
     *
     * @param  array  $assetChanges  kolom aset lain yang ikut berubah (location_id, condition, ...)
     * @param  array  $eventAttributes  atribut tambahan event (notes, reference, related_employee_id, ...)
     */
    public function transition(Asset $asset, AssetStatus $to, AssetEventType $type, array $assetChanges = [], array $eventAttributes = []): void
    {
        $from = $asset->status;
        $fromLocation = $asset->location_id;

        $asset->fill($assetChanges);
        $asset->status = $to;
        $asset->save();

        $this->recordEvent($asset, $type, array_merge([
            'from_status' => $from,
            'to_status' => $to,
            'from_location_id' => $fromLocation,
            'to_location_id' => $asset->location_id,
        ], $eventAttributes));
    }

    /**
     * Kunci baris aset (SELECT ... FOR UPDATE) dan pastikan semuanya berstatus AVAILABLE.
     *
     * @param  array<int>  $assetIds
     * @return Collection<int, Asset>
     */
    public function lockAvailable(array $assetIds): Collection
    {
        $assets = Asset::query()->whereKey($assetIds)->orderBy('id')->lockForUpdate()->get();

        if ($assets->count() !== count(array_unique($assetIds))) {
            throw new BusinessRuleException('Sebagian aset tidak ditemukan.');
        }

        $unavailable = $assets->reject(fn (Asset $a) => $a->status === AssetStatus::Available);
        if ($unavailable->isNotEmpty()) {
            throw new BusinessRuleException('Aset tidak tersedia: '.$unavailable->map(
                fn (Asset $a) => "{$a->asset_tag} ({$a->status->label()})"
            )->implode(', ').'. Silakan pilih aset lain.');
        }

        return $assets;
    }

    /**
     * Pastikan aset tidak sedang dipesan oleh permintaan assignment/loan lain yang masih terbuka
     * (PENDING_APPROVAL / APPROVED) pada rentang tanggal yang bertabrakan.
     *
     * @param  array<int>  $assetIds
     * @param  CarbonInterface|null  $to  null = tanpa batas akhir (assignment)
     */
    public function ensureNoConflictingRequests(array $assetIds, CarbonInterface $from, ?CarbonInterface $to, ?Model $exclude = null): void
    {
        $open = [RequestStatus::PendingApproval->value, RequestStatus::Approved->value];
        $conflicts = [];

        $assignments = AssignmentRequest::query()
            ->whereIn('status', $open)
            ->when($exclude instanceof AssignmentRequest, fn ($q) => $q->whereKeyNot($exclude->id))
            ->when($to, fn ($q) => $q->whereDate('start_date', '<=', $to))
            ->whereHas('assets', fn ($q) => $q->whereIn('assets.id', $assetIds))
            ->with(['assets' => fn ($q) => $q->whereIn('assets.id', $assetIds)])
            ->get();
        foreach ($assignments as $request) {
            foreach ($request->assets as $asset) {
                $conflicts[] = "{$asset->asset_tag} → {$request->request_no}";
            }
        }

        $loans = AssetLoanRequest::query()
            ->whereIn('status', $open)
            ->when($exclude instanceof AssetLoanRequest, fn ($q) => $q->whereKeyNot($exclude->id))
            ->whereDate('due_date', '>=', $from)
            ->when($to, fn ($q) => $q->whereDate('start_date', '<=', $to))
            ->whereHas('assets', fn ($q) => $q->whereIn('assets.id', $assetIds))
            ->with(['assets' => fn ($q) => $q->whereIn('assets.id', $assetIds)])
            ->get();
        foreach ($loans as $request) {
            foreach ($request->assets as $asset) {
                $conflicts[] = "{$asset->asset_tag} → {$request->request_no}";
            }
        }

        if ($conflicts !== []) {
            throw new BusinessRuleException('Aset sudah dipesan oleh permintaan lain pada periode yang sama: '.implode(', ', $conflicts));
        }
    }

    /** Registrasi aset manual (di luar procurement, mis. data migrasi). */
    public function register(array $data): Asset
    {
        return DB::transaction(function () use ($data) {
            $category = AssetCategory::findOrFail($data['asset_category_id']);
            if ($category->requires_serial && blank($data['serial_number'] ?? null)) {
                throw new BusinessRuleException("Nomor seri wajib diisi untuk kategori {$category->name}.");
            }

            $asset = Asset::create(array_merge($data, [
                'asset_tag' => $this->numbers->assetTag($category->code),
                'status' => AssetStatus::Available,
                'condition' => $data['condition'] ?? AssetCondition::Good->value,
            ]));

            $this->recordEvent($asset, AssetEventType::Registered, [
                'to_status' => $asset->status,
                'to_location_id' => $asset->location_id,
                'notes' => 'Registrasi manual',
            ]);

            return $asset;
        });
    }

    /** Ubah data deskriptif aset. Perubahan lokasi dicatat sebagai event tersendiri. */
    public function update(Asset $asset, array $data): Asset
    {
        if ($asset->isDisposed()) {
            throw new BusinessRuleException('Aset yang sudah dihapus tidak dapat diubah.');
        }

        return DB::transaction(function () use ($asset, $data) {
            $fromLocation = $asset->location_id;
            $asset->fill($data);
            $dirty = array_keys($asset->getDirty());
            $asset->save();

            if (in_array('location_id', $dirty, true)) {
                $this->recordEvent($asset, AssetEventType::LocationChanged, [
                    'from_location_id' => $fromLocation,
                    'to_location_id' => $asset->location_id,
                ]);
            }
            $other = array_diff($dirty, ['location_id']);
            if ($other !== []) {
                $this->recordEvent($asset, AssetEventType::Updated, ['metadata' => ['fields' => array_values($other)]]);
            }

            return $asset;
        });
    }

    /**
     * Perubahan status operasional oleh admin: perbaikan, selesai perbaikan, hilang.
     */
    public function changeOperationalStatus(Asset $asset, string $action, ?string $notes, ?string $condition = null): void
    {
        DB::transaction(function () use ($asset, $action, $notes, $condition) {
            $asset = Asset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $changes = $condition ? ['condition' => $condition] : [];

            [$allowed, $to, $event, $verb] = match ($action) {
                'repair' => [[AssetStatus::Available], AssetStatus::InRepair, AssetEventType::RepairStarted, 'dimasukkan ke perbaikan'],
                'repaired' => [[AssetStatus::InRepair], AssetStatus::Available, AssetEventType::RepairFinished, 'diselesaikan perbaikannya'],
                'lost' => [[AssetStatus::Available, AssetStatus::InRepair], AssetStatus::Lost, AssetEventType::MarkedLost, 'dilaporkan hilang'],
                'found' => [[AssetStatus::Lost], AssetStatus::Available, AssetEventType::StatusChanged, 'dikembalikan ke tersedia'],
                default => throw new BusinessRuleException('Aksi status tidak dikenal.'),
            };

            if (! in_array($asset->status, $allowed, true)) {
                throw new BusinessRuleException(
                    "Aset {$asset->asset_tag} berstatus {$asset->status->label()} sehingga tidak dapat {$verb}. "
                    .'Aset yang ditugaskan/dipinjam harus dikembalikan melalui proses pengembalian.'
                );
            }

            $this->transition($asset, $to, $event, $changes, ['notes' => $notes]);
        });
    }
}
