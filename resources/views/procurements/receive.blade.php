@extends('layouts.app')

@section('title', 'Penerimaan Barang')
@section('subtitle', $procurement->request_no.' · '.$procurement->title)

@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <a href="{{ route('procurements.index') }}" class="hover:text-on-surface">Procurement</a>
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <a href="{{ route('procurements.show', $procurement) }}" class="hover:text-on-surface">{{ $procurement->request_no }}</a>
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span class="text-on-surface font-semibold">Penerimaan</span>
@endsection

@php
    $conditions = \App\Enums\AssetCondition::cases();
    $openItems = $procurement->items->filter(fn ($i) => $i->remainingQuantity() > 0);
@endphp

@section('content')
<form method="POST" action="{{ route('procurements.receive', $procurement) }}" enctype="multipart/form-data">
    @csrf
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter items-start">
        <div class="lg:col-span-2 space-y-space-lg">
            <x-card title="Data Penerimaan" icon="local_shipping">
                <div class="grid sm:grid-cols-2 gap-space-md">
                    <x-field label="Tanggal Diterima" name="received_at" :required="true">
                        <input type="datetime-local" id="received_at" name="received_at" value="{{ old('received_at', now()->format('Y-m-d\TH:i')) }}" max="{{ now()->format('Y-m-d\TH:i') }}" class="form-input" required>
                    </x-field>
                    <x-field label="No. Surat Jalan" name="delivery_note_no">
                        <input type="text" id="delivery_note_no" name="delivery_note_no" value="{{ old('delivery_note_no') }}" maxlength="50" class="form-input font-mono">
                    </x-field>
                    <x-field label="Catatan" name="notes" class="sm:col-span-2">
                        <textarea id="notes" name="notes" rows="2" maxlength="2000" class="form-input">{{ old('notes') }}</textarea>
                    </x-field>
                </div>
            </x-card>

            @forelse ($openItems as $item)
                @php $p = "items.{$item->id}"; $n = "items[{$item->id}]"; @endphp
                <x-card :title="$item->item_name" icon="inventory_2">
                    <x-slot:actions>
                        <span class="text-label-sm font-normal text-outline">{{ $item->category?->name }} · sisa <span class="font-semibold text-on-surface">{{ $item->remainingQuantity() }}</span> dari {{ $item->quantity }} unit</span>
                    </x-slot:actions>
                    @if ($item->specification)<p class="text-body-sm text-on-surface-variant mb-space-md">{{ $item->specification }}</p>@endif
                    <div class="grid sm:grid-cols-4 gap-space-md">
                        <x-field label="Diterima" :name="$p.'.accepted'">
                            <input type="number" name="{{ $n }}[accepted]" value="{{ old($p.'.accepted') }}" min="0" max="{{ $item->remainingQuantity() }}" class="form-input">
                        </x-field>
                        <x-field label="Ditolak" :name="$p.'.rejected'">
                            <input type="number" name="{{ $n }}[rejected]" value="{{ old($p.'.rejected') }}" min="0" class="form-input">
                        </x-field>
                        <x-field label="Lokasi Penempatan" :name="$p.'.location_id'" class="sm:col-span-2">
                            <select name="{{ $n }}[location_id]" class="form-input">
                                <option value="">Pilih lokasi</option>
                                @foreach ($locations as $loc)
                                    <option value="{{ $loc->id }}" @selected((string) old($p.'.location_id') === (string) $loc->id)>{{ $loc->name }}</option>
                                @endforeach
                            </select>
                        </x-field>
                        <x-field label="Harga Perolehan / Unit (Rp)" :name="$p.'.unit_cost'">
                            <input type="number" name="{{ $n }}[unit_cost]" value="{{ old($p.'.unit_cost', (float) $item->estimated_unit_price) }}" min="0" step="1" class="form-input font-mono">
                        </x-field>
                        <x-field label="Kondisi" :name="$p.'.condition'">
                            <select name="{{ $n }}[condition]" class="form-input">
                                @foreach ($conditions as $c)
                                    <option value="{{ $c->value }}" @selected(old($p.'.condition', 'GOOD') === $c->value)>{{ $c->label() }}</option>
                                @endforeach
                            </select>
                        </x-field>
                        <x-field label="Merek" :name="$p.'.brand'">
                            <input type="text" name="{{ $n }}[brand]" value="{{ old($p.'.brand') }}" maxlength="100" class="form-input">
                        </x-field>
                        <x-field label="Model" :name="$p.'.model'">
                            <input type="text" name="{{ $n }}[model]" value="{{ old($p.'.model') }}" maxlength="100" class="form-input">
                        </x-field>
                        <x-field label="Garansi Berakhir" :name="$p.'.warranty_end_date'">
                            <input type="date" name="{{ $n }}[warranty_end_date]" value="{{ old($p.'.warranty_end_date') }}" class="form-input">
                        </x-field>
                        <x-field label="Nomor Seri" :name="$p.'.serial_numbers'" :required="(bool) $item->category?->requires_serial" class="sm:col-span-3"
                                 hint="Satu per baris atau pisahkan dengan koma; jumlah harus sama dengan unit diterima.">
                            <textarea name="{{ $n }}[serial_numbers]" rows="2" class="form-input font-mono">{{ old($p.'.serial_numbers') }}</textarea>
                        </x-field>
                        <x-field label="Catatan Pengecualian" :name="$p.'.exception_notes'" class="sm:col-span-4" hint="Isi bila ada barang ditolak / tidak sesuai.">
                            <input type="text" name="{{ $n }}[exception_notes]" value="{{ old($p.'.exception_notes') }}" maxlength="1000" class="form-input">
                        </x-field>
                    </div>
                </x-card>
            @empty
                <div class="card"><x-empty icon="task_alt" message="Semua item sudah diterima penuh."/></div>
            @endforelse
            @error('items')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div class="space-y-space-lg lg:sticky lg:top-20">
            <x-card title="Ringkasan Pesanan" icon="receipt_long">
                <dl class="dl-grid">
                    <dt>No. PO</dt><dd class="font-mono">{{ $procurement->po_number ?? '-' }}</dd>
                    <dt>Vendor</dt><dd>{{ $procurement->vendor?->name ?? '-' }}</dd>
                    <dt>Status</dt><dd><x-badge :enum="$procurement->status"/></dd>
                </dl>
                <table class="table mt-space-md">
                    <thead><tr><th>Item</th><th class="text-right">Diterima</th><th class="text-right">Sisa</th></tr></thead>
                    <tbody>
                        @foreach ($procurement->items as $item)
                            <tr>
                                <td class="text-body-sm">{{ $item->item_name }}</td>
                                <td class="text-right">{{ $item->quantity_received }}/{{ $item->quantity }}</td>
                                <td class="text-right font-semibold">{{ $item->remainingQuantity() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>

            <x-card title="Dokumen Penerimaan" icon="upload_file">
                <input type="file" name="attachments[]" multiple class="form-input" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx">
                <p class="form-hint">Surat jalan, BAST, foto barang (maks. 5 berkas).</p>
                @error('attachments')<p class="form-error">{{ $message }}</p>@enderror
                @foreach ($errors->get('attachments.*') as $messages)
                    <p class="form-error">{{ $messages[0] }}</p>
                @endforeach
            </x-card>

            <div class="card card-body space-y-space-sm">
                <p class="text-body-sm text-on-surface-variant flex gap-space-xs">
                    <span class="material-symbols-outlined !text-[18px] text-status-assigned">info</span>
                    Setiap unit yang diterima akan otomatis diregistrasi sebagai aset baru.
                </p>
                <button type="submit" class="btn btn-success w-full justify-center" @disabled($openItems->isEmpty())
                        onclick="return confirm('Simpan penerimaan dan registrasi aset?')">
                    <span class="material-symbols-outlined !text-[18px]">inventory</span> Simpan Penerimaan
                </button>
                <a href="{{ route('procurements.show', $procurement) }}" class="btn btn-ghost w-full justify-center">Batal</a>
            </div>
        </div>
    </div>
</form>
@endsection
