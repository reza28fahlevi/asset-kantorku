@extends('layouts.app')

@section('title', $employee->name)
@section('subtitle', trim(($employee->job_title ?? '').' · '.($employee->department?->name ?? ''), ' ·'))
@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <a href="{{ route('masters.employees.index') }}" class="hover:text-on-surface">Karyawan</a>
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span class="text-on-surface font-semibold">{{ $employee->employee_no }}</span>
@endsection

@section('actions')
    @permission('employee.manage')
        <a href="{{ route('masters.employees.edit', $employee) }}" class="btn btn-secondary" data-modal data-modal-title="Ubah Karyawan" data-modal-size="lg">
            <span class="material-symbols-outlined !text-[18px]">edit</span> Ubah
        </a>
    @endpermission
@endsection

@section('content')
<div class="grid grid-cols-1 sm:grid-cols-3 gap-gutter mb-gutter">
    <x-stat label="Aset Ditugaskan" :value="$employee->activeAssignments->count()" icon="devices" color="assigned" />
    <x-stat label="Pinjaman Aktif" :value="$employee->activeLoans->count()" icon="swap_horiz" color="loan" />
    <x-stat label="Bawahan Langsung" :value="$employee->subordinates->count()" icon="groups" color="neutral" />
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter">
    <div class="lg:col-span-2 space-y-gutter">
        <x-card title="Aset Ditugaskan" icon="devices" :padding="false">
            @if ($employee->activeAssignments->isEmpty())
                <x-empty icon="inventory_2" message="Tidak ada aset yang sedang ditugaskan." />
            @else
                <table class="table">
                    <thead><tr><th>Tag</th><th>Aset</th><th>Kategori</th><th>Sejak</th><th>Kondisi</th></tr></thead>
                    <tbody>
                        @foreach ($employee->activeAssignments as $a)
                            <tr>
                                <td><span class="tag">{{ $a->asset?->asset_tag }}</span></td>
                                <td>{{ $a->asset?->name }}</td>
                                <td>{{ $a->asset?->category?->name }}</td>
                                <td class="whitespace-nowrap">{{ $a->assigned_at?->format('d M Y') }}</td>
                                <td>{{ $a->condition_out?->label() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>

        <x-card title="Pinjaman Aktif" icon="swap_horiz" :padding="false">
            @if ($employee->activeLoans->isEmpty())
                <x-empty icon="event_available" message="Tidak ada pinjaman aktif." />
            @else
                <table class="table">
                    <thead><tr><th>Tag</th><th>Aset</th><th>Dipinjam</th><th>Jatuh Tempo</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($employee->activeLoans as $l)
                            <tr>
                                <td><span class="tag">{{ $l->asset?->asset_tag }}</span></td>
                                <td>{{ $l->asset?->name }}<div class="text-label-sm text-on-surface-variant">{{ $l->asset?->category?->name }}</div></td>
                                <td class="whitespace-nowrap">{{ $l->checked_out_at?->format('d M Y') }}</td>
                                <td class="whitespace-nowrap {{ $l->due_at?->isPast() ? 'text-error font-semibold' : '' }}">{{ $l->due_at?->format('d M Y') }}</td>
                                <td><x-badge :enum="$l->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>

        @if ($employee->subordinates->isNotEmpty())
            <x-card title="Bawahan Langsung" icon="groups" :padding="false">
                <table class="table">
                    <thead><tr><th>NIK</th><th>Nama</th><th>Jabatan</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($employee->subordinates as $s)
                            <tr>
                                <td><span class="tag">{{ $s->employee_no }}</span></td>
                                <td><a href="{{ route('masters.employees.show', $s) }}" class="text-secondary hover:underline">{{ $s->name }}</a></td>
                                <td>{{ $s->job_title ?? '-' }}</td>
                                <td><x-badge :enum="$s->employment_status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>
        @endif
    </div>

    <div class="space-y-gutter">
        <x-card title="Profil" icon="person">
            <x-slot:actions><x-badge :enum="$employee->employment_status" /></x-slot:actions>
            <dl class="dl-grid">
                <dt>NIK</dt><dd><span class="tag">{{ $employee->employee_no }}</span></dd>
                <dt>Email</dt><dd>{{ $employee->email ?? '-' }}</dd>
                <dt>Telepon</dt><dd>{{ $employee->phone ?? '-' }}</dd>
                <dt>Departemen</dt><dd>{{ $employee->department?->name ?? '-' }}</dd>
                <dt>Jabatan</dt><dd>{{ $employee->job_title ?? '-' }}</dd>
                <dt>Atasan</dt>
                <dd>
                    @if ($employee->manager)
                        <a href="{{ route('masters.employees.show', $employee->manager) }}" class="text-secondary hover:underline">{{ $employee->manager->name }}</a>
                    @else - @endif
                </dd>
                <dt>Lokasi Kerja</dt><dd>{{ $employee->workLocation?->name ?? '-' }}</dd>
                <dt>Mulai</dt><dd>{{ $employee->start_date?->format('d M Y') ?? '-' }}</dd>
                <dt>Berakhir</dt><dd>{{ $employee->end_date?->format('d M Y') ?? '-' }}</dd>
            </dl>
        </x-card>

        <x-card title="Akun Pengguna" icon="manage_accounts">
            @if ($employee->user)
                <dl class="dl-grid">
                    <dt>Email Login</dt><dd>{{ $employee->user->email }}</dd>
                    <dt>Status</dt>
                    <dd>@if ($employee->user->is_active)<x-badge color="available">Aktif</x-badge>@else<x-badge color="neutral">Nonaktif</x-badge>@endif</dd>
                    <dt>Role</dt>
                    <dd class="flex flex-wrap gap-1">
                        @forelse ($employee->user->roles as $role)
                            <x-badge color="neutral">{{ $role->display_name }}</x-badge>
                        @empty - @endforelse
                    </dd>
                    <dt>Login Terakhir</dt><dd>{{ $employee->user->last_login_at?->format('d M Y H:i') ?? '-' }}</dd>
                </dl>
                @permission('user.manage')
                    <a href="{{ route('admin.users.edit', $employee->user) }}" class="btn btn-secondary btn-sm w-full mt-space-md" data-modal data-modal-title="Ubah Pengguna" data-modal-size="lg">Kelola Akun</a>
                @endpermission
            @else
                <p class="text-body-sm text-on-surface-variant">Karyawan ini belum memiliki akun login.</p>
                @permission('user.manage')
                    <a href="{{ route('admin.users.create') }}" class="btn btn-secondary btn-sm w-full mt-space-md" data-modal data-modal-title="Tambah Pengguna" data-modal-size="lg">Buat Akun</a>
                @endpermission
            @endif
        </x-card>
    </div>
</div>
@endsection
