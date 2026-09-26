@use('App\Enums\AssetStatus')
@extends('layouts.app')

@section('title', 'Laporan')
@section('subtitle', 'Ringkasan portofolio aset, peminjaman, perbaikan, garansi, dan pengadaan')
@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span class="text-on-surface font-semibold">Laporan</span>
@endsection

@section('actions')
    <a href="{{ route('assets.export') }}" class="btn btn-secondary"><span class="material-symbols-outlined !text-[18px]">download</span> Ekspor Register Aset</a>
    <button type="button" onclick="window.print()" class="btn btn-primary"><span class="material-symbols-outlined !text-[18px]">print</span> Cetak</button>
@endsection

@section('content')
@php
    $money = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
    $totalUnits = (int) $byStatus->sum('total');
    $totalValue = (float) $byStatus->reject(fn ($r, $k) => $k === AssetStatus::Disposed->value)->sum('value');
    $catMax = max(1, (int) $byCategory->max('total'));
    $locMax = max(1, (int) $byLocation->max('total'));
@endphp

<div class="space-y-gutter">
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-gutter">
        <x-stat label="Total Aset" :value="number_format($totalUnits, 0, ',', '.')" icon="inventory_2" color="neutral" hint="Seluruh status" />
        <x-stat label="Nilai Aset Aktif" :value="$money($totalValue)" icon="payments" color="available" hint="Di luar aset dihapus" />
        <x-stat label="Pinjaman Terlambat" :value="$overdueLoans->count()" icon="schedule" color="disposal" />
        <x-stat label="Garansi Berakhir 60 Hari" :value="$warranty->count()" icon="verified_user" color="pending" />
    </div>

    <x-card title="Aset per Status" icon="donut_small" :padding="false">
        <table class="table">
            <thead><tr><th>Status</th><th class="text-right">Jumlah</th><th class="text-right">Porsi</th><th class="text-right">Nilai Perolehan</th></tr></thead>
            <tbody>
                @foreach (AssetStatus::cases() as $status)
                    @php $row = $byStatus->get($status->value); $t = (int) ($row->total ?? 0); @endphp
                    <tr>
                        <td><x-badge :enum="$status" /></td>
                        <td class="text-right font-mono">{{ $t }}</td>
                        <td class="text-right font-mono">{{ $totalUnits ? number_format($t / $totalUnits * 100, 1, ',', '.') : 0 }}%</td>
                        <td class="text-right font-mono">{{ $money($row->value ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-gutter">
        <x-card title="Aset per Kategori" icon="category" :padding="false">
            @if ($byCategory->isEmpty())
                <div class="p-space-lg"><x-empty icon="category" message="Belum ada data." /></div>
            @else
                <table class="table">
                    <thead><tr><th>Kategori</th><th class="w-1/3"></th><th class="text-right">Unit</th><th class="text-right">Nilai</th></tr></thead>
                    <tbody>
                        @foreach ($byCategory as $row)
                            <tr>
                                <td>{{ $row->name }}</td>
                                <td><div class="h-1.5 rounded-full bg-surface-subtle"><div class="h-1.5 rounded-full bg-status-assigned" style="width: {{ $row->total / $catMax * 100 }}%"></div></div></td>
                                <td class="text-right font-mono">{{ $row->total }}</td>
                                <td class="text-right font-mono whitespace-nowrap">{{ $money($row->value) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>

        <x-card title="Aset per Lokasi" icon="location_on" :padding="false">
            @if ($byLocation->isEmpty())
                <div class="p-space-lg"><x-empty icon="location_off" message="Belum ada data." /></div>
            @else
                <table class="table">
                    <thead><tr><th>Lokasi</th><th class="w-1/2"></th><th class="text-right">Unit</th></tr></thead>
                    <tbody>
                        @foreach ($byLocation as $row)
                            <tr>
                                <td>{{ $row->name }}</td>
                                <td><div class="h-1.5 rounded-full bg-surface-subtle"><div class="h-1.5 rounded-full bg-secondary-container" style="width: {{ $row->total / $locMax * 100 }}%"></div></div></td>
                                <td class="text-right font-mono">{{ $row->total }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>
    </div>

    <x-card title="Pinjaman Terlambat" icon="schedule" :padding="false">
        @if ($overdueLoans->isEmpty())
            <div class="p-space-lg"><x-empty icon="task_alt" message="Tidak ada pinjaman yang terlambat." /></div>
        @else
            <table class="table">
                <thead><tr><th>Asset Tag</th><th>Aset</th><th>Peminjam</th><th>Departemen</th><th>Jatuh Tempo</th><th>Terlambat</th><th></th></tr></thead>
                <tbody>
                    @foreach ($overdueLoans as $loan)
                        <tr>
                            <td class="tag">{{ $loan->asset?->asset_tag }}</td>
                            <td>{{ $loan->asset?->name }}</td>
                            <td>{{ $loan->borrower?->name ?? '-' }}</td>
                            <td>{{ $loan->borrower?->department?->name ?? '-' }}</td>
                            <td class="text-status-disposal whitespace-nowrap">{{ $loan->due_at?->format('d M Y') }}</td>
                            <td><x-badge color="disposal">{{ $loan->due_at?->diffForHumans(null, true) }}</x-badge></td>
                            <td class="text-right"><a href="{{ route('loans.show', $loan) }}" class="btn btn-ghost btn-sm">Detail</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-gutter">
        <x-card title="Aset Dalam Perbaikan" icon="build" :padding="false">
            @if ($inRepair->isEmpty())
                <div class="p-space-lg"><x-empty icon="build" message="Tidak ada aset dalam perbaikan." /></div>
            @else
                <table class="table">
                    <thead><tr><th>Asset Tag</th><th>Nama</th><th>Kategori</th><th>Lokasi</th></tr></thead>
                    <tbody>
                        @foreach ($inRepair as $asset)
                            <tr>
                                <td class="tag"><a href="{{ route('assets.show', $asset) }}" class="hover:underline">{{ $asset->asset_tag }}</a></td>
                                <td>{{ $asset->name }}</td>
                                <td>{{ $asset->category?->name ?? '-' }}</td>
                                <td>{{ $asset->location?->name ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>

        <x-card title="Garansi Akan Berakhir (60 Hari)" icon="verified_user" :padding="false">
            @if ($warranty->isEmpty())
                <div class="p-space-lg"><x-empty icon="verified_user" message="Tidak ada garansi yang akan berakhir." /></div>
            @else
                <table class="table">
                    <thead><tr><th>Asset Tag</th><th>Nama</th><th>Kategori</th><th>Berakhir</th></tr></thead>
                    <tbody>
                        @foreach ($warranty as $asset)
                            @php $end = $asset->warranty_end_date ? \Illuminate\Support\Carbon::parse($asset->warranty_end_date) : null; @endphp
                            <tr>
                                <td class="tag"><a href="{{ route('assets.show', $asset) }}" class="hover:underline">{{ $asset->asset_tag }}</a></td>
                                <td>{{ $asset->name }}</td>
                                <td>{{ $asset->category?->name ?? '-' }}</td>
                                <td class="whitespace-nowrap">
                                    {{ $end?->format('d M Y') }}
                                    @if ($end && $end->lte(today()->addDays(30)))<x-badge color="disposal">&le; 30 hari</x-badge>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>
    </div>

    <x-card title="Ringkasan Pengadaan" icon="shopping_cart" :padding="false">
        @if ($procurementSummary->isEmpty())
            <div class="p-space-lg"><x-empty icon="shopping_cart" message="Belum ada pengajuan pengadaan." /></div>
        @else
            <table class="table">
                <thead><tr><th>Status</th><th class="text-right">Jumlah Pengajuan</th><th class="text-right">Estimasi Total</th></tr></thead>
                <tbody>
                    @foreach ($procurementSummary as $row)
                        <tr>
                            <td><x-badge :enum="$row->status" /></td>
                            <td class="text-right font-mono">{{ $row->total }}</td>
                            <td class="text-right font-mono">{{ $money($row->value) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="font-semibold">
                        <td class="px-space-md py-space-sm">Total</td>
                        <td class="px-space-md py-space-sm text-right font-mono">{{ $procurementSummary->sum('total') }}</td>
                        <td class="px-space-md py-space-sm text-right font-mono">{{ $money($procurementSummary->sum('value')) }}</td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </x-card>
</div>
@endsection
