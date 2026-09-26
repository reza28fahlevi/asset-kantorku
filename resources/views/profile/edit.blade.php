@extends('layouts.app')

@section('title', 'Profil Saya')
@section('subtitle', 'Informasi akun dan keamanan')
@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span class="text-on-surface font-semibold">Profil</span>
@endsection

@section('content')
@php $emp = $user->employee; @endphp
<div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter">
    <div class="lg:col-span-2 space-y-gutter">
        <x-card title="Informasi Akun" icon="person">
            <dl class="dl-grid">
                <div><dt>Nama</dt><dd>{{ $user->name }}</dd></div>
                <div><dt>Email</dt><dd>{{ $user->email }}</dd></div>
                <div><dt>Status</dt><dd>@if ($user->is_active)<x-badge color="available">Aktif</x-badge>@else<x-badge color="neutral">Nonaktif</x-badge>@endif</dd></div>
                <div><dt>Login Terakhir</dt><dd>{{ $user->last_login_at?->format('d M Y H:i') ?? '-' }}</dd></div>
                <div class="sm:col-span-2"><dt>Peran</dt>
                    <dd class="flex flex-wrap gap-space-xs mt-1">
                        @forelse ($user->roles as $role)
                            <x-badge color="assigned">{{ $role->display_name }}</x-badge>
                        @empty
                            -
                        @endforelse
                    </dd>
                </div>
            </dl>
        </x-card>

        <x-card title="Data Karyawan" icon="badge">
            @if ($emp)
                <dl class="dl-grid">
                    <div><dt>No. Karyawan</dt><dd class="tag">{{ $emp->employee_no }}</dd></div>
                    <div><dt>Jabatan</dt><dd>{{ $emp->job_title ?? '-' }}</dd></div>
                    <div><dt>Departemen</dt><dd>{{ $emp->department?->name ?? '-' }}</dd></div>
                    <div><dt>Atasan</dt><dd>{{ $emp->manager?->name ?? '-' }}</dd></div>
                    <div><dt>Telepon</dt><dd>{{ $emp->phone ?? '-' }}</dd></div>
                    <div><dt>Tanggal Bergabung</dt><dd>{{ $emp->start_date ? \Illuminate\Support\Carbon::parse($emp->start_date)->format('d M Y') : '-' }}</dd></div>
                </dl>
            @else
                <x-empty icon="person_off" message="Akun ini belum terhubung dengan data karyawan." />
            @endif
        </x-card>
    </div>

    <div>
        <x-card title="Ubah Password" icon="lock" class="lg:sticky lg:top-space-lg">
            <form method="POST" action="{{ route('profile.password') }}" class="space-y-space-md">
                @csrf
                @method('PUT')
                <x-field label="Password Saat Ini" name="current_password" :required="true">
                    <input type="password" id="current_password" name="current_password" class="form-input w-full" autocomplete="current-password" required>
                </x-field>
                <x-field label="Password Baru" name="password" :required="true" hint="Minimal 8 karakter, mengandung huruf dan angka.">
                    <input type="password" id="password" name="password" class="form-input w-full" autocomplete="new-password" required>
                </x-field>
                <x-field label="Konfirmasi Password Baru" name="password_confirmation" :required="true">
                    <input type="password" id="password_confirmation" name="password_confirmation" class="form-input w-full" autocomplete="new-password" required>
                </x-field>
                <button type="submit" class="btn btn-primary w-full"><span class="material-symbols-outlined !text-[18px]">save</span> Simpan Password</button>
            </form>
        </x-card>
    </div>
</div>
@endsection
