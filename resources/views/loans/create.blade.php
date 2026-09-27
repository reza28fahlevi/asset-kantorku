@extends('layouts.app')
@section('title', 'Ajukan Peminjaman Aset')
@section('subtitle', 'Peminjaman aset sementara dengan tanggal jatuh tempo. Approval oleh manager peminjam.')
@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span><a href="{{ route('loans.index') }}" class="hover:text-on-surface">Peminjaman</a>
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span><span class="text-on-surface font-semibold">Ajukan Peminjaman</span>
@endsection
@section('content')
<form method="POST" data-ajax-form action="{{ route('loans.store') }}" class="grid grid-cols-1 lg:grid-cols-3 gap-gutter"
      x-data="{ start: @js(old('start_date', now()->toDateString())), due: @js(old('due_date', now()->addDays(7)->toDateString())), maxDays: {{ $maxDays }},
                get days() { const s = new Date(this.start), d = new Date(this.due); return (isNaN(s) || isNaN(d)) ? 0 : Math.round((d - s) / 86400000) + 1; } }">
    @csrf
    <div class="lg:col-span-2 space-y-gutter">
        <x-card title="Informasi Peminjaman" icon="schedule">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-space-md">
                @if ($employees->isNotEmpty())
                    <x-field label="Karyawan Peminjam" name="borrower_employee_id" hint="Kosongkan untuk diri sendiri." class="md:col-span-2">
                        <select name="borrower_employee_id" id="borrower_employee_id" class="form-input">
                            <option value="">- Diri sendiri -</option>
                            @foreach ($employees as $e)
                                <option value="{{ $e->id }}" @selected(old('borrower_employee_id') == $e->id)>{{ $e->optionLabel() }}</option>
                            @endforeach
                        </select>
                    </x-field>
                @endif
                <x-field label="Tanggal Mulai" name="start_date" :required="true">
                    <input type="date" name="start_date" id="start_date" x-model="start" min="{{ now()->toDateString() }}" class="form-input" required>
                </x-field>
                <x-field label="Jatuh Tempo" name="due_date" :required="true" hint="Maksimal {{ $maxDays }} hari.">
                    <input type="date" name="due_date" id="due_date" x-model="due" :min="start" class="form-input" required>
                </x-field>
                <x-field label="Lokasi Penggunaan" name="usage_location_id" class="md:col-span-2">
                    <select name="usage_location_id" id="usage_location_id" class="form-input">
                        <option value="">- Tidak ditentukan -</option>
                        @foreach ($locations as $l)
                            <option value="{{ $l->id }}" @selected(old('usage_location_id') == $l->id)>{{ $l->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Tujuan / Keperluan" name="purpose" :required="true" class="md:col-span-2">
                    <textarea name="purpose" id="purpose" rows="3" maxlength="2000" class="form-input" required placeholder="Jelaskan keperluan peminjaman...">{{ old('purpose') }}</textarea>
                </x-field>
            </div>
        </x-card>
        <x-card title="Pilih Aset" icon="inventory_2">
            @include('assignments._asset-picker', ['max' => 20])
        </x-card>
    </div>
    <div>
        <div class="lg:sticky lg:top-24 space-y-gutter">
            <x-card title="Ringkasan" icon="summarize">
                <dl class="dl-grid">
                    <dt>Durasi</dt><dd><span class="font-semibold" :class="days > maxDays ? 'text-error' : ''" x-text="days + ' hari'"></span></dd>
                    <dt>Batas</dt><dd>{{ $maxDays }} hari</dd>
                </dl>
                <p x-show="days > maxDays" x-cloak class="form-error mt-space-sm">Durasi melebihi batas maksimal peminjaman.</p>
                <div class="mt-space-md text-body-sm text-on-surface-variant space-y-space-sm">
                    <p>Permintaan akan disetujui oleh <strong class="text-on-surface">manager peminjam</strong>, lalu admin aset melakukan serah-terima.</p>
                    <p>Hanya aset berstatus Tersedia yang dapat dipilih (maks. 20).</p>
                </div>
                <div class="mt-space-lg flex flex-col gap-space-sm">
                    <button name="action" value="submit" class="btn btn-primary w-full justify-center"><span class="material-symbols-outlined !text-[18px]">send</span> Ajukan</button>
                    <button name="action" value="draft" class="btn btn-secondary w-full justify-center"><span class="material-symbols-outlined !text-[18px]">save</span> Simpan Draft</button>
                    <a href="{{ route('loans.index') }}" class="btn btn-ghost w-full justify-center">Batal</a>
                </div>
            </x-card>
        </div>
    </div>
</form>
@endsection
