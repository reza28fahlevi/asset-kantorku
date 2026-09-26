@extends('layouts.app')

@section('title', 'Pengajuan Pengadaan Aset')
@section('subtitle', 'Lengkapi kebutuhan, rincian barang, dan dokumen penawaran untuk diajukan ke alur persetujuan.')

@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <a href="{{ route('procurements.index') }}" class="hover:text-on-surface">Procurement</a>
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span class="text-on-surface font-semibold">Pengadaan Baru</span>
@endsection

@php
    $oldItems = old('items', [['asset_category_id' => '', 'item_name' => '', 'specification' => '', 'quantity' => 1, 'estimated_unit_price' => 0]]);
    $oldItems = array_values(array_map(fn ($i) => [
        'asset_category_id' => (string) ($i['asset_category_id'] ?? ''),
        'item_name' => $i['item_name'] ?? '',
        'specification' => $i['specification'] ?? '',
        'quantity' => (int) ($i['quantity'] ?? 1),
        'estimated_unit_price' => (float) ($i['estimated_unit_price'] ?? 0),
    ], $oldItems));
    $manager = $employee?->manager;
@endphp

@section('content')
<form method="POST" action="{{ route('procurements.store') }}" enctype="multipart/form-data"
      x-data="procurementForm(@js($oldItems))">
    @csrf
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter items-start">
        <div class="lg:col-span-2 space-y-space-lg">
            {{-- 1. Profil pemohon --}}
            <x-card title="1. Profil Pemohon" icon="badge">
                @if ($employee)
                    <div class="grid sm:grid-cols-2 gap-space-md">
                        <div class="rounded-lg bg-surface-subtle border border-border-subtle p-space-md">
                            <p class="text-label-sm uppercase text-outline">Nomor Induk Karyawan</p>
                            <p class="font-mono text-body-md mt-1">{{ $employee->employee_no }}</p>
                        </div>
                        <div class="rounded-lg bg-surface-subtle border border-border-subtle p-space-md">
                            <p class="text-label-sm uppercase text-outline">Nama Pemohon</p>
                            <p class="text-body-md font-semibold mt-1">{{ $employee->name }}</p>
                            <p class="text-label-sm font-normal text-outline">{{ $employee->job_title }}</p>
                        </div>
                        <div class="rounded-lg bg-surface-subtle border border-border-subtle p-space-md">
                            <p class="text-label-sm uppercase text-outline">Departemen Asal</p>
                            <p class="text-body-md mt-1">{{ $employee->department?->name ?? '-' }}</p>
                        </div>
                        <div class="rounded-lg bg-amber-50 border border-amber-200 p-space-md">
                            <p class="text-label-sm uppercase text-secondary">Atasan Langsung (Approver)</p>
                            <p class="text-body-md font-semibold mt-1">{{ $manager?->name ?? 'Belum ditentukan' }}</p>
                            <p class="text-label-sm font-normal text-outline">{{ $manager?->job_title }}</p>
                        </div>
                    </div>
                @else
                    <p class="text-body-sm text-status-disposal">Akun Anda belum terhubung ke data karyawan. Hubungi administrator.</p>
                @endif
            </x-card>

            {{-- 2. Kebutuhan --}}
            <x-card title="2. Kebutuhan Pengadaan" icon="assignment">
                <div class="grid sm:grid-cols-2 gap-space-md">
                    <x-field label="Judul Pengadaan" name="title" :required="true" class="sm:col-span-2">
                        <input type="text" id="title" name="title" value="{{ old('title') }}" maxlength="200" class="form-input" placeholder="mis. Pengadaan 3 unit laptop engineer baru" required>
                    </x-field>
                    <x-field label="Departemen (Cost Center)" name="department_id" :required="true">
                        <select id="department_id" name="department_id" class="form-input" required>
                            <option value="">Pilih departemen</option>
                            @foreach ($departments as $dept)
                                <option value="{{ $dept->id }}" @selected((string) old('department_id', $employee?->department_id) === (string) $dept->id)>{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </x-field>
                    <x-field label="Tanggal Dibutuhkan" name="needed_by">
                        <input type="date" id="needed_by" name="needed_by" value="{{ old('needed_by') }}" min="{{ now()->toDateString() }}" class="form-input">
                    </x-field>
                    <x-field label="Justifikasi Bisnis & Tujuan Operasional" name="justification" :required="true" class="sm:col-span-2">
                        <textarea id="justification" name="justification" rows="4" maxlength="5000" class="form-input" placeholder="Jelaskan alasan kebutuhan, dampak bisnis, dan rencana penggunaan..." required>{{ old('justification') }}</textarea>
                    </x-field>
                </div>
            </x-card>

            {{-- 3. Item --}}
            <x-card title="3. Daftar Rincian Item Barang" icon="shopping_cart" :padding="false">
                <x-slot:actions>
                    <button type="button" class="btn btn-secondary btn-sm" @click="add()" :disabled="items.length >= 50">
                        <span class="material-symbols-outlined !text-[18px]">add_circle</span> Tambah Item
                    </button>
                </x-slot:actions>
                @error('items')<p class="form-error px-space-lg pt-space-md">{{ $message }}</p>@enderror
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="w-44">Kategori</th>
                                <th>Nama Item & Spesifikasi</th>
                                <th class="w-24">Qty</th>
                                <th class="w-40">Harga Satuan (Rp)</th>
                                <th class="text-right w-36">Subtotal</th>
                                <th class="w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(item, i) in items" :key="item.key">
                                <tr class="align-top">
                                    <td>
                                        <select class="form-input" :name="`items[${i}][asset_category_id]`" x-model="item.asset_category_id" required>
                                            <option value="">Pilih</option>
                                            @foreach ($categories as $cat)
                                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="space-y-space-xs">
                                        <input type="text" class="form-input" :name="`items[${i}][item_name]`" x-model="item.item_name" maxlength="200" placeholder="Nama item" required>
                                        <textarea class="form-input" rows="2" :name="`items[${i}][specification]`" x-model="item.specification" maxlength="2000" placeholder="Spesifikasi teknis (opsional)"></textarea>
                                    </td>
                                    <td><input type="number" min="1" max="1000" class="form-input" :name="`items[${i}][quantity]`" x-model.number="item.quantity" required></td>
                                    <td><input type="number" min="0" step="1" class="form-input font-mono" :name="`items[${i}][estimated_unit_price]`" x-model.number="item.estimated_unit_price" required></td>
                                    <td class="text-right font-mono whitespace-nowrap pt-3" x-text="rupiah(subtotal(item))"></td>
                                    <td>
                                        <button type="button" class="btn btn-ghost btn-sm" @click="remove(i)" :disabled="items.length === 1" title="Hapus item">
                                            <span class="material-symbols-outlined !text-[18px]">delete</span>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                @foreach ($errors->getMessages() as $key => $messages)
                    @if (str_starts_with($key, 'items.'))
                        <p class="form-error px-space-lg">Item #{{ ((int) explode('.', $key)[1]) + 1 }}: {{ $messages[0] }}</p>
                    @endif
                @endforeach
                <div class="m-space-lg mt-space-md flex flex-wrap justify-between items-center gap-space-sm bg-surface-subtle rounded-lg px-space-md py-space-sm">
                    <span class="text-body-sm text-on-surface-variant flex items-center gap-1">
                        <span class="material-symbols-outlined !text-[16px]">info</span> Total qty: <span class="font-mono" x-text="totalQty() + ' unit'"></span>
                    </span>
                    <span class="text-body-sm"><span class="text-label-sm uppercase text-outline mr-space-sm">Subtotal Keseluruhan</span><span class="font-mono font-semibold" x-text="rupiah(total())"></span></span>
                </div>
            </x-card>

            {{-- 4. Lampiran --}}
            <x-card title="4. Quotation & Bukti Penawaran Vendor" icon="upload_file">
                <label class="block border-2 border-dashed border-border-subtle rounded-lg p-space-xl text-center cursor-pointer hover:bg-surface-subtle">
                    <span class="material-symbols-outlined !text-[36px] text-secondary">cloud_upload</span>
                    <p class="text-title-md mt-space-sm">Pilih berkas penawaran</p>
                    <p class="text-body-sm text-on-surface-variant">PDF, JPG, PNG, WEBP, DOC(X), XLS(X) · maksimal 5 berkas</p>
                    <input type="file" name="attachments[]" multiple class="sr-only" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
                           @change="files = Array.from($event.target.files).map(f => ({ name: f.name, size: f.size }))">
                </label>
                <ul class="mt-space-md space-y-space-xs" x-show="files.length">
                    <template x-for="f in files" :key="f.name">
                        <li class="flex items-center gap-space-sm bg-surface-subtle rounded px-space-md py-space-sm text-body-sm">
                            <span class="material-symbols-outlined !text-[18px] text-outline">description</span>
                            <span class="flex-1 truncate" x-text="f.name"></span>
                            <span class="text-label-sm font-normal text-outline" x-text="(f.size / 1024).toFixed(0) + ' KB'"></span>
                        </li>
                    </template>
                </ul>
                @error('attachments')<p class="form-error">{{ $message }}</p>@enderror
                @foreach ($errors->get('attachments.*') as $messages)
                    <p class="form-error">{{ $messages[0] }}</p>
                @endforeach
            </x-card>
        </div>

        {{-- Ringkasan sticky --}}
        <div class="space-y-space-lg lg:sticky lg:top-20">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><span class="material-symbols-outlined">receipt_long</span> Ringkasan Pengadaan</h3>
                    <x-badge color="neutral">Draft</x-badge>
                </div>
                <div class="card-body space-y-space-md">
                    <div class="rounded-lg bg-surface-subtle p-space-md">
                        <p class="text-label-sm uppercase text-outline">Total Estimasi Biaya</p>
                        <p class="text-display-md font-mono mt-1" x-text="rupiah(total())"></p>
                        <p class="text-label-sm font-normal text-outline mt-1"><span x-text="items.length"></span> jenis item · <span x-text="totalQty()"></span> unit</p>
                    </div>
                    <div>
                        <p class="text-label-sm uppercase text-outline mb-space-sm">Alur Persetujuan</p>
                        <div class="flex items-start gap-space-sm">
                            <span class="w-6 h-6 rounded-full bg-status-pending text-white text-label-sm flex items-center justify-center shrink-0">1</span>
                            <div>
                                <p class="text-body-sm font-semibold">{{ $manager?->name ?? 'Atasan langsung' }}</p>
                                <p class="text-label-sm font-normal text-outline">Atasan langsung pemohon; tahap berikutnya ditentukan oleh kebijakan nilai.</p>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-lg border border-blue-200 bg-blue-50 p-space-md text-body-sm text-on-surface-variant flex gap-space-sm">
                        <span class="material-symbols-outlined !text-[18px] text-status-assigned">shield</span>
                        <span>Setelah diajukan, permintaan terkunci dan tidak dapat diubah. Simpan sebagai draft bila masih perlu dilengkapi.</span>
                    </div>
                    <button type="submit" name="action" value="submit" class="btn btn-primary w-full justify-center">
                        <span class="material-symbols-outlined !text-[18px]">send</span> Kirim untuk Approval
                    </button>
                    <div class="grid grid-cols-2 gap-space-sm">
                        <button type="submit" name="action" value="draft" class="btn btn-secondary justify-center" formnovalidate>
                            <span class="material-symbols-outlined !text-[18px]">save</span> Simpan Draft
                        </button>
                        <a href="{{ route('procurements.index') }}" class="btn btn-ghost justify-center">Batal</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    function procurementForm(initial) {
        let seq = 0;
        const blank = () => ({ key: ++seq, asset_category_id: '', item_name: '', specification: '', quantity: 1, estimated_unit_price: 0 });
        return {
            items: initial.map(i => ({ ...blank(), ...i, key: ++seq })),
            files: [],
            add() { if (this.items.length < 50) this.items.push(blank()); },
            remove(i) { if (this.items.length > 1) this.items.splice(i, 1); },
            subtotal(item) { return (Number(item.quantity) || 0) * (Number(item.estimated_unit_price) || 0); },
            total() { return this.items.reduce((s, i) => s + this.subtotal(i), 0); },
            totalQty() { return this.items.reduce((s, i) => s + (Number(i.quantity) || 0), 0); },
            rupiah(v) { return 'Rp ' + Math.round(v).toLocaleString('id-ID'); },
        };
    }
</script>
@endpush
