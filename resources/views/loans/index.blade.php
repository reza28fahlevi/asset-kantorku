@extends('layouts.app')
@section('title', 'Daftar Pengajuan Peminjaman')
@section('subtitle', 'Daftar permintaan peminjaman aset sementara beserta status approval.')
@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span><span class="text-on-surface font-semibold">Peminjaman</span>
@endsection
@section('actions')
    <a href="{{ route('loans.active') }}" class="btn btn-secondary"><span class="material-symbols-outlined !text-[18px]">schedule</span> Peminjaman Aktif</a>
    @can('create', App\Models\AssetLoanRequest::class)
        <a href="{{ route('loans.create') }}" class="btn btn-primary"><span class="material-symbols-outlined !text-[18px]">add</span> Ajukan Peminjaman</a>
    @endcan
@endsection

@use('App\Enums\RequestStatus')
@php
    $currentStatus = request('status');
    $statusUrl = fn ($value) => request()->fullUrlWithQuery(['status' => $value, 'page' => null]);
    $hasFilter = request()->hasAny(['q', 'status', 'from', 'to', 'department_id']);
@endphp

@section('content')
{{-- data-ajax-region: tab status, KPI, filter & pagination hanya memperbarui area ini via AJAX --}}
<div id="loan-list" data-ajax-region class="space-y-space-lg">
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-space-md">
        <x-stat label="Total Pengajuan" :value="number_format($totalCount, 0, ',', '.')" icon="schedule" color="neutral" :href="$statusUrl(null)" hint="Sesuai cakupan & filter"/>
        <x-stat label="Menunggu Approval" :value="number_format($statusCounts[RequestStatus::PendingApproval->value] ?? 0, 0, ',', '.')" icon="hourglass_top" color="pending" :href="$statusUrl(RequestStatus::PendingApproval->value)"/>
        <x-stat label="Siap Diserahkan" :value="number_format($statusCounts[RequestStatus::Approved->value] ?? 0, 0, ',', '.')" icon="handshake" color="loan" :href="$statusUrl(RequestStatus::Approved->value)" hint="Disetujui, menunggu serah terima"/>
        <x-stat label="Terpenuhi" :value="number_format($statusCounts[RequestStatus::Fulfilled->value] ?? 0, 0, ',', '.')" icon="task_alt" color="available" :href="$statusUrl(RequestStatus::Fulfilled->value)"/>
    </div>

    <div class="card px-space-md py-space-sm flex flex-wrap items-center gap-space-xs">
        <a href="{{ $statusUrl(null) }}" @class(['btn btn-sm', 'btn-primary' => ! $currentStatus, 'btn-ghost' => $currentStatus])>
            Semua <span class="tag">{{ $totalCount }}</span>
        </a>
        @foreach (RequestStatus::cases() as $s)
            <a href="{{ $statusUrl($s->value) }}" @class(['btn btn-sm', 'btn-primary' => $currentStatus === $s->value, 'btn-ghost' => $currentStatus !== $s->value])>
                {{ $s->label() }} <span class="tag">{{ $statusCounts[$s->value] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    <x-card title="Daftar Pengajuan Peminjaman" icon="schedule" :padding="false">
        <form method="GET" action="{{ route('loans.index') }}" data-auto-submit class="flex flex-wrap items-end gap-space-sm px-space-lg py-space-md border-b border-border-subtle">
            <div class="flex-1 min-w-[220px]">
                <label class="form-label" for="q">Cari</label>
                <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-input" placeholder="No. permintaan, pemohon, peminjam, tag aset...">
            </div>
            <div class="w-48">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-input">
                    <option value="">Semua status</option>
                    @foreach (RequestStatus::cases() as $s)
                        <option value="{{ $s->value }}" @selected($currentStatus === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-52">
                <label class="form-label" for="department_id">Departemen Peminjam</label>
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
            @if ($hasFilter)<a href="{{ route('loans.index') }}" class="btn btn-ghost"><span class="material-symbols-outlined !text-[18px]">filter_alt_off</span> Reset</a>@endif
        </form>

        @if ($requests->isEmpty())
            <x-empty icon="schedule" :message="$hasFilter ? 'Tidak ada pengajuan peminjaman yang sesuai filter.' : 'Belum ada pengajuan peminjaman.'">
                @can('create', App\Models\AssetLoanRequest::class)
                    <a href="{{ route('loans.create') }}" class="btn btn-primary btn-sm mt-space-md"><span class="material-symbols-outlined !text-[18px]">add</span> Ajukan Peminjaman</a>
                @endcan
            </x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No. Permintaan</th>
                            <th>Tujuan</th>
                            <th>Peminjam / Departemen</th>
                            <th>Pemohon</th>
                            <th>Lokasi Pakai</th>
                            <th class="text-center">Jml Aset</th>
                            <th>Periode</th>
                            <th>Status</th>
                            <th>Dibuat</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($requests as $r)
                        <tr>
                            <td><a href="{{ route('loans.show', $r) }}" class="font-mono font-semibold text-secondary hover:underline whitespace-nowrap">{{ $r->request_no }}</a></td>
                            <td class="max-w-[260px]"><p class="line-clamp-2">{{ \Illuminate\Support\Str::limit($r->purpose, 120) }}</p></td>
                            <td>
                                <p>{{ $r->borrower?->name ?? '-' }}</p>
                                <p class="text-label-sm font-normal text-outline">{{ $r->borrower?->department?->name ?? '-' }}</p>
                            </td>
                            <td>{{ $r->requester?->name ?? '-' }}</td>
                            <td>{{ $r->usageLocation?->name ?? '-' }}</td>
                            <td class="text-center">{{ $r->assets_count }}</td>
                            <td class="whitespace-nowrap">
                                <p>{{ $r->start_date?->format('d M Y') }}</p>
                                <p class="text-label-sm font-normal text-outline">s.d. {{ $r->due_date?->format('d M Y') }}</p>
                            </td>
                            <td><x-badge :enum="$r->status" /></td>
                            <td class="whitespace-nowrap text-on-surface-variant">{{ $r->created_at?->format('d M Y') }}</td>
                            <td class="text-right"><a href="{{ route('loans.show', $r) }}" class="btn btn-ghost btn-sm">Detail</a></td>
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
