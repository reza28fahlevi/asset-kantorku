@extends('layouts.app')

@section('title', 'Role & Hak Akses')
@section('subtitle', 'Kelompok hak akses yang diberikan kepada pengguna')
@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span>Administrasi</span>
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span class="text-on-surface font-semibold">Role</span>
@endsection

@section('actions')
    <a href="{{ route('admin.roles.create') }}" class="btn btn-primary" data-modal data-modal-size="xl">
        <span class="material-symbols-outlined !text-[18px]">add</span> Tambah Role
    </a>
@endsection

@section('content')
<x-card :padding="false">
    @if ($roles->isEmpty())
        <x-empty icon="shield" message="Belum ada role." />
    @else
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Role</th><th>Kode</th><th>Deskripsi</th><th class="text-right">Pengguna</th><th class="text-right">Permission</th><th>Tipe</th><th></th></tr></thead>
                <tbody>
                    @foreach ($roles as $role)
                        <tr>
                            <td class="font-semibold text-on-surface">{{ $role->display_name }}</td>
                            <td><span class="tag">{{ $role->name }}</span></td>
                            <td class="text-body-sm text-on-surface-variant max-w-md">{{ $role->description ?? '-' }}</td>
                            <td class="text-right">{{ $role->users_count }}</td>
                            <td class="text-right">{{ $role->permissions_count }}</td>
                            <td>@if ($role->is_system)<x-badge color="pending">Bawaan Sistem</x-badge>@else<x-badge color="neutral">Kustom</x-badge>@endif</td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-ghost btn-sm" data-modal data-modal-title="Ubah Role" data-modal-size="xl">Ubah</a>
                                @if (! $role->is_system && $role->users_count === 0)
                                    <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="inline" data-ajax-form
                                          data-confirm="Hapus role {{ $role->display_name }}?" data-confirm-text="Role yang dihapus tidak dapat dikembalikan." data-confirm-button="Ya, hapus">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-sm text-error">Hapus</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-card>
@endsection
