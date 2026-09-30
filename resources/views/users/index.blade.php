@extends('layouts.app')

@section('title', 'Pengguna')
@section('subtitle', 'Akun login, keterkaitan karyawan, dan role akses')
@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span>Administrasi</span>
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span class="text-on-surface font-semibold">Pengguna</span>
@endsection

@section('actions')
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary" data-modal data-modal-size="lg">
        <span class="material-symbols-outlined !text-[18px]">person_add</span> Tambah Pengguna
    </a>
@endsection

@section('content')
<div class="card">
    <form method="GET" class="flex flex-wrap items-end gap-space-md px-space-lg py-space-md border-b border-border-subtle">
        <div class="flex-1 min-w-[220px]">
            <label class="form-label">Cari</label>
            <input type="text" name="q" value="{{ request('q') }}" class="form-input" placeholder="Nama atau email">
        </div>
        <div class="w-56">
            <label class="form-label">Role</label>
            <select name="role" class="form-input">
                <option value="">Semua role</option>
                @foreach ($roles as $r)
                    <option value="{{ $r->name }}" @selected(request('role') === $r->name)>{{ $r->display_name }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-secondary"><span class="material-symbols-outlined !text-[18px]">filter_list</span> Filter</button>
        @if (request()->hasAny(['q', 'role']))
            <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">Reset</a>
        @endif
    </form>

    @if ($users->isEmpty())
        <x-empty icon="person_off" message="Pengguna tidak ditemukan." />
    @else
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr><th>Nama</th><th>Karyawan</th><th>Role</th><th>Login Terakhir</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($users as $u)
                        <tr>
                            <td>
                                <div class="font-semibold text-on-surface">{{ $u->name }}</div>
                                <div class="text-label-sm text-on-surface-variant">{{ $u->email }}</div>
                            </td>
                            <td>
                                @if ($u->employee)
                                    <span class="tag">{{ $u->employee->employee_no }}</span>
                                    <div class="text-body-sm text-on-surface-variant">{{ $u->employee->department?->name }}</div>
                                @else
                                    <span class="text-on-surface-variant">-</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($u->roles as $role)
                                        <x-badge color="neutral">{{ $role->display_name }}</x-badge>
                                    @endforeach
                                </div>
                            </td>
                            <td class="whitespace-nowrap text-body-sm">{{ $u->last_login_at?->format('d M Y H:i') ?? '-' }}</td>
                            <td>
                                @if ($u->is_active)<x-badge color="available">Aktif</x-badge>@else<x-badge color="neutral">Nonaktif</x-badge>@endif
                            </td>
                            <td class="text-right"><a href="{{ route('admin.users.edit', $u) }}" class="btn btn-ghost btn-sm" data-modal data-modal-title="Ubah Pengguna" data-modal-size="lg">Ubah</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-space-lg py-space-md border-t border-border-subtle">{{ $users->links() }}</div>
    @endif
</div>
@endsection
