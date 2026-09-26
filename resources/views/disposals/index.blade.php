@extends('layouts.app')

@section('title', 'Disposal Aset')
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

@section('content')
<div class="card">
    <form method="GET" class="flex flex-wrap items-end gap-space-md px-space-lg py-space-md border-b border-border-subtle">
        <div class="flex-1 min-w-[220px]">
            <label class="form-label">Cari</label>
            <input type="text" name="q" value="{{ request('q') }}" class="form-input" placeholder="No. permintaan / tag / nama aset">
        </div>
        <div class="w-56">
            <label class="form-label">Status</label>
            <select name="status" class="form-input">
                <option value="">Semua status</option>
                @foreach (\App\Enums\DisposalStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-secondary"><span class="material-symbols-outlined !text-[18px]">filter_list</span> Filter</button>
        @if (request()->hasAny(['q', 'status']))
            <a href="{{ route('disposals.index') }}" class="btn btn-ghost">Reset</a>
        @endif
    </form>

    @if ($requests->isEmpty())
        <x-empty icon="delete_sweep" message="Belum ada pengajuan disposal." />
    @else
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>No. Permintaan</th>
                        <th>Aset</th>
                        <th>Alasan</th>
                        <th>Metode Rencana</th>
                        <th>Pemohon</th>
                        <th>Tanggal</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requests as $r)
                        <tr>
                            <td><a href="{{ route('disposals.show', $r) }}" class="font-semibold text-secondary hover:underline">{{ $r->request_no }}</a></td>
                            <td>
                                <span class="tag">{{ $r->asset?->asset_tag }}</span>
                                <div class="text-body-sm">{{ $r->asset?->name }}</div>
                                <div class="text-label-sm text-on-surface-variant">{{ $r->asset?->category?->name }}</div>
                            </td>
                            <td><x-badge :enum="$r->reason_type" /></td>
                            <td>{{ $r->planned_method?->label() }}</td>
                            <td>{{ $r->requester?->name }}</td>
                            <td class="whitespace-nowrap">{{ $r->created_at?->format('d M Y') }}</td>
                            <td><x-badge :enum="$r->status" /></td>
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
</div>
@endsection
