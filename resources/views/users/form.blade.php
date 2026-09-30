@extends('layouts.app')

@php
    $editing = $user->exists;
    $selectedRoles = collect(old('roles', $editing ? $user->roles->pluck('id')->all() : []))->map(fn ($v) => (int) $v)->all();
    $isActive = old('_token') ? (bool) old('is_active') : (bool) $user->is_active;
@endphp

@section('title', $editing ? 'Ubah Pengguna' : 'Tambah Pengguna')
@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span>Administrasi</span>
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <a href="{{ route('admin.users.index') }}" class="hover:text-on-surface">Pengguna</a>
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span class="text-on-surface font-semibold">{{ $editing ? $user->name : 'Tambah' }}</span>
@endsection

@section('content')
{{-- Dipakai sebagai halaman penuh maupun isi modal (ajax-modal.js mengambil [data-modal-content]) --}}
<div class="card max-w-4xl">
<form method="POST" data-ajax-form action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}"
      data-modal-content data-modal-title="{{ $editing ? 'Ubah Pengguna' : 'Tambah Pengguna' }}" data-modal-size="lg">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="modal-body card-body space-y-space-lg">
        <section>
            <h4 class="text-label-md uppercase text-outline mb-space-sm">Akun</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-space-md">
                <x-field label="Nama" name="name" :required="true">
                    <input type="text" name="name" id="name" class="form-input" maxlength="150" required value="{{ old('name', $user->name) }}">
                </x-field>
                <x-field label="Email Login" name="email" :required="true">
                    <input type="email" name="email" id="email" class="form-input" maxlength="150" required value="{{ old('email', $user->email) }}">
                </x-field>
                <x-field label="Karyawan Terkait" name="employee_id" hint="Diperlukan agar pengguna dapat mengajukan permintaan & menjadi approver." class="md:col-span-2">
                    <select name="employee_id" id="employee_id" class="form-input">
                        <option value="">Tidak terkait karyawan</option>
                        @foreach ($employees as $e)
                            <option value="{{ $e->id }}" @selected((string) old('employee_id', $user->employee_id) === (string) $e->id)>{{ $e->optionLabel() }}</option>
                        @endforeach
                    </select>
                </x-field>
            </div>
            <label class="flex items-start gap-2 mt-space-md">
                <input type="checkbox" name="is_active" value="1" class="rounded border-border-subtle mt-1" @checked($isActive)>
                <span>
                    <span class="text-body-md font-semibold text-on-surface">Akun aktif</span>
                    <span class="block text-body-sm text-on-surface-variant">Akun nonaktif tidak dapat login.</span>
                </span>
            </label>
            @if ($editing)
                <p class="mt-space-sm text-body-sm text-on-surface-variant">
                    Login terakhir: {{ $user->last_login_at?->format('d M Y H:i') ?? '-' }} · Dibuat: {{ $user->created_at?->format('d M Y') }}
                </p>
            @endif
        </section>

        <section class="pt-space-md border-t border-border-subtle">
            <h4 class="text-label-md uppercase text-outline mb-space-sm">Kata Sandi</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-space-md">
                <x-field label="Kata Sandi" name="password" :required="! $editing" :hint="$editing ? 'Kosongkan jika tidak ingin mengubah.' : 'Minimal 8 karakter, kombinasi huruf dan angka.'">
                    <input type="password" name="password" id="password" class="form-input" autocomplete="new-password" @if (! $editing) required @endif>
                </x-field>
                <x-field label="Konfirmasi Kata Sandi" name="password_confirmation">
                    <input type="password" name="password_confirmation" id="password_confirmation" class="form-input" autocomplete="new-password">
                </x-field>
            </div>
        </section>

        @php
            $rolePermissions = $roles->mapWithKeys(fn ($r) => [$r->id => $r->permissions->map(fn ($p) => ['id' => $p->id, 'label' => $p->display_name ?: $p->name, 'group' => $p->group_name ?: 'Umum'])->values()]);
        @endphp
        <section class="pt-space-md border-t border-border-subtle">
            <h4 class="text-label-md uppercase text-outline mb-space-sm">Role Akses (Multi Role)</h4>
            <div x-data="rolePicker(@js($selectedRoles), @js($rolePermissions))">
            <p class="text-body-sm text-on-surface-variant mb-space-md">Pilih satu atau lebih role. Hak akses user adalah gabungan dari seluruh role yang dipilih.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-space-sm">
                @foreach ($roles as $r)
                    <label class="flex items-start gap-2 p-space-sm rounded border cursor-pointer transition-colors"
                           :class="selected.includes({{ $r->id }}) ? 'border-secondary bg-secondary-fixed/30' : 'border-border-subtle hover:bg-surface-subtle'">
                        <input type="checkbox" name="roles[]" value="{{ $r->id }}" x-model.number="selected" class="rounded border-border-strong mt-1 text-primary-container focus:ring-secondary">
                        <span class="flex-1">
                            <span class="flex items-center justify-between gap-2">
                                <span class="text-body-md font-semibold text-on-surface">{{ $r->display_name }}</span>
                                <span class="text-label-sm font-normal text-outline">{{ $r->permissions->count() }} hak</span>
                            </span>
                            <span class="block text-body-sm text-on-surface-variant">{{ $r->description ?: $r->name }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            <div class="mt-space-lg rounded-lg bg-surface-subtle p-space-md">
                <p class="text-label-sm uppercase text-outline mb-space-sm">
                    Hak akses efektif · <span x-text="selected.length"></span> role · <span x-text="effective().length"></span> permission
                </p>
                <p x-show="!selected.length" class="text-body-sm text-outline">Belum ada role dipilih.</p>
                <div class="space-y-space-sm max-h-64 overflow-y-auto">
                    <template x-for="[group, items] in grouped()" :key="group">
                        <div>
                            <p class="text-label-md text-on-surface" x-text="group"></p>
                            <div class="flex flex-wrap gap-1 mt-1">
                                <template x-for="p in items" :key="p.id">
                                    <span class="px-2 py-0.5 rounded-full bg-surface-card ring-1 ring-border-subtle text-label-sm font-normal" x-text="p.label"></span>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            @error('roles')<p class="form-error">{{ $message }}</p>@enderror
            @error('roles.*')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </section>
    </div>

    <div class="modal-footer flex items-center justify-end gap-space-sm px-space-lg py-space-md border-t border-border-subtle">
        <a href="{{ route('admin.users.index') }}" class="btn btn-ghost" data-modal-close>Batal</a>
        <button class="btn btn-primary"><span class="material-symbols-outlined !text-[18px]">save</span> Simpan</button>
    </div>

    {{-- Script di dalam form agar ikut dijalankan saat form dimuat ke modal --}}
    <script>
        function rolePicker(selected, rolePermissions) {
            return {
                selected: selected,
                effective() {
                    const map = new Map();
                    this.selected.forEach(id => (rolePermissions[id] || []).forEach(p => map.set(p.id, p)));
                    return [...map.values()];
                },
                grouped() {
                    const groups = {};
                    this.effective().forEach(p => (groups[p.group] ??= []).push(p));
                    return Object.entries(groups).sort();
                },
            };
        }
    </script>
</form>
</div>
@endsection
