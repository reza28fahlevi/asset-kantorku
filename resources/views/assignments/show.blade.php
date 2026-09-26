@extends('layouts.app')
@section('title', $assignment->request_no)
@section('subtitle', 'Permintaan penugasan aset untuk '.($assignment->recipient?->name ?? '-'))
@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span><a href="{{ route('assignments.index') }}" class="hover:text-on-surface">Penugasan</a>
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span><span class="text-on-surface font-semibold">{{ $assignment->request_no }}</span>
@endsection
@section('actions')
    @can('submit', $assignment)
        <x-confirm-form :action="route('assignments.submit', $assignment)" confirm="Ajukan permintaan ini untuk approval?" button="btn btn-primary">
            <span class="material-symbols-outlined !text-[18px]">send</span> Ajukan
        </x-confirm-form>
    @endcan
    @can('cancel', $assignment)
        <x-cancel-request :action="route('assignments.cancel', $assignment)" :approved="$assignment->status === \App\Enums\RequestStatus::Approved" label="permintaan serah terima"/>
    @endcan
@endsection
@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter">
    <div class="lg:col-span-2 space-y-gutter">
        <x-card title="Detail Permintaan" icon="description">
            <x-slot:actions><x-badge :enum="$assignment->status" /></x-slot:actions>
            <dl class="dl-grid">
                <dt>No. Permintaan</dt><dd class="font-semibold">{{ $assignment->request_no }}</dd>
                <dt>Pemohon</dt><dd>{{ $assignment->requester?->name ?? '-' }}</dd>
                <dt>Penerima</dt><dd>{{ $assignment->recipient?->name ?? '-' }}@if ($assignment->recipient?->department) <span class="text-on-surface-variant">&middot; {{ $assignment->recipient->department->name }}</span>@endif</dd>
                <dt>Manager Penerima</dt><dd>{{ $assignment->recipient?->manager?->name ?? '-' }}</dd>
                <dt>Lokasi Penempatan</dt><dd>{{ $assignment->location?->name ?? '-' }}</dd>
                <dt>Tanggal Mulai</dt><dd>{{ $assignment->start_date?->format('d M Y') }}</dd>
                <dt>Diajukan</dt><dd>{{ $assignment->submitted_at?->format('d M Y H:i') ?? '-' }}</dd>
                @if ($assignment->fulfilled_at)<dt>Terlaksana</dt><dd>{{ $assignment->fulfilled_at->format('d M Y H:i') }}</dd>@endif
                @if ($assignment->cancelled_at)<dt>Dibatalkan</dt><dd>{{ $assignment->cancelled_at->format('d M Y H:i') }}@if ($assignment->cancelledBy) · {{ $assignment->cancelledBy->name }}@endif</dd>@if ($assignment->cancel_reason)<dt>Alasan Batal</dt><dd>{{ $assignment->cancel_reason }}</dd>@endif @endif
                <dt>Tujuan</dt><dd class="whitespace-pre-line">{{ $assignment->purpose }}</dd>
            </dl>
        </x-card>

        <x-card title="Aset Diminta ({{ $assignment->assets->count() }})" icon="inventory_2" :padding="false">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Asset Tag</th><th>Nama</th><th>Kategori</th><th>Lokasi Saat Ini</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach ($assignment->assets as $asset)
                        <tr>
                            <td><a href="{{ route('assets.show', $asset) }}" class="tag hover:underline">{{ $asset->asset_tag }}</a></td>
                            <td>{{ $asset->name }}</td>
                            <td>{{ $asset->category?->name ?? '-' }}</td>
                            <td>{{ $asset->location?->name ?? '-' }}</td>
                            <td><x-badge :enum="$asset->status" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        @if ($assignment->assignments->isNotEmpty())
            <x-card title="Serah-Terima & Pengembalian" icon="handshake" :padding="false">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Aset</th><th>Diserahkan</th><th>Kondisi Awal</th><th>Dikembalikan</th><th>Kondisi Akhir</th><th>Lampiran</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($assignment->assignments as $item)
                            <tr>
                                <td><span class="tag">{{ $item->asset->asset_tag }}</span><div class="text-body-sm mt-1">{{ $item->asset->name }}</div></td>
                                <td>{{ $item->assigned_at->format('d M Y H:i') }}<div class="text-label-sm text-on-surface-variant">oleh {{ $item->assignedBy?->name ?? '-' }}@if ($item->handover_document_no) &middot; {{ $item->handover_document_no }}@endif</div></td>
                                <td>@if ($item->condition_out)<x-badge :enum="$item->condition_out" />@endif</td>
                                <td>{{ $item->returned_at?->format('d M Y H:i') ?? '-' }}</td>
                                <td>@if ($item->condition_in)<x-badge :enum="$item->condition_in" />@else - @endif</td>
                                <td>
                                    @forelse ($item->attachments as $att)
                                        <a href="{{ route('attachments.download', $att) }}" class="flex items-center gap-1 text-secondary hover:underline text-body-sm"><span class="material-symbols-outlined !text-[16px]">attach_file</span>{{ $att->original_name }}</a>
                                    @empty - @endforelse
                                </td>
                                <td class="text-right">
                                    @can('return', $item)
                                        <div x-data="{ open: false }">
                                            <button type="button" class="btn btn-secondary btn-sm" @click="open = true"><span class="material-symbols-outlined !text-[16px]">assignment_return</span> Kembalikan</button>
                                            @include('assignments._return-modal', [
                                                'action' => route('assignments.return', $item),
                                                'title' => 'Pengembalian '.$item->asset->asset_tag.' - '.$item->asset->name,
                                                'currentLocationId' => $item->location_id,
                                                'locationRequired' => true,
                                            ])
                                        </div>
                                    @elseif ($item->isActive())
                                        <x-badge color="assigned">Aktif</x-badge>
                                    @else
                                        <x-badge color="neutral">Selesai</x-badge>
                                    @endcan
                                </td>
                            </tr>
                            @if ($item->handover_notes || $item->return_notes)
                                <tr><td colspan="7" class="text-body-sm text-on-surface-variant">
                                    @if ($item->handover_notes)<div><strong>Catatan serah-terima:</strong> {{ $item->handover_notes }}</div>@endif
                                    @if ($item->return_notes)<div><strong>Catatan pengembalian:</strong> {{ $item->return_notes }}</div>@endif
                                </td></tr>
                            @endif
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endif
    </div>

    <div class="space-y-gutter">
        @can('handover', $assignment)
            <x-card title="Serah-Terima Aset" icon="handshake">
                <form method="POST" action="{{ route('assignments.handover', $assignment) }}" enctype="multipart/form-data" class="space-y-space-md">
                    @csrf
                    <x-field label="Tanggal Serah-Terima" name="assigned_at" :required="true">
                        <input type="datetime-local" name="assigned_at" id="assigned_at" value="{{ old('assigned_at', now()->format('Y-m-d\TH:i')) }}" max="{{ now()->format('Y-m-d\TH:i') }}" class="form-input" required>
                    </x-field>
                    <x-field label="Kondisi Aset" name="condition_out" :required="true">
                        <select name="condition_out" id="condition_out" class="form-input" required>
                            @foreach (App\Enums\AssetCondition::cases() as $c)
                                <option value="{{ $c->value }}" @selected(old('condition_out') === $c->value)>{{ $c->label() }}</option>
                            @endforeach
                        </select>
                    </x-field>
                    <x-field label="No. Dokumen BAST" name="handover_document_no">
                        <input type="text" name="handover_document_no" id="handover_document_no" value="{{ old('handover_document_no') }}" maxlength="50" class="form-input">
                    </x-field>
                    <x-field label="Catatan" name="handover_notes">
                        <textarea name="handover_notes" id="handover_notes" rows="2" maxlength="2000" class="form-input">{{ old('handover_notes') }}</textarea>
                    </x-field>
                    <x-field label="Lampiran (maks. 5 berkas)" name="attachments">
                        <input type="file" name="attachments[]" multiple class="form-input">
                    </x-field>
                    <button class="btn btn-success w-full justify-center"><span class="material-symbols-outlined !text-[18px]">check_circle</span> Catat Serah-Terima</button>
                </form>
            </x-card>
        @endcan

        <x-approval-timeline :approval="$assignment->approvalRequest" />
    </div>
</div>
@endsection
