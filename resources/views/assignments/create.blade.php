@extends('layouts.app')
@section('title', 'Buat Permintaan Penugasan')
@section('subtitle', 'Ajukan penugasan aset tersedia kepada karyawan. Approval oleh manager penerima.')
@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span><a href="{{ route('assignments.index') }}" class="hover:text-on-surface">Penugasan</a>
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span><span class="text-on-surface font-semibold">Buat Permintaan</span>
@endsection
@section('content')
<form method="POST" data-ajax-form action="{{ route('assignments.store') }}" class="grid grid-cols-1 lg:grid-cols-3 gap-gutter">
    @csrf
    <div class="lg:col-span-2 space-y-gutter">
        <x-card title="Informasi Penugasan" icon="assignment_ind">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-space-md">
                @if ($employees->isNotEmpty())
                    <x-field label="Karyawan Penerima" name="recipient_employee_id" hint="Kosongkan untuk diri sendiri." class="md:col-span-2">
                        <select name="recipient_employee_id" id="recipient_employee_id" class="form-input">
                            <option value="">- Diri sendiri -</option>
                            @foreach ($employees as $e)
                                <option value="{{ $e->id }}" @selected(old('recipient_employee_id') == $e->id)>{{ $e->optionLabel() }}</option>
                            @endforeach
                        </select>
                    </x-field>
                @endif
                <x-field label="Lokasi Penempatan" name="location_id" :required="true">
                    <select name="location_id" id="location_id" class="form-input" required>
                        <option value="">- Pilih lokasi -</option>
                        @foreach ($locations as $l)
                            <option value="{{ $l->id }}" @selected(old('location_id') == $l->id)>{{ $l->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Tanggal Mulai" name="start_date" :required="true">
                    <input type="date" name="start_date" id="start_date" value="{{ old('start_date', now()->toDateString()) }}" min="{{ now()->toDateString() }}" class="form-input" required>
                </x-field>
                <x-field label="Tujuan / Keperluan" name="purpose" :required="true" class="md:col-span-2">
                    <textarea name="purpose" id="purpose" rows="3" maxlength="2000" class="form-input" required placeholder="Jelaskan keperluan penggunaan aset...">{{ old('purpose') }}</textarea>
                </x-field>
            </div>
        </x-card>
        <x-card title="Pilih Aset" icon="inventory_2">
            @include('assignments._asset-picker', ['max' => 50])
        </x-card>
    </div>
    <div>
        <div class="lg:sticky lg:top-24 space-y-gutter">
            <x-card title="Ringkasan" icon="summarize">
                <div class="space-y-space-md text-body-sm text-on-surface-variant">
                    <p>Permintaan akan dikirim ke <strong class="text-on-surface">manager karyawan penerima</strong> untuk approval.</p>
                    <p>Setelah disetujui, admin aset melakukan serah-terima dan status aset berubah menjadi <x-badge color="assigned">Ditugaskan</x-badge>.</p>
                    <p>Hanya aset berstatus Tersedia yang dapat dipilih (maks. 50).</p>
                </div>
                <div class="mt-space-lg flex flex-col gap-space-sm">
                    <button name="action" value="submit" class="btn btn-primary w-full justify-center"><span class="material-symbols-outlined !text-[18px]">send</span> Ajukan</button>
                    <button name="action" value="draft" class="btn btn-secondary w-full justify-center"><span class="material-symbols-outlined !text-[18px]">save</span> Simpan Draft</button>
                    <a href="{{ route('assignments.index') }}" class="btn btn-ghost w-full justify-center">Batal</a>
                </div>
            </x-card>
        </div>
    </div>
</form>
@endsection
