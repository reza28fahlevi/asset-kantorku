@extends('layouts.app')

@section('title', 'Pengadaan Aset')
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

@section('content')
<x-card title="Daftar Permintaan Pengadaan" icon="shopping_cart" :padding="false">
    <form method="GET" class="px-space-lg py-space-md border-b border-border-subtle flex flex-wrap items-end gap-space-sm">
        <div class="flex-1 min-w-[220px]">
            <label class="form-label" for="q">Cari</label>
            <input type="text" id="q" name="q" value="{{ request('q') }}" class="form-input" placeholder="Nomor permintaan atau judul...">
        </div>
        <div class="w-56">
            <label class="form-label" for="status">Status</label>
            <select id="status" name="status" class="form-input">
                <option value="">Semua status</option>
                @foreach (\App\Enums\ProcurementStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-secondary"><span class="material-symbols-outlined !text-[18px]">filter_list</span> Filter</button>
        @if (request()->hasAny(['q', 'status']))
            <a href="{{ route('procurements.index') }}" class="btn btn-ghost">Reset</a>
        @endif
    </form>

    @if ($requests->isEmpty())
        <x-empty icon="shopping_cart" message="Belum ada permintaan pengadaan."/>
    @else
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>No. Permintaan</th>
                        <th>Judul</th>
                        <th>Pemohon</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Estimasi Total</th>
                        <th>Dibutuhkan</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requests as $row)
                        <tr>
                            <td><a href="{{ route('procurements.show', $row) }}" class="tag hover:underline">{{ $row->request_no }}</a></td>
                            <td>
                                <a href="{{ route('procurements.show', $row) }}" class="font-semibold hover:underline">{{ $row->title }}</a>
                                <p class="text-label-sm font-normal text-outline">Dibuat {{ $row->created_at?->format('d M Y') }}</p>
                            </td>
                            <td>
                                <p>{{ $row->requester?->name ?? '-' }}</p>
                                <p class="text-label-sm font-normal text-outline">{{ $row->department?->name }}</p>
                            </td>
                            <td class="text-right">{{ (int) $row->items_sum_quantity }}</td>
                            <td class="text-right font-mono whitespace-nowrap">Rp {{ number_format((float) $row->estimated_total, 0, ',', '.') }}</td>
                            <td class="whitespace-nowrap">{{ $row->needed_by?->format('d M Y') ?? '-' }}</td>
                            <td><x-badge :enum="$row->status"/></td>
                            <td class="text-right">
                                <a href="{{ route('procurements.show', $row) }}" class="btn btn-ghost btn-sm" title="Detail">
                                    <span class="material-symbols-outlined !text-[18px]">chevron_right</span>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-space-lg py-space-md border-t border-border-subtle">{{ $requests->links() }}</div>
    @endif
</x-card>
@endsection
