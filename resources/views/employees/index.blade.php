@extends('layouts.app')

@section('title', 'Karyawan')
@section('subtitle', 'Data master karyawan, struktur atasan, dan akun pengguna')
@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span>Master Data</span>
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span class="text-on-surface font-semibold">Karyawan</span>
@endsection

@section('actions')
    @permission('employee.manage')
        <a href="{{ route('masters.employees.create') }}" class="btn btn-primary" data-modal data-modal-size="lg">
            <span class="material-symbols-outlined !text-[18px]">person_add</span> Tambah Karyawan
        </a>
    @endpermission
@endsection

@section('content')
<div class="card">
    <form method="GET" class="flex flex-wrap items-end gap-space-md px-space-lg py-space-md border-b border-border-subtle">
        <div class="flex-1 min-w-[220px]">
            <label class="form-label">Cari</label>
            <input type="text" name="q" value="{{ request('q') }}" class="form-input" placeholder="Nama, NIK, atau email">
        </div>
        <div class="w-56">
            <label class="form-label">Departemen</label>
            <select name="department_id" class="form-input">
                <option value="">Semua departemen</option>
                @foreach ($departments as $d)
                    <option value="{{ $d->id }}" @selected((string) request('department_id') === (string) $d->id)>{{ $d->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="w-44">
            <label class="form-label">Status</label>
            <select name="status" class="form-input">
                <option value="">Semua</option>
                @foreach (\App\Enums\EmploymentStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-secondary"><span class="material-symbols-outlined !text-[18px]">filter_list</span> Filter</button>
        @if (request()->hasAny(['q', 'department_id', 'status']))
            <a href="{{ route('masters.employees.index') }}" class="btn btn-ghost">Reset</a>
        @endif
    </form>

    @if ($employees->isEmpty())
        <x-empty icon="group_off" message="Data karyawan tidak ditemukan." />
    @else
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>NIK</th>
                        <th>Nama</th>
                        <th>Departemen</th>
                        <th>Jabatan</th>
                        <th>Atasan</th>
                        <th>Akun</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($employees as $e)
                        <tr>
                            <td><span class="tag">{{ $e->employee_no }}</span></td>
                            <td>
                                <a href="{{ route('masters.employees.show', $e) }}" class="font-semibold text-on-surface hover:text-secondary">{{ $e->name }}</a>
                                <div class="text-label-sm text-on-surface-variant">{{ $e->email ?? '-' }}</div>
                            </td>
                            <td>{{ $e->department?->name ?? '-' }}</td>
                            <td>{{ $e->job_title ?? '-' }}</td>
                            <td>{{ $e->manager?->name ?? '-' }}</td>
                            <td>
                                @if ($e->user)
                                    <span class="inline-flex items-center gap-1 text-body-sm"><span class="material-symbols-outlined !text-[16px] text-status-available">verified_user</span>Ada</span>
                                @else
                                    <span class="text-body-sm text-on-surface-variant">-</span>
                                @endif
                            </td>
                            <td><x-badge :enum="$e->employment_status" /></td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('masters.employees.show', $e) }}" class="btn btn-ghost btn-sm">Detail</a>
                                @permission('employee.manage')
                                    <a href="{{ route('masters.employees.edit', $e) }}" class="btn btn-ghost btn-sm" data-modal data-modal-title="Ubah Karyawan" data-modal-size="lg">Ubah</a>
                                @endpermission
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-space-lg py-space-md border-t border-border-subtle">{{ $employees->links() }}</div>
    @endif
</div>
@endsection
