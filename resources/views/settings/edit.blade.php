@extends('layouts.app')

@section('title', 'Pengaturan Sistem')
@section('subtitle', 'Parameter umum aplikasi, approval, peminjaman, dan lampiran')
@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span>Administrasi</span>
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span class="text-on-surface font-semibold">Pengaturan</span>
@endsection

@section('actions')
    <button type="button" class="btn btn-primary" data-modal="#settings-modal">
        <span class="material-symbols-outlined !text-[18px]">edit</span> Ubah Pengaturan
    </button>
@endsection

@section('content')
@php
    $escalationId = (string) ($settings['approval.escalation_employee_id'] ?? '');
    $escalation = $escalationId !== '' ? $employees->first(fn ($e) => (string) $e->id === $escalationId) : null;
@endphp
<div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter">
    <div class="lg:col-span-2 space-y-gutter">
        <x-card title="Umum" icon="apartment">
            <dl class="dl-grid">
                <div><dt>Nama Perusahaan</dt><dd>{{ $settings['app.company_name'] ?? '-' }}</dd></div>
            </dl>
        </x-card>

        <x-card title="Approval" icon="fact_check">
            <dl class="dl-grid">
                <div class="sm:col-span-2"><dt>Approver Eskalasi</dt><dd>{{ $escalation?->optionLabel() ?? 'Tidak ada' }}</dd></div>
            </dl>
            <p class="form-hint">Dipakai bila approver pada suatu tahap tidak tersedia (mis. atasan kosong atau nonaktif).</p>
        </x-card>

        <x-card title="Peminjaman & Lampiran" icon="tune">
            <dl class="dl-grid">
                <div><dt>Durasi Maksimal Peminjaman</dt><dd>{{ $settings['loan.max_duration_days'] ?? 14 }} hari</dd></div>
                <div><dt>Ukuran Maksimal Lampiran</dt><dd>{{ number_format((int) ($settings['attachment.max_size_kb'] ?? 5120), 0, ',', '.') }} KB</dd></div>
            </dl>
        </x-card>
    </div>

    <div>
        <div class="card lg:sticky lg:top-space-lg">
            <div class="card-header"><h3 class="card-title">Perubahan</h3></div>
            <div class="card-body space-y-space-md">
                <p class="text-body-sm text-on-surface-variant">Setiap perubahan pengaturan dicatat pada audit log beserta nilai sebelum dan sesudahnya.</p>
                <button type="button" class="btn btn-primary w-full" data-modal="#settings-modal">
                    <span class="material-symbols-outlined !text-[18px]">edit</span> Ubah Pengaturan
                </button>
                @permission('audit.view')
                    <a href="{{ route('admin.audit.index', ['action' => 'settings_updated']) }}" class="btn btn-ghost w-full">Riwayat Perubahan</a>
                @endpermission
            </div>
        </div>
    </div>
</div>

<template id="settings-modal">
    <form method="POST" data-ajax-form action="{{ route('admin.settings.update') }}" data-modal-content data-modal-title="Ubah Pengaturan Sistem" data-modal-size="md">
        @csrf
        @method('PUT')
        <div class="modal-body card-body space-y-space-md">
            <x-field label="Nama Perusahaan" name="app_company_name" :required="true">
                <input type="text" name="app_company_name" id="app_company_name" class="form-input" maxlength="150" required
                       value="{{ old('app_company_name', $settings['app.company_name'] ?? '') }}">
            </x-field>
            <x-field label="Approver Eskalasi" name="approval_escalation_employee_id"
                     hint="Dipakai bila approver pada suatu tahap tidak tersedia (mis. atasan kosong atau nonaktif). Hanya karyawan dengan akun approver.">
                <select name="approval_escalation_employee_id" id="approval_escalation_employee_id" class="form-input">
                    <option value="">Tidak ada</option>
                    @foreach ($employees as $e)
                        <option value="{{ $e->id }}" @selected((string) old('approval_escalation_employee_id', $settings['approval.escalation_employee_id'] ?? '') === (string) $e->id)>
                            {{ $e->optionLabel() }}
                        </option>
                    @endforeach
                </select>
            </x-field>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-space-md">
                <x-field label="Durasi Maksimal Peminjaman (hari)" name="loan_max_duration_days" :required="true">
                    <input type="number" name="loan_max_duration_days" id="loan_max_duration_days" class="form-input" min="1" max="365" required
                           value="{{ old('loan_max_duration_days', $settings['loan.max_duration_days'] ?? 14) }}">
                </x-field>
                <x-field label="Ukuran Maksimal Lampiran (KB)" name="attachment_max_size_kb" :required="true" hint="100 – 20480 KB">
                    <input type="number" name="attachment_max_size_kb" id="attachment_max_size_kb" class="form-input" min="100" max="20480" required
                           value="{{ old('attachment_max_size_kb', $settings['attachment.max_size_kb'] ?? 5120) }}">
                </x-field>
            </div>
        </div>
        <div class="modal-footer flex items-center justify-end gap-space-sm px-space-lg py-space-md border-t border-border-subtle">
            <button type="button" class="btn btn-ghost" data-modal-close>Batal</button>
            <button class="btn btn-primary"><span class="material-symbols-outlined !text-[18px]">save</span> Simpan Pengaturan</button>
        </div>
    </form>
</template>
@endsection
