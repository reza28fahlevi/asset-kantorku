@extends('layouts.app')

@section('title', $procurement->title)
@section('subtitle', $procurement->request_no.' · diajukan oleh '.($procurement->requester?->name ?? '-'))

@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <a href="{{ route('procurements.index') }}" class="hover:text-on-surface">Procurement</a>
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span class="text-on-surface font-semibold">{{ $procurement->request_no }}</span>
@endsection

@php
    $rp = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
@endphp

@section('actions')
    @can('update', $procurement)
        <a href="{{ route('procurements.edit', $procurement) }}" class="btn btn-secondary">
            <span class="material-symbols-outlined !text-[18px]">edit</span> Lengkapi Draft
        </a>
    @endcan
    @can('submit', $procurement)
        <x-confirm-form :action="route('procurements.submit', $procurement)" method="POST" confirm="Ajukan permintaan ini untuk approval? Setelah diajukan data tidak dapat diubah." button="btn btn-primary">
            <span class="material-symbols-outlined !text-[18px]">send</span> Ajukan
        </x-confirm-form>
    @endcan
    @can('receive', $procurement)
        <a href="{{ route('procurements.receive-form', $procurement) }}" class="btn btn-success">
            <span class="material-symbols-outlined !text-[18px]">inventory</span> Catat Penerimaan
        </a>
    @endcan
    @can('cancel', $procurement)
        <x-cancel-request :action="route('procurements.cancel', $procurement)" :approved="$procurement->status === \App\Enums\ProcurementStatus::Approved" label="permintaan pengadaan"/>
    @endcan
@endsection

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter items-start">
    <div class="lg:col-span-2 space-y-space-lg">
        {{-- Informasi --}}
        <x-card title="Informasi Permintaan" icon="description">
            <x-slot:actions><x-badge :enum="$procurement->status"/></x-slot:actions>
            <dl class="dl-grid">
                <dt>No. Permintaan</dt><dd><span class="tag">{{ $procurement->request_no }}</span></dd>
                <dt>Judul</dt><dd>{{ $procurement->title }}</dd>
                <dt>Pemohon</dt><dd>{{ $procurement->requester?->name ?? '-' }} <span class="text-outline">{{ $procurement->requester?->employee_no }}</span></dd>
                <dt>Departemen</dt><dd>{{ $procurement->department?->name ?? '-' }}</dd>
                <dt>Tanggal Dibutuhkan</dt><dd>{{ $procurement->needed_by?->format('d M Y') ?? '-' }}</dd>
                <dt>Diajukan</dt><dd>{{ $procurement->submitted_at?->format('d M Y H:i') ?? '-' }}</dd>
                <dt>Justifikasi</dt><dd class="whitespace-pre-line">{{ $procurement->justification }}</dd>
                @if ($procurement->cancelled_at)
                    <dt>Dibatalkan</dt><dd>{{ $procurement->cancelled_at->format('d M Y H:i') }}@if ($procurement->cancelledBy) · {{ $procurement->cancelledBy->name }}@endif</dd>@if ($procurement->cancel_reason)<dt>Alasan Batal</dt><dd>{{ $procurement->cancel_reason }}</dd>@endif
                @endif
                @if ($procurement->completed_at)
                    <dt>Selesai</dt><dd>{{ $procurement->completed_at->format('d M Y H:i') }}</dd>
                @endif
                @if ($procurement->closing_note)
                    <dt>Catatan Penutupan</dt><dd class="whitespace-pre-line">{{ $procurement->closing_note }}</dd>
                @endif
            </dl>
        </x-card>

        {{-- Item --}}
        <x-card title="Rincian Item" icon="list_alt" :padding="false">
            <x-slot:actions>
                <span class="text-label-sm font-normal text-outline">{{ $procurement->items->count() }} jenis · {{ $procurement->items->sum('quantity') }} unit</span>
            </x-slot:actions>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Item & Spesifikasi</th>
                            <th>Kategori</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Harga Satuan</th>
                            <th class="text-right">Subtotal</th>
                            <th class="text-right">Diterima</th>
                            <th class="text-right">Ditolak</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($procurement->items as $item)
                            <tr class="align-top">
                                <td>
                                    <p class="font-semibold">{{ $item->item_name }}</p>
                                    @if ($item->specification)<p class="text-body-sm text-on-surface-variant whitespace-pre-line">{{ $item->specification }}</p>@endif
                                    @if ($item->assets->isNotEmpty())
                                        <div class="flex flex-wrap gap-1 mt-space-xs">
                                            @foreach ($item->assets as $asset)
                                                <a href="{{ route('assets.show', $asset) }}" class="tag hover:underline">{{ $asset->asset_tag }}</a>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td>{{ $item->category?->name ?? '-' }}</td>
                                <td class="text-right">{{ $item->quantity }}</td>
                                <td class="text-right font-mono whitespace-nowrap">{{ $rp($item->estimated_unit_price) }}</td>
                                <td class="text-right font-mono whitespace-nowrap">{{ $rp($item->subtotal()) }}</td>
                                <td class="text-right">{{ $item->quantity_received }}</td>
                                <td class="text-right">{{ $item->quantity_rejected }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-surface-subtle">
                            <td colspan="4" class="px-space-md py-space-sm text-right text-label-sm uppercase text-outline">Total Estimasi</td>
                            <td class="px-space-md py-space-sm text-right font-mono font-semibold whitespace-nowrap">{{ $rp($procurement->estimated_total) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-card>

        {{-- Penerimaan --}}
        <x-card title="Riwayat Penerimaan Barang" icon="inventory_2" :padding="false">
            @if ($procurement->receipts->isEmpty())
                <x-empty icon="inventory_2" message="Belum ada penerimaan barang."/>
            @else
                <div class="divide-y divide-border-subtle">
                    @foreach ($procurement->receipts as $receipt)
                        <div class="px-space-lg py-space-md space-y-space-sm">
                            <div class="flex flex-wrap items-center justify-between gap-space-sm">
                                <div class="flex items-center gap-space-sm">
                                    <span class="tag">{{ $receipt->receipt_no }}</span>
                                    <span class="text-body-sm text-on-surface-variant">{{ $receipt->received_at?->format('d M Y H:i') }} · oleh {{ $receipt->receivedBy?->name ?? '-' }}</span>
                                </div>
                                @if ($receipt->delivery_note_no)
                                    <span class="text-label-sm font-normal text-outline">Surat Jalan: <span class="font-mono">{{ $receipt->delivery_note_no }}</span></span>
                                @endif
                            </div>
                            <table class="table">
                                <thead><tr><th>Item</th><th class="text-right">Diterima</th><th class="text-right">Ditolak</th><th>Catatan</th></tr></thead>
                                <tbody>
                                    @foreach ($receipt->items as $line)
                                        <tr>
                                            <td>{{ $line->requestItem?->item_name ?? '-' }}</td>
                                            <td class="text-right">{{ $line->quantity_accepted }}</td>
                                            <td class="text-right">{{ $line->quantity_rejected }}</td>
                                            <td class="text-body-sm text-on-surface-variant">{{ $line->exception_notes ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if ($receipt->notes)<p class="text-body-sm text-on-surface-variant whitespace-pre-line">{{ $receipt->notes }}</p>@endif
                            @if ($receipt->attachments->isNotEmpty())
                                <div class="flex flex-wrap gap-space-sm">
                                    @foreach ($receipt->attachments as $file)
                                        <a href="{{ route('attachments.download', $file) }}" class="inline-flex items-center gap-1 text-body-sm text-status-assigned hover:underline">
                                            <span class="material-symbols-outlined !text-[16px]">attach_file</span>{{ $file->original_name }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>

        {{-- Lampiran --}}
        <x-card title="Dokumen Pendukung" icon="attach_file">
            @if ($procurement->attachments->isEmpty())
                <p class="text-body-sm text-outline">Tidak ada lampiran.</p>
            @else
                <div class="grid sm:grid-cols-2 gap-space-sm">
                    @foreach ($procurement->attachments as $file)
                        <a href="{{ route('attachments.download', $file) }}" class="flex items-center gap-space-sm border border-border-subtle rounded-lg p-space-sm hover:bg-surface-subtle">
                            <span class="material-symbols-outlined text-status-disposal">description</span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-body-sm font-semibold truncate">{{ $file->original_name }}</span>
                                <span class="block text-label-sm font-normal text-outline">{{ $file->humanSize() }} · {{ \App\Models\Attachment::CATEGORIES[$file->category] ?? $file->category }} · {{ $file->created_at?->format('d M Y') }}</span>
                            </span>
                            <span class="material-symbols-outlined !text-[18px] text-outline">download</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </x-card>
    </div>

    {{-- Sidebar --}}
    <div class="space-y-space-lg">
        <div class="card">
            <div class="card-body">
                <p class="text-label-sm uppercase text-outline">Total Estimasi Biaya</p>
                <p class="text-display-md font-mono mt-1">{{ $rp($procurement->estimated_total) }}</p>
                <div class="mt-space-sm"><x-badge :enum="$procurement->status"/></div>
            </div>
        </div>

        <x-approval-timeline :approval="$procurement->approvalRequest"/>

        {{-- Pemesanan --}}
        <x-card title="Pemesanan (PO)" icon="local_shipping">
            @if ($procurement->po_number)
                <dl class="dl-grid">
                    <dt>No. PO</dt><dd class="font-mono">{{ $procurement->po_number }}</dd>
                    <dt>Vendor</dt><dd>{{ $procurement->vendor?->name ?? '-' }}</dd>
                    <dt>Tgl Pesan</dt><dd>{{ $procurement->ordered_at?->format('d M Y') ?? '-' }}</dd>
                    <dt>Dicatat oleh</dt><dd>{{ $procurement->orderedBy?->name ?? '-' }}</dd>
                </dl>
            @elseif (auth()->user()->can('order', $procurement))
                <form method="POST" action="{{ route('procurements.order', $procurement) }}" class="space-y-space-md">
                    @csrf
                    <x-field label="Vendor" name="vendor_id" :required="true">
                        <select id="vendor_id" name="vendor_id" class="form-input" required>
                            <option value="">Pilih vendor</option>
                            @foreach ($vendors as $vendor)
                                <option value="{{ $vendor->id }}" @selected((string) old('vendor_id') === (string) $vendor->id)>{{ $vendor->name }}</option>
                            @endforeach
                        </select>
                    </x-field>
                    <x-field label="Nomor PO" name="po_number" :required="true">
                        <input type="text" id="po_number" name="po_number" value="{{ old('po_number') }}" maxlength="50" class="form-input font-mono" required>
                    </x-field>
                    <x-field label="Tanggal Pesan" name="ordered_at" :required="true">
                        <input type="date" id="ordered_at" name="ordered_at" value="{{ old('ordered_at', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="form-input" required>
                    </x-field>
                    <button type="submit" class="btn btn-primary w-full justify-center">
                        <span class="material-symbols-outlined !text-[18px]">shopping_bag</span> Catat Pemesanan
                    </button>
                </form>
            @else
                <p class="text-body-sm text-outline">Belum ada pemesanan. PO dicatat setelah permintaan disetujui.</p>
            @endif
        </x-card>

        {{-- Tutup --}}
        @can('close', $procurement)
            <x-card title="Tutup Procurement" icon="task_alt">
                <p class="text-body-sm text-on-surface-variant mb-space-md">Barang diterima sebagian. Tutup permintaan bila sisa barang tidak akan dikirim.</p>
                <form method="POST" action="{{ route('procurements.close', $procurement) }}" class="space-y-space-md" onsubmit="return confirm('Tutup procurement ini? Sisa barang tidak akan diterima lagi.')">
                    @csrf
                    <x-field label="Catatan Penutupan" name="closing_note" :required="true">
                        <textarea id="closing_note" name="closing_note" rows="3" maxlength="2000" class="form-input" required>{{ old('closing_note') }}</textarea>
                    </x-field>
                    <button type="submit" class="btn btn-secondary w-full justify-center">Tutup Procurement</button>
                </form>
            </x-card>
        @endcan
    </div>
</div>
@endsection
