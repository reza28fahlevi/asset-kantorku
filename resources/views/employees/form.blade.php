@extends('layouts.app')

@php $editing = $employee->exists; @endphp

@section('title', $editing ? 'Ubah Karyawan' : 'Tambah Karyawan')
@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <a href="{{ route('masters.employees.index') }}" class="hover:text-on-surface">Karyawan</a>
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span class="text-on-surface font-semibold">{{ $editing ? $employee->name : 'Tambah' }}</span>
@endsection

@section('content')
<form method="POST" action="{{ $editing ? route('masters.employees.update', $employee) : route('masters.employees.store') }}"
      class="grid grid-cols-1 lg:grid-cols-3 gap-gutter">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="lg:col-span-2 space-y-gutter">
        <x-card title="Identitas" icon="badge">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-space-md">
                <x-field label="NIK / No. Karyawan" name="employee_no" :required="true">
                    <input type="text" name="employee_no" id="employee_no" class="form-input" maxlength="30" required value="{{ old('employee_no', $employee->employee_no) }}">
                </x-field>
                <x-field label="Nama Lengkap" name="name" :required="true">
                    <input type="text" name="name" id="name" class="form-input" maxlength="150" required value="{{ old('name', $employee->name) }}">
                </x-field>
                <x-field label="Email" name="email">
                    <input type="email" name="email" id="email" class="form-input" maxlength="150" value="{{ old('email', $employee->email) }}">
                </x-field>
                <x-field label="Telepon" name="phone">
                    <input type="text" name="phone" id="phone" class="form-input" maxlength="30" value="{{ old('phone', $employee->phone) }}">
                </x-field>
            </div>
        </x-card>

        <x-card title="Organisasi" icon="account_tree">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-space-md">
                <x-field label="Departemen" name="department_id" :required="true">
                    <select name="department_id" id="department_id" class="form-input" required>
                        <option value="">Pilih departemen</option>
                        @foreach ($departments as $d)
                            <option value="{{ $d->id }}" @selected((string) old('department_id', $employee->department_id) === (string) $d->id)>{{ $d->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Jabatan" name="job_title">
                    <input type="text" name="job_title" id="job_title" class="form-input" maxlength="100" value="{{ old('job_title', $employee->job_title) }}">
                </x-field>
                <x-field label="Atasan Langsung" name="manager_employee_id" hint="Dipakai untuk routing approval tingkat manajer.">
                    <select name="manager_employee_id" id="manager_employee_id" class="form-input">
                        <option value="">Tanpa atasan</option>
                        @foreach ($managers as $m)
                            <option value="{{ $m->id }}" @selected((string) old('manager_employee_id', $employee->manager_employee_id) === (string) $m->id)>{{ $m->name }} ({{ $m->employee_no }})</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Lokasi Kerja" name="work_location_id">
                    <select name="work_location_id" id="work_location_id" class="form-input">
                        <option value="">Tidak ditentukan</option>
                        @foreach ($locations as $l)
                            <option value="{{ $l->id }}" @selected((string) old('work_location_id', $employee->work_location_id) === (string) $l->id)>{{ $l->name }}</option>
                        @endforeach
                    </select>
                </x-field>
            </div>
        </x-card>
    </div>

    <div>
        <div class="card lg:sticky lg:top-space-lg">
            <div class="card-header"><h3 class="card-title">Status Kepegawaian</h3></div>
            <div class="card-body space-y-space-md">
                <x-field label="Status" name="employment_status" :required="true">
                    <select name="employment_status" id="employment_status" class="form-input" required>
                        @foreach (\App\Enums\EmploymentStatus::cases() as $s)
                            <option value="{{ $s->value }}" @selected(old('employment_status', $employee->employment_status?->value) === $s->value)>{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Tanggal Mulai" name="start_date">
                    <input type="date" name="start_date" id="start_date" class="form-input" value="{{ old('start_date', $employee->start_date?->format('Y-m-d')) }}">
                </x-field>
                <x-field label="Tanggal Berakhir" name="end_date">
                    <input type="date" name="end_date" id="end_date" class="form-input" value="{{ old('end_date', $employee->end_date?->format('Y-m-d')) }}">
                </x-field>
                <div class="flex flex-col gap-space-sm pt-space-sm border-t border-border-subtle">
                    <button class="btn btn-primary w-full"><span class="material-symbols-outlined !text-[18px]">save</span> Simpan</button>
                    <a href="{{ $editing ? route('masters.employees.show', $employee) : route('masters.employees.index') }}" class="btn btn-ghost w-full">Batal</a>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
