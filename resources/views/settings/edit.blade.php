@extends('layouts.app')

@section('title', 'Pengaturan Sistem')
@section('subtitle', 'Parameter umum aplikasi, approval, peminjaman, dan lampiran')
@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span>Administrasi</span>
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span class="text-on-surface font-semibold">Pengaturan</span>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.settings.update') }}" class="grid grid-cols-1 lg:grid-cols-3 gap-gutter">
    @csrf
    @method('PUT')

    <div class="lg:col-span-2 space-y-gutter">
        <x-card title="Umum" icon="apartment">
            <x-field label="Nama Perusahaan" name="app_company_name" :required="true">
                <input type="text" name="app_company_name" id="app_company_name" class="form-input" maxlength="150" required
                       value="{{ old('app_company_name', $settings['app.company_name'] ?? '') }}">
            </x-field>
        </x-card>

        <x-card title="Approval" icon="fact_check">
            <x-field label="Approver Eskalasi" name="approval_escalation_employee_id"
                     hint="Dipakai bila approver pada suatu tahap tidak tersedia (mis. atasan kosong atau nonaktif). Hanya karyawan dengan akun approver.">
                <select name="approval_escalation_employee_id" id="approval_escalation_employee_id" class="form-input">
                    <option value="">Tidak ada</option>
                    @foreach ($employees as $e)
                        <option value="{{ $e->id }}" @selected((string) old('approval_escalation_employee_id', $settings['approval.escalation_employee_id'] ?? '') === (string) $e->id)>
                            {{ $e->name }} ({{ $e->employee_no }})
                        </option>
                    @endforeach
                </select>
            </x-field>
        </x-card>

        <x-card title="Peminjaman & Lampiran" icon="tune">
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
        </x-card>
    </div>

    <div>
        <div class="card lg:sticky lg:top-space-lg">
            <div class="card-header"><h3 class="card-title">Simpan Perubahan</h3></div>
            <div class="card-body space-y-space-md">
                <p class="text-body-sm text-on-surface-variant">Setiap perubahan pengaturan dicatat pada audit log beserta nilai sebelum dan sesudahnya.</p>
                <button class="btn btn-primary w-full"><span class="material-symbols-outlined !text-[18px]">save</span> Simpan Pengaturan</button>
                @permission('audit.view')
                    <a href="{{ route('admin.audit.index', ['action' => 'settings_updated']) }}" class="btn btn-ghost w-full">Riwayat Perubahan</a>
                @endpermission
            </div>
        </div>
    </div>
</form>
@endsection
