@extends('layouts.app')
@section('title', 'Penugasan Aktif')
@section('subtitle', 'Aset yang saat ini dipegang oleh karyawan.')
@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span><a href="{{ route('assignments.index') }}" class="hover:text-on-surface">Penugasan</a>
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span><span class="text-on-surface font-semibold">Aktif</span>
@endsection
@section('actions')
    <a href="{{ route('assignments.index') }}" class="btn btn-secondary"><span class="material-symbols-outlined !text-[18px]">list</span> Daftar Permintaan</a>
@endsection
@section('content')
<x-card :padding="false">
    <form method="GET" class="flex flex-wrap items-end gap-space-md px-space-lg py-space-md border-b border-border-subtle">
        <div class="flex-1 min-w-[240px]">
            <label class="form-label">Pencarian</label>
            <input type="search" name="q" value="{{ request('q') }}" class="form-input" placeholder="Asset tag, nama aset, serial number, atau nama karyawan...">
        </div>
        <button class="btn btn-secondary"><span class="material-symbols-outlined !text-[18px]">search</span> Cari</button>
        @if (request('q'))<a href="{{ route('assignments.active') }}" class="btn btn-ghost">Reset</a>@endif
    </form>
    @if ($assignments->isEmpty())
        <x-empty icon="assignment_ind" message="Tidak ada penugasan aktif." />
    @else
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Aset</th><th>Kategori</th><th>Pemegang</th><th>Lokasi</th><th>Diserahkan</th><th>Kondisi</th><th>Permintaan</th><th></th></tr></thead>
                <tbody>
                @foreach ($assignments as $a)
                    <tr>
                        <td><span class="tag">{{ $a->asset->asset_tag }}</span><div class="text-body-sm mt-1">{{ $a->asset->name }}</div></td>
                        <td>{{ $a->asset->category?->name ?? '-' }}</td>
                        <td>{{ $a->employee?->name }}<div class="text-label-sm text-on-surface-variant">{{ $a->employee?->department?->name }}</div></td>
                        <td>{{ $a->location?->name ?? '-' }}</td>
                        <td>{{ $a->assigned_at->format('d M Y') }}</td>
                        <td>@if ($a->condition_out)<x-badge :enum="$a->condition_out" />@endif</td>
                        <td>
                            @if ($a->assignmentRequest)<a href="{{ route('assignments.show', $a->assignmentRequest) }}" class="text-secondary hover:underline">{{ $a->assignmentRequest->request_no }}</a>@else - @endif
                        </td>
                        <td class="text-right">
                            @can('return', $a)
                                <div x-data="{ open: false }">
                                    <button type="button" class="btn btn-secondary btn-sm" @click="open = true"><span class="material-symbols-outlined !text-[16px]">assignment_return</span> Kembalikan</button>
                                    @include('assignments._return-modal', [
                                        'action' => route('assignments.return', $a),
                                        'title' => 'Pengembalian '.$a->asset->asset_tag.' - '.$a->asset->name,
                                        'currentLocationId' => $a->location_id,
                                        'locationRequired' => true,
                                    ])
                                </div>
                            @endcan
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-space-lg py-space-md border-t border-border-subtle">{{ $assignments->links() }}</div>
    @endif
</x-card>
@endsection
