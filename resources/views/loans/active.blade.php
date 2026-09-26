@extends('layouts.app')
@section('title', 'Peminjaman Aktif')
@section('subtitle', 'Aset yang sedang dipinjam, termasuk yang mendekati jatuh tempo dan terlambat.')
@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span><a href="{{ route('loans.index') }}" class="hover:text-on-surface">Peminjaman</a>
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span><span class="text-on-surface font-semibold">Aktif</span>
@endsection
@section('actions')
    <a href="{{ route('loans.index') }}" class="btn btn-secondary"><span class="material-symbols-outlined !text-[18px]">list</span> Daftar Permintaan</a>
@endsection
@section('content')
<x-card :padding="false">
    <div class="flex flex-wrap gap-space-sm px-space-lg py-space-md border-b border-border-subtle">
        @foreach (['' => 'Semua', 'due_soon' => 'Jatuh tempo ≤ 3 hari', 'overdue' => 'Terlambat'] as $key => $label)
            <a href="{{ route('loans.active', $key ? ['filter' => $key] : []) }}" class="btn btn-sm {{ ($filter ?? '') === $key ? 'btn-primary' : 'btn-secondary' }}">{{ $label }}</a>
        @endforeach
    </div>
    @if ($loans->isEmpty())
        <x-empty icon="schedule" message="Tidak ada peminjaman aktif untuk filter ini." />
    @else
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Aset</th><th>Peminjam</th><th>Dipinjam</th><th>Jatuh Tempo</th><th>Status</th><th>Permintaan</th><th></th></tr></thead>
                <tbody>
                @foreach ($loans as $loan)
                    @php $overdue = $loan->isOverdue(); $pendingExt = $loan->extensions->firstWhere('status', App\Enums\ExtensionStatus::PendingApproval); @endphp
                    <tr>
                        <td><span class="tag">{{ $loan->asset->asset_tag }}</span><div class="text-body-sm mt-1">{{ $loan->asset->name }}</div><div class="text-label-sm text-on-surface-variant">{{ $loan->asset->category?->name }}</div></td>
                        <td>{{ $loan->borrower?->name }}<div class="text-label-sm text-on-surface-variant">{{ $loan->borrower?->department?->name }}</div></td>
                        <td>{{ $loan->checked_out_at->format('d M Y') }}</td>
                        <td class="{{ $overdue ? 'text-error font-semibold' : '' }}">
                            {{ $loan->due_at->format('d M Y') }}
                            @if ($overdue)<div class="text-label-sm">Terlambat {{ (int) $loan->due_at->diffInDays(now()) }} hari</div>
                            @elseif ($loan->original_due_at && ! $loan->due_at->equalTo($loan->original_due_at))<div class="text-label-sm text-on-surface-variant">Diperpanjang</div>@endif
                        </td>
                        <td>
                            @if ($overdue)<x-badge :enum="App\Enums\LoanStatus::Overdue" />@else<x-badge :enum="$loan->status" />@endif
                            @if ($pendingExt)<div class="mt-1"><x-badge color="pending">Perpanjangan diajukan</x-badge></div>@endif
                        </td>
                        <td>@if ($loan->loanRequest)<a href="{{ route('loans.show', $loan->loanRequest) }}" class="text-secondary hover:underline">{{ $loan->loanRequest->request_no }}</a>@else - @endif</td>
                        <td class="text-right whitespace-nowrap">
                            <div x-data="{ open: false, extend: false }" class="inline-flex gap-space-xs">
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
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-space-lg py-space-md border-t border-border-subtle">{{ $loans->links() }}</div>
    @endif
</x-card>
@endsection
