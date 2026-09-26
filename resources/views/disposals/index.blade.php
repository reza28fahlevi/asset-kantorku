@extends('layouts.app')

@section('title', 'Daftar Disposal')
@section('subtitle', 'Pengajuan penghapusan aset dari daftar aktif perusahaan')
@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span class="text-on-surface font-semibold">Disposal</span>
@endsection

@section('actions')
    @can('create', \App\Models\DisposalRequest::class)
        <a href="{{ route('disposals.create') }}" class="btn btn-primary">
            <span class="material-symbols-outlined !text-[18px]">add</span> Ajukan Disposal
        </a>
    @endcan
@endsection

@use('App\Enums\DisposalStatus')
@php
    $currentStatus = request('status');
    $statusUrl = fn ($value) => request()->fullUrlWithQuery(['status' => $value, 'page' => null]);
    $hasFilter = request()->hasAny(['q', 'status', 'from', 'to', 'department_id']);
@endphp

@section('content')
{{-- data-ajax-region: tab status, KPI, filter & pagination hanya memperbarui area ini via AJAX --}}
<div id="disposal-list" data-ajax-region class="space-y-space-lg">
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-space-md">
        <x-stat label="Total Pengajuan" :value="number_format($totalCount, 0, ',', '.')" icon="delete_sweep" color="neutral" :href="$statusUrl(null)" hint="Sesuai cakupan & filter"/>
        <x-stat label="Menunggu Approval" :value="number_format($statusCounts[DisposalStatus::PendingApproval->value] ?? 0, 0, ',', '.')" icon="hourglass_top" color="pending" :href="$statusUrl(DisposalStatus::PendingApproval->value)"/>
        <x-stat label="Siap Dieksekusi" :value="number_format($statusCounts[DisposalStatus::Approved->value] ?? 0, 0, ',', '.')" icon="gavel" color="disposal" :href="$statusUrl(DisposalStatus::Approved->value)" hint="Disetujui, menunggu eksekusi"/>
        <x-stat label="Selesai" :value="number_format($statusCounts[DisposalStatus::Completed->value] ?? 0, 0, ',', '.')" icon="task_alt" color="available" :href="$statusUrl(DisposalStatus::Completed->value)"/>
    </div>

    <div class="card px-space-md py-space-sm flex flex-wrap items-center gap-space-xs">
        <a href="{{ $statusUrl(null) }}" @class(['btn btn-sm', 'btn-primary' => ! $currentStatus, 'btn-ghost' => $currentStatus])>
            Semua <span class="tag">{{ $totalCount }}</span>
        </a>
        @foreach (DisposalStatus::cases() as $s)
            <a href="{{ $statusUrl($s->value) }}" @class(['btn btn-sm', 'btn-primary' => $currentStatus === $s->value, 'btn-ghost' => $currentStatus !== $s->value])>
                {{ $s->label() }} <span class="tag">{{ $statusCounts[$s->value] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    <x-card title="Daftar Pengajuan Disposal" icon="delete_sweep" :padding="false">
        <form method="GET" action="{{ route('disposals.index') }}" data-auto-submit class="flex flex-wrap items-end gap-space-sm px-space-lg py-space-md border-b border-border-subtle">
            <div class="flex-1 min-w-[220px]">
                <label class="form-label" for="q">Cari</label>
                <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-input" placeholder="No. permintaan, tag / nama aset, pemohon...">
            </div>
            <div class="w-48">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-input">
                    <option value="">Semua status</option>
                    @foreach (DisposalStatus::cases() as $s)
                        <option value="{{ $s->value }}" @selected($currentStatus === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-52">
                <label class="form-label" for="department_id">Departemen Aset</label>
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
            <button class="btn btn-secondary"><span class="material-symbols-outlined !text-[18px]">filter_list</span> Filter</button>
            @if ($hasFilter)
                <a href="{{ route('disposals.index') }}" class="btn btn-ghost"><span class="material-symbols-outlined !text-[18px]">filter_alt_off</span> Reset</a>
            @endif
        </form>

        @if ($requests->isEmpty())
            <x-empty icon="delete_sweep" :message="$hasFilter ? 'Tidak ada pengajuan disposal yang sesuai filter.' : 'Belum ada pengajuan disposal.'">
                @can('create', \App\Models\DisposalRequest::class)
                    <a href="{{ route('disposals.create') }}" class="btn btn-primary btn-sm mt-space-md"><span class="material-symbols-outlined !text-[18px]">add</span> Ajukan Disposal</a>
                @endcan
            </x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No. Permintaan</th>
                            <th>Aset</th>
                            <th>Departemen</th>
                            <th>Alasan</th>
                            <th>Metode</th>
                            <th>Pemohon</th>
                            <th>Eksekusi</th>
                            <th class="text-right">Hasil</th>
                            <th>Status</th>
                            <th>Dibuat</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $r)
                            <tr>
                                <td><a href="{{ route('disposals.show', $r) }}" class="font-mono font-semibold text-secondary hover:underline whitespace-nowrap">{{ $r->request_no }}</a></td>
                                <td>
                                    <span class="tag">{{ $r->asset?->asset_tag }}</span>
                                    <div class="text-body-sm">{{ $r->asset?->name }}</div>
                                    <div class="text-label-sm text-on-surface-variant">{{ $r->asset?->category?->name }}</div>
                                </td>
                                <td>{{ $r->asset?->department?->name ?? '-' }}</td>
                                <td><x-badge :enum="$r->reason_type" /></td>
                                <td>
                                    <p>{{ $r->planned_method?->label() }}</p>
                                    @if ($r->actual_method && $r->actual_method !== $r->planned_method)
                                        <p class="text-label-sm font-normal text-outline">Aktual: {{ $r->actual_method->label() }}</p>
                                    @endif
                                </td>
                                <td>{{ $r->requester?->name ?? '-' }}</td>
                                <td class="whitespace-nowrap">{{ $r->executed_at?->format('d M Y') ?? '-' }}</td>
                                <td class="text-right font-mono whitespace-nowrap">{{ $r->proceeds_amount !== null ? 'Rp '.number_format((float) $r->proceeds_amount, 0, ',', '.') : '-' }}</td>
                                <td><x-badge :enum="$r->status" /></td>
                                <td class="whitespace-nowrap text-on-surface-variant">{{ $r->created_at?->format('d M Y') }}</td>
                                <td class="text-right">
                                    <a href="{{ route('disposals.show', $r) }}" class="btn btn-ghost btn-sm">Detail</a>
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
