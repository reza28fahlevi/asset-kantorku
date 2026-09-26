@extends('layouts.app')

@section('title', $disposal->request_no)
@section('subtitle', 'Pengajuan disposal aset')
@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <a href="{{ route('disposals.index') }}" class="hover:text-on-surface">Disposal</a>
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span class="text-on-surface font-semibold">{{ $disposal->request_no }}</span>
@endsection

@section('actions')
    @can('submit', $disposal)
        <x-confirm-form :action="route('disposals.submit', $disposal)" method="POST" confirm="Ajukan permintaan disposal ini untuk approval?" button="btn btn-primary">
            <span class="material-symbols-outlined !text-[18px]">send</span> Ajukan
        </x-confirm-form>
    @endcan
    @can('cancel', $disposal)
        <x-confirm-form :action="route('disposals.cancel', $disposal)" method="POST" confirm="Batalkan permintaan disposal ini?" button="btn btn-danger">
            Batalkan
        </x-confirm-form>
    @endcan
@endsection

@section('content')
@php $asset = $disposal->asset; @endphp
<div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter">
    <div class="lg:col-span-2 space-y-gutter">
        <x-card title="Detail Pengajuan" icon="description">
            <x-slot:actions><x-badge :enum="$disposal->status" /></x-slot:actions>
            <dl class="dl-grid">
                <dt>No. Permintaan</dt><dd class="font-semibold">{{ $disposal->request_no }}</dd>
                <dt>Pemohon</dt><dd>{{ $disposal->requester?->name ?? '-' }}</dd>
                <dt>Jenis Alasan</dt><dd><x-badge :enum="$disposal->reason_type" /></dd>
                <dt>Alasan</dt><dd class="whitespace-pre-line">{{ $disposal->reason }}</dd>
                <dt>Kondisi Aset</dt><dd class="whitespace-pre-line">{{ $disposal->condition_description ?: '-' }}</dd>
                <dt>Metode Rencana</dt><dd>{{ $disposal->planned_method?->label() }}</dd>
                <dt>Dibuat</dt><dd>{{ $disposal->created_at?->format('d M Y H:i') }}</dd>
                <dt>Diajukan</dt><dd>{{ $disposal->submitted_at?->format('d M Y H:i') ?? '-' }}</dd>
                @if ($disposal->cancelled_at)
                    <dt>Dibatalkan</dt><dd>{{ $disposal->cancelled_at->format('d M Y H:i') }}</dd>
                @endif
            </dl>
        </x-card>

        @if ($disposal->status === \App\Enums\DisposalStatus::Completed)
            <x-card title="Hasil Eksekusi Disposal" icon="task_alt">
                <dl class="dl-grid">
                    <dt>Tanggal Eksekusi</dt><dd>{{ $disposal->executed_at?->format('d M Y') }}</dd>
                    <dt>Metode Aktual</dt><dd>{{ $disposal->actual_method?->label() ?? '-' }}</dd>
                    <dt>Penerima</dt><dd>{{ $disposal->disposal_recipient ?: '-' }}</dd>
                    <dt>Hasil Penjualan</dt><dd>{{ $disposal->proceeds_amount !== null ? 'Rp '.number_format((float) $disposal->proceeds_amount, 0, ',', '.') : '-' }}</dd>
                    <dt>Dieksekusi Oleh</dt><dd>{{ $disposal->executedBy?->name ?? '-' }}</dd>
                    <dt>Catatan</dt><dd class="whitespace-pre-line">{{ $disposal->execution_notes ?: '-' }}</dd>
                    <dt>Selesai</dt><dd>{{ $disposal->completed_at?->format('d M Y H:i') ?? '-' }}</dd>
                </dl>
            </x-card>
        @endif

        @can('execute', $disposal)
            <x-card title="Eksekusi Disposal" icon="gavel">
                <form method="POST" action="{{ route('disposals.execute', $disposal) }}" enctype="multipart/form-data" class="space-y-space-md"
                      onsubmit="return confirm('Eksekusi disposal? Aset akan berstatus Dihapus dan tidak dapat dikembalikan.')">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-space-md">
                        <x-field label="Tanggal Eksekusi" name="executed_at" :required="true">
                            <input type="date" name="executed_at" id="executed_at" class="form-input" max="{{ now()->toDateString() }}"
                                   value="{{ old('executed_at', now()->toDateString()) }}" required>
                        </x-field>
                        <x-field label="Metode Aktual" name="actual_method" :required="true">
                            <select name="actual_method" id="actual_method" class="form-input" required>
                                @foreach (\App\Enums\DisposalMethod::cases() as $c)
                                    <option value="{{ $c->value }}" @selected(old('actual_method', $disposal->planned_method?->value) === $c->value)>{{ $c->label() }}</option>
                                @endforeach
                            </select>
                        </x-field>
                        <x-field label="Penerima / Pembeli" name="disposal_recipient">
                            <input type="text" name="disposal_recipient" id="disposal_recipient" class="form-input" maxlength="200" value="{{ old('disposal_recipient') }}">
                        </x-field>
                        <x-field label="Hasil Penjualan (Rp)" name="proceeds_amount">
                            <input type="number" name="proceeds_amount" id="proceeds_amount" class="form-input" min="0" step="1" value="{{ old('proceeds_amount') }}">
                        </x-field>
                        <x-field label="Catatan Eksekusi" name="execution_notes" class="md:col-span-2">
                            <textarea name="execution_notes" id="execution_notes" rows="3" class="form-input" maxlength="2000">{{ old('execution_notes') }}</textarea>
                        </x-field>
                        <x-field label="Bukti Disposal" name="attachments" :required="true" hint="Wajib minimal 1 file (berita acara, foto, kuitansi). Maks. 5 file." class="md:col-span-2">
                            <input type="file" name="attachments[]" multiple required class="form-input">
                        </x-field>
                    </div>
                    @error('attachments.*')<p class="form-error">{{ $message }}</p>@enderror
                    <div class="flex justify-end">
                        <button class="btn btn-danger"><span class="material-symbols-outlined !text-[18px]">gavel</span> Eksekusi Disposal</button>
                    </div>
                </form>
            </x-card>
        @endcan

        <x-card title="Lampiran" icon="attach_file" :padding="false">
            @if ($disposal->attachments->isEmpty())
                <x-empty icon="folder_off" message="Tidak ada lampiran." />
            @else
                <table class="table">
                    <thead><tr><th>Nama File</th><th>Kategori</th><th>Ukuran</th><th>Diunggah</th></tr></thead>
                    <tbody>
                        @foreach ($disposal->attachments as $file)
                            <tr>
                                <td><a href="{{ route('attachments.download', $file) }}" class="text-secondary hover:underline inline-flex items-center gap-1">
                                    <span class="material-symbols-outlined !text-[16px]">description</span>{{ $file->original_name }}</a></td>
                                <td>{{ \App\Models\Attachment::CATEGORIES[$file->category] ?? $file->category }}</td>
                                <td>{{ $file->humanSize() }}</td>
                                <td class="text-body-sm">{{ $file->uploadedBy?->name ?? '-' }}<div class="text-label-sm text-on-surface-variant">{{ $file->created_at?->format('d M Y H:i') }}</div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>
    </div>

    <div class="space-y-gutter">
        <x-card title="Aset" icon="inventory_2">
            @if ($asset)
                <span class="tag">{{ $asset->asset_tag }}</span>
                <div class="font-semibold text-on-surface mt-1">{{ $asset->name }}</div>
                <dl class="dl-grid mt-space-md">
                    <dt>Kategori</dt><dd>{{ $asset->category?->name ?? '-' }}</dd>
                    <dt>Lokasi</dt><dd>{{ $asset->location?->name ?? '-' }}</dd>
                    <dt>Serial</dt><dd>{{ $asset->serial_number ?: '-' }}</dd>
                    <dt>Status</dt><dd><x-badge :enum="$asset->status" /></dd>
                    @if ($disposal->asset_status_before)
                        <dt>Status Sebelum</dt><dd><x-badge :enum="$disposal->asset_status_before" /></dd>
                    @endif
                </dl>
                @if (Route::has('assets.show'))
                    <a href="{{ route('assets.show', $asset) }}" class="btn btn-secondary btn-sm w-full mt-space-md">Lihat Detail Aset</a>
                @endif
            @else
                <p class="text-body-sm text-on-surface-variant">Aset tidak ditemukan.</p>
            @endif
        </x-card>

        <x-approval-timeline :approval="$disposal->approvalRequest" />
    </div>
</div>
@endsection
