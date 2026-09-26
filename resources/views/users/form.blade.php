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
<form method="POST" action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}"
      class="grid grid-cols-1 lg:grid-cols-3 gap-gutter">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="lg:col-span-2 space-y-gutter">
        <x-card title="Akun" icon="account_circle">
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
                            <option value="{{ $e->id }}" @selected((string) old('employee_id', $user->employee_id) === (string) $e->id)>{{ $e->employee_no }} — {{ $e->name }}</option>
                        @endforeach
                    </select>
                </x-field>
            </div>
        </x-card>

        <x-card title="Kata Sandi" icon="key">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-space-md">
                <x-field label="Kata Sandi" name="password" :required="! $editing" :hint="$editing ? 'Kosongkan jika tidak ingin mengubah.' : 'Minimal 8 karakter, kombinasi huruf dan angka.'">
                    <input type="password" name="password" id="password" class="form-input" autocomplete="new-password" @if (! $editing) required @endif>
                </x-field>
                <x-field label="Konfirmasi Kata Sandi" name="password_confirmation">
                    <input type="password" name="password_confirmation" id="password_confirmation" class="form-input" autocomplete="new-password">
                </x-field>
            </div>
        </x-card>

        <x-card title="Role Akses" icon="shield_person">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-space-sm">
                @foreach ($roles as $r)
                    <label class="flex items-start gap-2 p-space-sm rounded border border-border-subtle hover:bg-surface cursor-pointer">
                        <input type="checkbox" name="roles[]" value="{{ $r->id }}" class="rounded border-border-subtle mt-1" @checked(in_array($r->id, $selectedRoles, true))>
                        <span>
                            <span class="text-body-md font-semibold text-on-surface">{{ $r->display_name }}</span>
                            <span class="block text-body-sm text-on-surface-variant">{{ $r->description ?: $r->name }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('roles')<p class="form-error">{{ $message }}</p>@enderror
            @error('roles.*')<p class="form-error">{{ $message }}</p>@enderror
        </x-card>
    </div>

    <div>
        <div class="card lg:sticky lg:top-space-lg">
            <div class="card-header"><h3 class="card-title">Status Akun</h3></div>
            <div class="card-body space-y-space-md">
                <label class="flex items-start gap-2">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-border-subtle mt-1" @checked($isActive)>
                    <span>
                        <span class="text-body-md font-semibold text-on-surface">Akun aktif</span>
                        <span class="block text-body-sm text-on-surface-variant">Akun nonaktif tidak dapat login.</span>
                    </span>
                </label>
                @if ($editing)
                    <dl class="dl-grid">
                        <dt>Login Terakhir</dt><dd>{{ $user->last_login_at?->format('d M Y H:i') ?? '-' }}</dd>
                        <dt>Dibuat</dt><dd>{{ $user->created_at?->format('d M Y') }}</dd>
                    </dl>
                @endif
                <div class="flex flex-col gap-space-sm pt-space-sm border-t border-border-subtle">
                    <button class="btn btn-primary w-full"><span class="material-symbols-outlined !text-[18px]">save</span> Simpan</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-ghost w-full">Batal</a>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
