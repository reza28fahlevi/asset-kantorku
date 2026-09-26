@extends('layouts.app')

@section('title', 'Register Aset')
@section('subtitle', 'Katalog seluruh aset perusahaan beserta status dan pemegangnya')

@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span class="text-on-surface font-semibold">Register Aset</span>
@endsection

@section('actions')
    @permission('report.view')
        {{-- Export mengikuti filter yang sedang aktif --}}
        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
            <button type="button" class="btn btn-secondary" @click="open = !open">
                <span class="material-symbols-outlined !text-[18px]">download</span> Export
                <span class="material-symbols-outlined !text-[18px]">expand_more</span>
            </button>
            <div x-cloak x-show="open" x-transition class="absolute right-0 mt-1 w-56 card py-1 z-40">
                @foreach ([['xlsx', 'table_view', 'Excel (.xlsx)', 'text-status-available'], ['pdf', 'picture_as_pdf', 'PDF (.pdf)', 'text-status-disposal'], ['csv', 'description', 'CSV (.csv)', 'text-outline']] as [$fmt, $icon, $label, $tone])
                    <a href="{{ route('assets.export', [...request()->except('page'), 'format' => $fmt]) }}" @click="open = false"
                       class="flex items-center gap-space-sm px-space-md py-space-sm text-body-sm hover:bg-surface-subtle">
                        <span class="material-symbols-outlined !text-[18px] {{ $tone }}">{{ $icon }}</span>{{ $label }}
                    </a>
                @endforeach
                <p class="px-space-md pt-1 pb-space-sm text-label-sm font-normal text-outline border-t border-border-subtle mt-1">Sesuai filter aktif. PDF maks. {{ \App\Services\AssetExporter::PDF_MAX_ROWS }} baris.</p>
            </div>
        </div>
    @endpermission
    @permission('asset.create')
        <a href="{{ route('assets.create') }}" class="btn btn-primary">
            <span class="material-symbols-outlined !text-[18px]">add</span> Registrasi Aset
        </a>
    @endpermission
@endsection

@section('content')
    <div class="card mb-space-lg">
        <form method="GET" action="{{ route('assets.index') }}" class="card-body grid grid-cols-1 md:grid-cols-12 gap-space-md items-end">
            <div class="md:col-span-4">
                <label class="form-label" for="q">Cari</label>
                <div class="relative">
                    <span class="material-symbols-outlined !text-[18px] absolute left-2 top-1/2 -translate-y-1/2 text-outline">search</span>
                    <input type="text" id="q" name="q" value="{{ request('q') }}" class="form-input pl-8" placeholder="Tag, nama, serial, merek, model...">
                </div>
            </div>
            <div class="md:col-span-2">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-input">
                    <option value="">Semua Status</option>
                    @foreach (\App\Enums\AssetStatus::cases() as $s)
                        <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="form-label" for="category_id">Kategori</label>
                <select id="category_id" name="category_id" class="form-input">
                    <option value="">Semua Kategori</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}" @selected((string) request('category_id') === (string) $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="form-label" for="location_id">Lokasi</label>
                <select id="location_id" name="location_id" class="form-input">
                    <option value="">Semua Lokasi</option>
                    @foreach ($locations as $l)
                        <option value="{{ $l->id }}" @selected((string) request('location_id') === (string) $l->id)>{{ $l->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2 flex gap-space-sm">
                <button type="submit" class="btn btn-primary flex-1">
                    <span class="material-symbols-outlined !text-[18px]">filter_list</span> Filter
                </button>
                @if (request()->hasAny(['q', 'status', 'category_id', 'location_id']))
                    <a href="{{ route('assets.index') }}" class="btn btn-ghost" title="Reset filter">
                        <span class="material-symbols-outlined !text-[18px]">close</span>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <x-card title="Daftar Aset" icon="inventory_2" :padding="false">
        <x-slot:actions>
            <span class="text-label-md text-on-surface-variant">{{ number_format($assets->total(), 0, ',', '.') }} aset</span>
        </x-slot:actions>

        @if ($assets->isEmpty())
            <x-empty icon="inventory_2" message="Tidak ada aset yang sesuai dengan filter." />
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Asset Tag</th>
                        <th>Nama Aset</th>
                        <th>Kategori</th>
                        <th>Lokasi</th>
                        <th>Pemegang</th>
                        <th>Kondisi</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($assets as $asset)
                        @php($holder = $asset->currentHolder())
                        <tr>
                            <td><a href="{{ route('assets.show', $asset) }}" class="tag hover:underline">{{ $asset->asset_tag }}</a></td>
                            <td>
                                <a href="{{ route('assets.show', $asset) }}" class="font-semibold text-on-surface hover:underline">{{ $asset->name }}</a>
                                <div class="text-label-sm text-on-surface-variant">
                                    {{ collect([$asset->brand, $asset->model])->filter()->implode(' ') ?: '-' }}
                                    @if ($asset->serial_number) &middot; SN: {{ $asset->serial_number }} @endif
                                </div>
                            </td>
                            <td>{{ $asset->category?->name }}</td>
                            <td>{{ $asset->location?->name }}</td>
                            <td>
                                @if ($holder)
                                    <div class="flex items-center gap-space-xs">
                                        <span class="material-symbols-outlined !text-[16px] text-outline">{{ $asset->activeLoan ? 'schedule' : 'person' }}</span>
                                        {{ $holder->name }}
                                    </div>
                                @else
                                    <span class="text-outline">-</span>
                                @endif
                            </td>
                            <td><x-badge :enum="$asset->condition" /></td>
                            <td><x-badge :enum="$asset->status" /></td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('assets.show', $asset) }}" class="btn btn-ghost btn-sm" title="Detail">
                                    <span class="material-symbols-outlined !text-[18px]">visibility</span>
                                </a>
                                @permission('asset.update')
                                    @unless ($asset->isDisposed())
                                        <a href="{{ route('assets.edit', $asset) }}" class="btn btn-ghost btn-sm" title="Ubah">
                                            <span class="material-symbols-outlined !text-[18px]">edit</span>
                                        </a>
                                    @endunless
                                @endpermission
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="px-space-lg py-space-md border-t border-border-subtle">{{ $assets->links() }}</div>
        @endif
    </x-card>
@endsection
