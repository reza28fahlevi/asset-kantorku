@extends('layouts.app')

@php
    $icons = ['departments' => 'corporate_fare', 'locations' => 'location_on', 'categories' => 'category', 'vendors' => 'storefront'];
    $canManage = auth()->user()?->hasPermission('master.manage');
@endphp

@section('title', $title)
@section('subtitle', 'Master data referensi. Data yang sudah dipakai transaksi cukup dinonaktifkan.')
@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span>Master Data</span>
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span class="text-on-surface font-semibold">{{ $title }}</span>
@endsection

@section('actions')
    @if ($canManage)
        <a href="{{ route("masters.{$key}.create") }}" class="btn btn-primary" data-modal>
            <span class="material-symbols-outlined !text-[18px]">add</span> Tambah {{ $title }}
        </a>
    @endif
@endsection

@section('content')
<div class="card">
    <form method="GET" class="flex flex-wrap items-end gap-space-md px-space-lg py-space-md border-b border-border-subtle">
        <div class="flex-1 min-w-[220px]">
            <label class="form-label">Cari</label>
            <input type="text" name="q" value="{{ request('q') }}" class="form-input" placeholder="Kode atau nama {{ strtolower($title) }}">
        </div>
        <button class="btn btn-secondary"><span class="material-symbols-outlined !text-[18px]">search</span> Cari</button>
        @if (request()->filled('q'))
            <a href="{{ route("masters.{$key}.index") }}" class="btn btn-ghost">Reset</a>
        @endif
    </form>

    @if ($items->isEmpty())
        <x-empty :icon="$icons[$key] ?? 'database'" message="Belum ada data {{ strtolower($title) }}." />
    @else
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        @switch($key)
                            @case('locations') <th>Induk</th><th>Alamat</th> @break
                            @case('categories') <th>Umur Manfaat</th><th>Wajib Serial</th> @break
                            @case('vendors') <th>Kontak</th><th>Telepon / Email</th><th>NPWP</th> @break
                        @endswitch
                        <th>Status</th>
                        @if ($canManage)<th></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td><span class="tag">{{ $item->code }}</span></td>
                            <td class="font-semibold text-on-surface">{{ $item->name }}</td>
                            @switch($key)
                                @case('locations')
                                    <td>{{ $item->parent?->name ?? '-' }}</td>
                                    <td class="text-body-sm max-w-xs truncate">{{ $item->address ?? '-' }}</td>
                                    @break
                                @case('categories')
                                    <td>{{ $item->useful_life_months ? $item->useful_life_months.' bulan' : '-' }}</td>
                                    <td>{{ $item->requires_serial ? 'Ya' : 'Tidak' }}</td>
                                    @break
                                @case('vendors')
                                    <td>{{ $item->contact_person ?? '-' }}</td>
                                    <td class="text-body-sm">{{ $item->phone ?? '-' }}<div class="text-on-surface-variant">{{ $item->email }}</div></td>
                                    <td>{{ $item->tax_number ?? '-' }}</td>
                                    @break
                            @endswitch
                            <td>
                                @if ($item->is_active)
                                    <x-badge color="available">Aktif</x-badge>
                                @else
                                    <x-badge color="neutral">Nonaktif</x-badge>
                                @endif
                            </td>
                            @if ($canManage)
                                <td class="text-right whitespace-nowrap">
                                    <a href="{{ route("masters.{$key}.edit", $item->getKey()) }}" class="btn btn-ghost btn-sm" data-modal data-modal-title="Ubah {{ $title }}">Ubah</a>
                                    <x-confirm-form :action="route('masters.'.$key.'.destroy', $item->getKey())" method="DELETE"
                                                    confirm="Hapus {{ $item->name }}? Data yang sudah dipakai tidak dapat dihapus." button="btn btn-ghost btn-sm text-error">
                                        Hapus
                                    </x-confirm-form>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-space-lg py-space-md border-t border-border-subtle">{{ $items->links() }}</div>
    @endif
</div>
@endsection
