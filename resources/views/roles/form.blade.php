@extends('layouts.app')

@php
    $editing = $role->exists;
    $selected = collect(old('permissions', $editing ? $role->permissions->pluck('id')->all() : []))->map(fn ($v) => (int) $v)->all();
@endphp

@section('title', $editing ? 'Ubah Role' : 'Tambah Role')
@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span>Administrasi</span>
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <a href="{{ route('admin.roles.index') }}" class="hover:text-on-surface">Role</a>
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span class="text-on-surface font-semibold">{{ $editing ? $role->display_name : 'Tambah' }}</span>
@endsection

@section('content')
{{-- Dipakai sebagai halaman penuh maupun isi modal (ajax-modal.js mengambil [data-modal-content]) --}}
<div class="card max-w-5xl">
<form method="POST" action="{{ $editing ? route('admin.roles.update', $role) : route('admin.roles.store') }}" data-ajax-form
      data-modal-content data-modal-title="{{ $editing ? 'Ubah Role' : 'Tambah Role' }}" data-modal-size="xl"
      x-data="{ count: {{ count($selected) }}, recount() { this.count = this.$root.querySelectorAll('input[name=\'permissions[]\']:checked').length } }"
      @change="recount()">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="modal-body card-body space-y-space-lg">
        <section class="grid grid-cols-1 md:grid-cols-3 gap-space-md">
            <x-field label="Kode Role" name="name" :required="! $role->is_system" :hint="$role->is_system ? 'Kode role bawaan sistem tidak dapat diubah.' : 'Huruf kecil, angka, garis bawah.'">
                <input type="text" name="name" id="name" class="form-input font-mono" maxlength="50" value="{{ old('name', $role->name) }}"
                       @if ($role->is_system) disabled @else required pattern="[a-z0-9_]+" @endif>
            </x-field>
            <x-field label="Nama Tampilan" name="display_name" :required="true">
                <input type="text" name="display_name" id="display_name" class="form-input" maxlength="100" required value="{{ old('display_name', $role->display_name) }}">
            </x-field>
            <x-field label="Deskripsi" name="description">
                <textarea name="description" id="description" rows="3" class="form-input" maxlength="1000">{{ old('description', $role->description) }}</textarea>
            </x-field>
        </section>

        <section class="pt-space-md border-t border-border-subtle">
            <h4 class="text-label-md uppercase text-outline mb-space-sm">Hak Akses per Modul</h4>
            <div class="space-y-space-lg">
                @foreach ($permissionGroups as $group => $permissions)
                    <div x-data class="border border-border-subtle rounded">
                        <div class="flex items-center justify-between px-space-md py-space-sm bg-surface border-b border-border-subtle">
                            <span class="text-label-md font-semibold uppercase tracking-wide text-on-surface">{{ $group ?: 'Umum' }}</span>
                            <div class="flex gap-space-sm text-label-sm">
                                <button type="button" class="text-secondary hover:underline"
                                        @click="$el.closest('.rounded').querySelectorAll('input[type=checkbox]').forEach(c => c.checked = true); $dispatch('change')">Pilih semua</button>
                                <button type="button" class="text-on-surface-variant hover:underline"
                                        @click="$el.closest('.rounded').querySelectorAll('input[type=checkbox]').forEach(c => c.checked = false); $dispatch('change')">Kosongkan</button>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-space-lg gap-y-space-sm p-space-md">
                            @foreach ($permissions as $p)
                                <label class="flex items-start gap-2 cursor-pointer">
                                    <input type="checkbox" name="permissions[]" value="{{ $p->id }}" class="rounded border-border-subtle mt-1" @checked(in_array($p->id, $selected, true))>
                                    <span>
                                        <span class="text-body-md text-on-surface">{{ $p->display_name }}</span>
                                        <span class="block font-mono text-label-sm text-on-surface-variant">{{ $p->name }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            @error('permissions')<p class="form-error">{{ $message }}</p>@enderror
            @error('permissions.*')<p class="form-error">{{ $message }}</p>@enderror
        </section>
    </div>

    <div class="modal-footer flex items-center justify-between gap-space-sm px-space-lg py-space-md border-t border-border-subtle">
        <span class="text-body-sm text-on-surface-variant">Permission dipilih: <span class="font-semibold text-on-surface" x-text="count">{{ count($selected) }}</span></span>
        <div class="flex items-center gap-space-sm">
            <a href="{{ route('admin.roles.index') }}" class="btn btn-ghost" data-modal-close>Batal</a>
            <button class="btn btn-primary"><span class="material-symbols-outlined !text-[18px]">save</span> Simpan</button>
        </div>
    </div>
</form>
</div>
@endsection
