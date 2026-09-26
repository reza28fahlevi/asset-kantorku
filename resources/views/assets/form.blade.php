@extends('layouts.app')

@php($editing = $asset->exists)

@section('title', $editing ? 'Ubah Aset' : 'Registrasi Aset')
@section('subtitle', $editing ? $asset->asset_tag.' - '.$asset->name : 'Daftarkan aset baru ke register perusahaan')

@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <a href="{{ route('assets.index') }}" class="hover:text-on-surface">Register Aset</a>
    @if ($editing)
        <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
        <a href="{{ route('assets.show', $asset) }}" class="hover:text-on-surface">{{ $asset->asset_tag }}</a>
    @endif
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span class="text-on-surface font-semibold">{{ $editing ? 'Ubah' : 'Registrasi' }}</span>
@endsection

@section('content')
<form method="POST" action="{{ $editing ? route('assets.update', $asset) : route('assets.store') }}"
      class="grid grid-cols-1 lg:grid-cols-3 gap-space-lg"
      x-data="{ name: @js(old('name', $asset->name)), cost: @js(old('purchase_cost', $asset->purchase_cost)) }">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="lg:col-span-2 space-y-space-lg">
        <x-card title="Identitas Aset" icon="badge">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-space-md">
                <x-field label="Nama Aset" name="name" :required="true" class="md:col-span-2">
                    <input type="text" id="name" name="name" x-model="name" value="{{ old('name', $asset->name) }}" maxlength="200" class="form-input" required>
                </x-field>
                <x-field label="Kategori" name="asset_category_id" :required="! $editing" :hint="$editing ? 'Kategori tidak dapat diubah setelah registrasi.' : 'Menentukan prefix asset tag.'">
                    @if ($editing)
                        <input type="text" class="form-input bg-surface-container-low" value="{{ $asset->category?->name }}" disabled>
                    @else
                        <select id="asset_category_id" name="asset_category_id" class="form-input" required>
                            <option value="">-- Pilih Kategori --</option>
                            @foreach ($categories as $c)
                                <option value="{{ $c->id }}" @selected((string) old('asset_category_id', $asset->asset_category_id) === (string) $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    @endif
                </x-field>
                <x-field label="Kondisi" name="condition" :required="! $editing" :hint="$editing ? 'Kondisi berubah melalui transaksi (pengembalian/perbaikan).' : null">
                    @if ($editing)
                        <input type="text" class="form-input bg-surface-container-low" value="{{ $asset->condition?->label() }}" disabled>
                    @else
                        <select id="condition" name="condition" class="form-input" required>
                            @foreach (\App\Enums\AssetCondition::cases() as $cond)
                                <option value="{{ $cond->value }}" @selected(old('condition', $asset->condition?->value) === $cond->value)>{{ $cond->label() }}</option>
                            @endforeach
                        </select>
                    @endif
                </x-field>
                <x-field label="Merek" name="brand">
                    <input type="text" id="brand" name="brand" value="{{ old('brand', $asset->brand) }}" maxlength="100" class="form-input">
                </x-field>
                <x-field label="Model" name="model">
                    <input type="text" id="model" name="model" value="{{ old('model', $asset->model) }}" maxlength="100" class="form-input">
                </x-field>
                <x-field label="Serial Number" name="serial_number" class="md:col-span-2">
                    <input type="text" id="serial_number" name="serial_number" value="{{ old('serial_number', $asset->serial_number) }}" maxlength="100" class="form-input font-mono">
                </x-field>
                <x-field label="Spesifikasi" name="specification" class="md:col-span-2">
                    <textarea id="specification" name="specification" rows="3" maxlength="2000" class="form-input">{{ old('specification', $asset->specification) }}</textarea>
                </x-field>
            </div>
        </x-card>

        <x-card title="Penempatan" icon="location_on">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-space-md">
                <x-field label="Lokasi" name="location_id" :required="true">
                    <select id="location_id" name="location_id" class="form-input" required>
                        <option value="">-- Pilih Lokasi --</option>
                        @foreach ($locations as $l)
                            <option value="{{ $l->id }}" @selected((string) old('location_id', $asset->location_id) === (string) $l->id)>{{ $l->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Departemen Pemilik" name="department_id">
                    <select id="department_id" name="department_id" class="form-input">
                        <option value="">-- Tidak ada --</option>
                        @foreach ($departments as $d)
                            <option value="{{ $d->id }}" @selected((string) old('department_id', $asset->department_id) === (string) $d->id)>{{ $d->name }}</option>
                        @endforeach
                    </select>
                </x-field>
            </div>
        </x-card>

        <x-card title="Pembelian & Garansi" icon="receipt_long">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-space-md">
                <x-field label="Vendor" name="vendor_id">
                    <select id="vendor_id" name="vendor_id" class="form-input">
                        <option value="">-- Tidak ada --</option>
                        @foreach ($vendors as $v)
                            <option value="{{ $v->id }}" @selected((string) old('vendor_id', $asset->vendor_id) === (string) $v->id)>{{ $v->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Nilai Beli (Rp)" name="purchase_cost">
                    <input type="number" id="purchase_cost" name="purchase_cost" x-model="cost" value="{{ old('purchase_cost', $asset->purchase_cost) }}" min="0" step="0.01" class="form-input">
                </x-field>
                <x-field label="Tanggal Beli" name="purchase_date">
                    <input type="date" id="purchase_date" name="purchase_date" value="{{ old('purchase_date', $asset->purchase_date?->format('Y-m-d')) }}" class="form-input">
                </x-field>
                <x-field label="Garansi Berakhir" name="warranty_end_date">
                    <input type="date" id="warranty_end_date" name="warranty_end_date" value="{{ old('warranty_end_date', $asset->warranty_end_date?->format('Y-m-d')) }}" class="form-input">
                </x-field>
                <x-field label="Catatan" name="notes" class="md:col-span-2">
                    <textarea id="notes" name="notes" rows="3" maxlength="2000" class="form-input">{{ old('notes', $asset->notes) }}</textarea>
                </x-field>
            </div>
        </x-card>
    </div>

    <aside class="lg:col-span-1">
        <div class="lg:sticky lg:top-space-lg space-y-space-lg">
            <x-card title="Ringkasan" icon="summarize">
                <dl class="dl-grid">
                    <dt>Asset Tag</dt>
                    <dd>
                        @if ($editing)
                            <span class="tag">{{ $asset->asset_tag }}</span>
                        @else
                            <span class="text-outline">Otomatis saat disimpan</span>
                        @endif
                    </dd>
                    <dt>Nama</dt>
                    <dd x-text="name || '-'"></dd>
                    <dt>Nilai Beli</dt>
                    <dd x-text="cost ? 'Rp ' + Math.round(Number(cost)).toLocaleString('id-ID') : '-'"></dd>
                    @if ($editing)
                        <dt>Status</dt>
                        <dd><x-badge :enum="$asset->status" /></dd>
                    @endif
                </dl>
                <p class="form-hint mt-space-md">Kolom bertanda <span class="text-error">*</span> wajib diisi.</p>
                <div class="mt-space-lg flex flex-col gap-space-sm">
                    <button type="submit" class="btn btn-primary w-full">
                        <span class="material-symbols-outlined !text-[18px]">save</span>
                        {{ $editing ? 'Simpan Perubahan' : 'Registrasi Aset' }}
                    </button>
                    <a href="{{ $editing ? route('assets.show', $asset) : route('assets.index') }}" class="btn btn-secondary w-full">Batal</a>
                </div>
            </x-card>
        </div>
    </aside>
</form>
@endsection
