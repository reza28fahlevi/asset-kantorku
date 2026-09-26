@extends('layouts.app')

@section('title', 'Daftar Pengadaan')
@section('subtitle', 'Daftar permintaan pengadaan (procurement) beserta status approval, pemesanan, dan penerimaan.')

@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span class="text-on-surface font-semibold">Procurement</span>
@endsection

@section('actions')
    @can('create', \App\Models\ProcurementRequest::class)
        <a href="{{ route('procurements.create') }}" class="btn btn-primary">
            <span class="material-symbols-outlined !text-[18px]">add</span> Ajukan Pengadaan
        </a>
    @endcan
@endsection

@use('App\Enums\ProcurementStatus')
@php
    $currentStatus = request('status');
    $statusUrl = fn ($value) => request()->fullUrlWithQuery(['status' => $value, 'page' => null]);
    $inProcess = ($statusCounts[ProcurementStatus::Approved->value] ?? 0)
        + ($statusCounts[ProcurementStatus::Ordered->value] ?? 0)
        + ($statusCounts[ProcurementStatus::PartiallyReceived->value] ?? 0);
    $hasFilter = request()->hasAny(['q', 'status', 'from', 'to', 'department_id']);
@endphp

@section('content')
<div class="space-y-space-lg">
    {{-- KPI --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-space-md">
        <x-stat label="Total Permintaan" :value="number_format($totalCount, 0, ',', '.')" icon="shopping_cart" color="neutral" :href="$statusUrl(null)" hint="Sesuai cakupan & filter"/>
        <x-stat label="Menunggu Approval" :value="number_format($statusCounts[ProcurementStatus::PendingApproval->value] ?? 0, 0, ',', '.')" icon="hourglass_top" color="pending" :href="$statusUrl(ProcurementStatus::PendingApproval->value)"/>
        <x-stat label="Dalam Proses" :value="number_format($inProcess, 0, ',', '.')" icon="local_shipping" color="assigned" hint="Disetujui, dipesan, diterima sebagian"/>
        <x-stat label="Nilai Estimasi" :value="'Rp '.number_format($totalValue, 0, ',', '.')" icon="payments" color="available" hint="Total estimasi daftar saat ini"/>
    </div>

    {{-- Tab status --}}
    <div class="card px-space-md py-space-sm flex flex-wrap items-center gap-space-xs">
        <a href="{{ $statusUrl(null) }}" @class(['btn btn-sm', 'btn-primary' => ! $currentStatus, 'btn-ghost' => $currentStatus])>
            Semua <span class="tag">{{ $totalCount }}</span>
        </a>
        @foreach (ProcurementStatus::cases() as $s)
            <a href="{{ $statusUrl($s->value) }}" @class(['btn btn-sm', 'btn-primary' => $currentStatus === $s->value, 'btn-ghost' => $currentStatus !== $s->value])>
                {{ $s->label() }} <span class="tag">{{ $statusCounts[$s->value] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    <x-card title="Daftar Permintaan Pengadaan" icon="shopping_cart" :padding="false">
        <form method="GET" class="px-space-lg py-space-md border-b border-border-subtle flex flex-wrap items-end gap-space-sm">
            <div class="flex-1 min-w-[220px]">
                <label class="form-label" for="q">Cari</label>
                <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-input" placeholder="No. permintaan, judul, atau nama pemohon...">
            </div>
            <div class="w-48">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-input">
                    <option value="">Semua status</option>
                    @foreach (ProcurementStatus::cases() as $s)
                        <option value="{{ $s->value }}" @selected($currentStatus === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-52">
                <label class="form-label" for="department_id">Departemen</label>
                <select id="department_id" name="department_id" class="form-input">
                    <option value="">Semua departemen</option>
                    @foreach ($departments as $d)
                        <option value="{{ $d->id }}" @selected((string) request('department_id') === (string) $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-40">
                <label class="form-label" for="from">Dibuat dari</label>
                <input type="date" id="from" name="from" value="{{ request('from') }}" class="form-input">
            </div>
            <div class="w-40">
                <label class="form-label" for="to">Sampai</label>
                <input type="date" id="to" name="to" value="{{ request('to') }}" class="form-input">
            </div>
            <button type="submit" class="btn btn-secondary"><span class="material-symbols-outlined !text-[18px]">filter_list</span> Filter</button>
            @if ($hasFilter)
                <a href="{{ route('procurements.index') }}" class="btn btn-ghost"><span class="material-symbols-outlined !text-[18px]">filter_alt_off</span> Reset</a>
            @endif
        </form>

        @if ($requests->isEmpty())
            <x-empty icon="shopping_cart" :message="$hasFilter ? 'Tidak ada permintaan pengadaan yang sesuai filter.' : 'Belum ada permintaan pengadaan.'">
                @can('create', \App\Models\ProcurementRequest::class)
                    <a href="{{ route('procurements.create') }}" class="btn btn-primary btn-sm mt-space-md"><span class="material-symbols-outlined !text-[18px]">add</span> Ajukan Pengadaan</a>
                @endcan
            </x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No. Permintaan</th>
                            <th>Judul</th>
                            <th>Pemohon / Departemen</th>
                            <th class="text-right">Item / Qty</th>
                            <th class="text-right">Estimasi Total</th>
                            <th>Dibutuhkan</th>
                            <th>Status</th>
                            <th>Dibuat</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $row)
                            <tr>
                                <td><a href="{{ route('procurements.show', $row) }}" class="font-mono font-semibold text-secondary hover:underline whitespace-nowrap">{{ $row->request_no }}</a></td>
                                <td class="max-w-[280px]">
                                    <a href="{{ route('procurements.show', $row) }}" class="font-semibold hover:underline line-clamp-2">{{ $row->title }}</a>
                                    @if ($row->po_number)<p class="text-label-sm font-normal text-outline">PO {{ $row->po_number }}</p>@endif
                                </td>
                                <td>
                                    <p>{{ $row->requester?->name ?? '-' }}</p>
                                    <p class="text-label-sm font-normal text-outline">{{ $row->department?->name ?? '-' }}</p>
                                </td>
                                <td class="text-right whitespace-nowrap">{{ $row->items_count }} / {{ (int) $row->items_sum_quantity }} unit</td>
                                <td class="text-right font-mono whitespace-nowrap">Rp {{ number_format((float) $row->estimated_total, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap">{{ $row->needed_by?->format('d M Y') ?? '-' }}</td>
                                <td><x-badge :enum="$row->status"/></td>
                                <td class="whitespace-nowrap text-on-surface-variant">{{ $row->created_at?->format('d M Y') }}</td>
                                <td class="text-right whitespace-nowrap">
                                    @if ($row->status === ProcurementStatus::Draft)
                                        @can('update', $row)
                                            <a href="{{ route('procurements.edit', $row) }}" class="btn btn-secondary btn-sm">Lengkapi Draft</a>
                                        @endcan
                                    @endif
                                    <a href="{{ route('procurements.show', $row) }}" class="btn btn-ghost btn-sm">Detail</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-space-lg py-space-md border-t border-border-subtle">{{ $requests->links() }}</div>
        @endif
    </x-card>
</div>
@endsection
