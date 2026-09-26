@extends('layouts.app')
@section('title', $loanRequest->request_no)
@section('subtitle', 'Permintaan peminjaman aset oleh '.($loanRequest->borrower?->name ?? '-'))
@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span><a href="{{ route('loans.index') }}" class="hover:text-on-surface">Peminjaman</a>
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span><span class="text-on-surface font-semibold">{{ $loanRequest->request_no }}</span>
@endsection
@section('actions')
    @can('submit', $loanRequest)
        <x-confirm-form :action="route('loans.submit', $loanRequest)" confirm="Ajukan permintaan peminjaman ini untuk approval?" button="btn btn-primary">
            <span class="material-symbols-outlined !text-[18px]">send</span> Ajukan
        </x-confirm-form>
    @endcan
    @can('cancel', $loanRequest)
        <x-cancel-request :action="route('loans.cancel', $loanRequest)" :approved="$loanRequest->status === \App\Enums\RequestStatus::Approved" label="permintaan peminjaman"/>
    @endcan
@endsection
@section('content')
@php $days = $loanRequest->start_date && $loanRequest->due_date ? (int) $loanRequest->start_date->diffInDays($loanRequest->due_date) + 1 : null; @endphp
<div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter">
    <div class="lg:col-span-2 space-y-gutter">
        <x-card title="Detail Permintaan" icon="description">
            <x-slot:actions><x-badge :enum="$loanRequest->status" /></x-slot:actions>
            <dl class="dl-grid">
                <dt>No. Permintaan</dt><dd class="font-semibold">{{ $loanRequest->request_no }}</dd>
                <dt>Pemohon</dt><dd>{{ $loanRequest->requester?->name ?? '-' }}</dd>
                <dt>Peminjam</dt><dd>{{ $loanRequest->borrower?->name ?? '-' }}@if ($loanRequest->borrower?->department) <span class="text-on-surface-variant">&middot; {{ $loanRequest->borrower->department->name }}</span>@endif</dd>
                <dt>Manager Peminjam</dt><dd>{{ $loanRequest->borrower?->manager?->name ?? '-' }}</dd>
                <dt>Lokasi Penggunaan</dt><dd>{{ $loanRequest->usageLocation?->name ?? '-' }}</dd>
                <dt>Periode</dt><dd>{{ $loanRequest->start_date?->format('d M Y') }} &ndash; {{ $loanRequest->due_date?->format('d M Y') }}@if ($days) <span class="text-on-surface-variant">({{ $days }} hari)</span>@endif</dd>
                <dt>Diajukan</dt><dd>{{ $loanRequest->submitted_at?->format('d M Y H:i') ?? '-' }}</dd>
                @if ($loanRequest->fulfilled_at)<dt>Terlaksana</dt><dd>{{ $loanRequest->fulfilled_at->format('d M Y H:i') }}</dd>@endif
                @if ($loanRequest->cancelled_at)<dt>Dibatalkan</dt><dd>{{ $loanRequest->cancelled_at->format('d M Y H:i') }}@if ($loanRequest->cancelledBy) · {{ $loanRequest->cancelledBy->name }}@endif</dd>@if ($loanRequest->cancel_reason)<dt>Alasan Batal</dt><dd>{{ $loanRequest->cancel_reason }}</dd>@endif @endif
                <dt>Tujuan</dt><dd class="whitespace-pre-line">{{ $loanRequest->purpose }}</dd>
            </dl>
        </x-card>

        <x-card title="Aset Diminta ({{ $loanRequest->assets->count() }})" icon="inventory_2" :padding="false">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Asset Tag</th><th>Nama</th><th>Kategori</th><th>Lokasi Saat Ini</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach ($loanRequest->assets as $asset)
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

        @if ($loanRequest->loans->isNotEmpty())
            <x-card title="Peminjaman & Pengembalian" icon="swap_horiz" :padding="false">
                <div class="divide-y divide-border-subtle">
                @foreach ($loanRequest->loans as $loan)
                    @php $overdue = $loan->isOverdue(); $pendingExt = $loan->extensions->firstWhere('status', App\Enums\ExtensionStatus::PendingApproval); @endphp
                    <div class="px-space-lg py-space-md space-y-space-sm" x-data="{ open: false, extend: false }">
                        <div class="flex flex-wrap items-start justify-between gap-space-md">
                            <div>
                                <div class="flex items-center gap-space-sm"><span class="tag">{{ $loan->asset->asset_tag }}</span><span class="font-semibold text-body-md">{{ $loan->asset->name }}</span>
                                    @if ($overdue)<x-badge :enum="App\Enums\LoanStatus::Overdue" />@else<x-badge :enum="$loan->status" />@endif
                                    @if ($loan->is_late && ! $loan->isActive())<x-badge color="disposal">Kembali terlambat</x-badge>@endif
                                </div>
                            </div>
                            <div class="flex gap-space-xs">
                                @can('extend', $loan)
                                    @unless ($pendingExt)
                                        <button type="button" class="btn btn-ghost btn-sm" @click="extend = true"><span class="material-symbols-outlined !text-[16px]">more_time</span> Perpanjang</button>
                                        @include('loans._extend-modal', ['loan' => $loan])
                                    @endunless
                                @endcan
                                @can('return', $loan)
                                    <button type="button" class="btn btn-secondary btn-sm" @click="open = true"><span class="material-symbols-outlined !text-[16px]">assignment_return</span> Kembalikan</button>
                                    @include('assignments._return-modal', [
                                        'action' => route('loans.return', $loan),
                                        'title' => 'Pengembalian '.$loan->asset->asset_tag.' - '.$loan->asset->name,
                                        'currentLocationId' => null,
                                        'locationRequired' => false,
                                    ])
                                @endcan
                            </div>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-space-md text-body-sm">
                            <div><div class="text-label-sm text-on-surface-variant">Diserahkan</div>{{ $loan->checked_out_at->format('d M Y H:i') }}<div class="text-label-sm text-on-surface-variant">oleh {{ $loan->checkedOutBy?->name ?? '-' }}</div></div>
                            <div><div class="text-label-sm text-on-surface-variant">Jatuh Tempo</div><span class="{{ $overdue ? 'text-error font-semibold' : '' }}">{{ $loan->due_at->format('d M Y') }}</span>
                                @if (! $loan->due_at->equalTo($loan->original_due_at))<div class="text-label-sm text-on-surface-variant">Awal: {{ $loan->original_due_at->format('d M Y') }}</div>@endif</div>
                            <div><div class="text-label-sm text-on-surface-variant">Kondisi Awal</div>@if ($loan->condition_out)<x-badge :enum="$loan->condition_out" />@endif</div>
                            <div><div class="text-label-sm text-on-surface-variant">Dikembalikan</div>
                                @if ($loan->returned_at){{ $loan->returned_at->format('d M Y H:i') }} @if ($loan->condition_in)<x-badge :enum="$loan->condition_in" />@endif
                                    <div class="text-label-sm text-on-surface-variant">oleh {{ $loan->returnedBy?->name ?? '-' }}</div>
                                @else - @endif</div>
                        </div>
                        @if ($loan->checkout_notes)<p class="text-body-sm text-on-surface-variant"><strong>Catatan serah-terima:</strong> {{ $loan->checkout_notes }}</p>@endif
                        @if ($loan->return_notes)<p class="text-body-sm text-on-surface-variant"><strong>Catatan pengembalian:</strong> {{ $loan->return_notes }}</p>@endif
                        @if ($loan->attachments->isNotEmpty())
                            <div class="flex flex-wrap gap-space-md">
                                @foreach ($loan->attachments as $att)
                                    <a href="{{ route('attachments.download', $att) }}" class="flex items-center gap-1 text-secondary hover:underline text-body-sm"><span class="material-symbols-outlined !text-[16px]">attach_file</span>{{ $att->original_name }}</a>
                                @endforeach
                            </div>
                        @endif

                        @if ($loan->extensions->isNotEmpty())
                            <div class="border border-border-subtle rounded">
                                <table class="table">
                                    <thead><tr><th>Perpanjangan</th><th>Jatuh Tempo</th><th>Alasan</th><th>Status</th><th></th></tr></thead>
                                    <tbody>
                                    @foreach ($loan->extensions as $ext)
                                        <tr>
                                            <td class="font-semibold">{{ $ext->request_no }}</td>
                                            <td>{{ $ext->current_due_at->format('d M Y') }} &rarr; <strong>{{ $ext->requested_due_at->format('d M Y') }}</strong></td>
                                            <td class="text-body-sm">{{ $ext->reason }}</td>
                                            <td><x-badge :enum="$ext->status" />
                                                @php $step = $ext->approvalRequest?->steps->firstWhere('status', App\Enums\ApprovalStatus::Pending); @endphp
                                                @if ($step)<div class="text-label-sm text-on-surface-variant mt-1">Menunggu {{ $step->approver?->name ?? 'approver' }}</div>@endif
                                            </td>
                                            <td class="text-right">
                                                @can('cancel', $ext)
                                                    <x-confirm-form :action="route('loans.extensions.cancel', $ext)" confirm="Batalkan pengajuan perpanjangan ini?" button="btn btn-ghost btn-sm !text-error">Batalkan</x-confirm-form>
                                                @endcan
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endforeach
                </div>
            </x-card>
        @endif
    </div>

    <div class="space-y-gutter">
        @can('checkout', $loanRequest)
            <x-card title="Serah-Terima Peminjaman" icon="handshake">
                <form method="POST" action="{{ route('loans.checkout', $loanRequest) }}" enctype="multipart/form-data" class="space-y-space-md">
                    @csrf
                    <x-field label="Tanggal Serah-Terima" name="checked_out_at" :required="true">
                        <input type="datetime-local" name="checked_out_at" id="checked_out_at" value="{{ old('checked_out_at', now()->format('Y-m-d\TH:i')) }}" max="{{ now()->format('Y-m-d\TH:i') }}" class="form-input" required>
                    </x-field>
                    <x-field label="Kondisi Aset" name="condition_out" :required="true">
                        <select name="condition_out" id="condition_out" class="form-input" required>
                            @foreach (App\Enums\AssetCondition::cases() as $c)
                                <option value="{{ $c->value }}" @selected(old('condition_out') === $c->value)>{{ $c->label() }}</option>
                            @endforeach
                        </select>
                    </x-field>
                    <x-field label="Catatan" name="checkout_notes">
                        <textarea name="checkout_notes" id="checkout_notes" rows="2" maxlength="2000" class="form-input">{{ old('checkout_notes') }}</textarea>
                    </x-field>
                    <x-field label="Lampiran (maks. 5 berkas)" name="attachments">
                        <input type="file" name="attachments[]" multiple class="form-input">
                    </x-field>
                    <p class="form-hint">Jatuh tempo: {{ $loanRequest->due_date?->format('d M Y') }}</p>
                    <button class="btn btn-success w-full justify-center"><span class="material-symbols-outlined !text-[18px]">check_circle</span> Catat Serah-Terima</button>
                </form>
            </x-card>
        @endcan

        <x-approval-timeline :approval="$loanRequest->approvalRequest" />
    </div>
</div>
@endsection
