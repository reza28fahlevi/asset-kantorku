@extends('layouts.app')
@section('title', 'Permintaan Peminjaman Aset')
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
@section('content')
<x-card :padding="false">
    <form method="GET" class="flex flex-wrap items-end gap-space-md px-space-lg py-space-md border-b border-border-subtle">
        <div class="flex-1 min-w-[200px]">
            <label class="form-label">Nomor Permintaan</label>
            <input type="search" name="q" value="{{ request('q') }}" class="form-input" placeholder="Cari nomor permintaan...">
        </div>
        <div class="w-56">
            <label class="form-label">Status</label>
            <select name="status" class="form-input">
                <option value="">Semua status</option>
                @foreach (App\Enums\RequestStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-secondary"><span class="material-symbols-outlined !text-[18px]">filter_list</span> Filter</button>
        @if (request()->hasAny(['q', 'status']))<a href="{{ route('loans.index') }}" class="btn btn-ghost">Reset</a>@endif
    </form>
    @if ($requests->isEmpty())
        <x-empty icon="schedule" message="Belum ada permintaan peminjaman." />
    @else
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>No. Permintaan</th><th>Peminjam</th><th>Periode</th><th class="text-center">Jml Aset</th><th>Pemohon</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach ($requests as $r)
                    <tr>
                        <td><a href="{{ route('loans.show', $r) }}" class="font-semibold text-secondary hover:underline">{{ $r->request_no }}</a></td>
                        <td>{{ $r->borrower?->name ?? '-' }}</td>
                        <td>{{ $r->start_date?->format('d M Y') }} &ndash; {{ $r->due_date?->format('d M Y') }}</td>
                        <td class="text-center">{{ $r->assets_count }}</td>
                        <td>{{ $r->requester?->name ?? '-' }}</td>
                        <td><x-badge :enum="$r->status" /></td>
                        <td class="text-right"><a href="{{ route('loans.show', $r) }}" class="btn btn-ghost btn-sm">Detail</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-space-lg py-space-md border-t border-border-subtle">{{ $requests->links() }}</div>
    @endif
</x-card>
@endsection
