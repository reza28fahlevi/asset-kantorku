@extends('layouts.app')

@php
    $editing = $model !== null;
    $val = fn (string $field, $default = null) => old($field, $editing ? $model->{$field} : $default);
    $codeMax = $key === 'categories' ? 10 : 20;
    $codeHint = $key === 'categories' ? 'Maks. 10 karakter alfanumerik, otomatis kapital. Dipakai sebagai prefix tag aset.' : 'Huruf, angka, strip atau garis bawah. Otomatis kapital.';
    $isActive = old('_token') ? (bool) old('is_active') : ($editing ? (bool) $model->is_active : true);
@endphp

@section('title', ($editing ? 'Ubah ' : 'Tambah ').$title)
@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span>Master Data</span>
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <a href="{{ route("masters.{$key}.index") }}" class="hover:text-on-surface">{{ $title }}</a>
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span class="text-on-surface font-semibold">{{ $editing ? $model->code : 'Tambah' }}</span>
@endsection

@section('content')
{{-- Dipakai sebagai halaman penuh maupun isi modal (ajax-modal.js mengambil [data-modal-content]) --}}
<div class="card max-w-3xl">
<form method="POST" data-ajax-form action="{{ $editing ? route("masters.{$key}.update", $model->getKey()) : route("masters.{$key}.store") }}"
      data-modal-content data-modal-title="{{ ($editing ? 'Ubah ' : 'Tambah ').$title }}" data-modal-size="md">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="modal-body card-body space-y-space-lg">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-space-md">
            <x-field label="Kode" name="code" :required="true" :hint="$codeHint">
                <input type="text" name="code" id="code" class="form-input uppercase font-mono" maxlength="{{ $codeMax }}" required value="{{ $val('code') }}">
            </x-field>
            <x-field label="Nama" name="name" :required="true">
                <input type="text" name="name" id="name" class="form-input" maxlength="150" required value="{{ $val('name') }}">
            </x-field>

            @if ($key === 'locations')
                <x-field label="Lokasi Induk" name="parent_id" hint="Opsional, untuk struktur gedung/lantai/ruang.">
                    <select name="parent_id" id="parent_id" class="form-input">
                        <option value="">Tanpa induk</option>
                        @foreach ($parents as $p)
                            <option value="{{ $p->id }}" @selected((string) $val('parent_id') === (string) $p->id)>{{ $p->code }} — {{ $p->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Alamat" name="address" class="md:col-span-2">
                    <textarea name="address" id="address" rows="3" class="form-input" maxlength="1000">{{ $val('address') }}</textarea>
                </x-field>
            @endif

            @if ($key === 'categories')
                <x-field label="Umur Manfaat (bulan)" name="useful_life_months">
                    <input type="number" name="useful_life_months" id="useful_life_months" class="form-input" min="1" max="600" value="{{ $val('useful_life_months') }}">
                </x-field>
                <div class="flex items-end pb-2">
                    <label class="inline-flex items-center gap-2 text-body-md">
                        <input type="checkbox" name="requires_serial" value="1" class="rounded border-border-subtle"
                               @checked(old('_token') ? old('requires_serial') : ($editing && $model->requires_serial))>
                        Wajib serial number
                    </label>
                </div>
                <x-field label="Deskripsi" name="description" class="md:col-span-2">
                    <textarea name="description" id="description" rows="3" class="form-input" maxlength="1000">{{ $val('description') }}</textarea>
                </x-field>
            @endif

            @if ($key === 'vendors')
                <x-field label="Contact Person" name="contact_person">
                    <input type="text" name="contact_person" id="contact_person" class="form-input" maxlength="100" value="{{ $val('contact_person') }}">
                </x-field>
                <x-field label="Telepon" name="phone">
                    <input type="text" name="phone" id="phone" class="form-input" maxlength="30" value="{{ $val('phone') }}">
                </x-field>
                <x-field label="Email" name="email">
                    <input type="email" name="email" id="email" class="form-input" maxlength="150" value="{{ $val('email') }}">
                </x-field>
                <x-field label="NPWP" name="tax_number">
                    <input type="text" name="tax_number" id="tax_number" class="form-input" maxlength="30" value="{{ $val('tax_number') }}">
                </x-field>
                <x-field label="Alamat" name="address" class="md:col-span-2">
                    <textarea name="address" id="address" rows="2" class="form-input" maxlength="1000">{{ $val('address') }}</textarea>
                </x-field>
                <x-field label="Catatan" name="notes" class="md:col-span-2">
                    <textarea name="notes" id="notes" rows="2" class="form-input" maxlength="2000">{{ $val('notes') }}</textarea>
                </x-field>
            @endif
        </div>

        <label class="flex items-start gap-2 pt-space-md border-t border-border-subtle">
            <input type="checkbox" name="is_active" value="1" class="rounded border-border-subtle mt-1" @checked($isActive)>
            <span>
                <span class="text-body-md font-semibold text-on-surface">Aktif</span>
                <span class="block text-body-sm text-on-surface-variant">Data nonaktif tidak muncul pada pilihan transaksi baru.</span>
            </span>
        </label>
    </div>

    <div class="modal-footer flex items-center justify-end gap-space-sm px-space-lg py-space-md border-t border-border-subtle">
        <a href="{{ route("masters.{$key}.index") }}" class="btn btn-ghost" data-modal-close>Batal</a>
        <button class="btn btn-primary"><span class="material-symbols-outlined !text-[18px]">save</span> Simpan</button>
    </div>
</form>
</div>
@endsection
