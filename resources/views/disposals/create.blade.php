@extends('layouts.app')

@section('title', 'Ajukan Disposal Aset')
@section('subtitle', 'Aset akan dikunci sebagai Menunggu Disposal setelah diajukan')
@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <a href="{{ route('disposals.index') }}" class="hover:text-on-surface">Disposal</a>
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span class="text-on-surface font-semibold">Pengajuan Baru</span>
@endsection

@php
    $initial = $asset ? [
        'id' => $asset->id,
        'asset_tag' => $asset->asset_tag,
        'name' => $asset->name,
        'serial_number' => $asset->serial_number,
        'category' => $asset->category?->name,
        'location' => null,
        'status' => $asset->status?->label(),
    ] : null;
@endphp

@section('content')
<form method="POST" action="{{ route('disposals.store') }}" enctype="multipart/form-data"
      x-data="disposalForm(@js($initial), @js((int) old('asset_id', 0)))"
      class="grid grid-cols-1 lg:grid-cols-3 gap-gutter">
    @csrf
    <input type="hidden" name="asset_id" :value="selected ? selected.id : ''">

    <div class="lg:col-span-2 space-y-gutter">
        <x-card title="Pilih Aset" icon="inventory_2">
            <template x-if="selected">
                <div class="flex items-start justify-between gap-space-md p-space-md rounded border border-border-subtle bg-surface">
                    <div>
                        <span class="tag" x-text="selected.asset_tag"></span>
                        <div class="font-semibold text-on-surface mt-1" x-text="selected.name"></div>
                        <div class="text-body-sm text-on-surface-variant">
                            <span x-text="selected.category"></span>
                            <span x-show="selected.serial_number"> · SN <span x-text="selected.serial_number"></span></span>
                            <span x-show="selected.location"> · <span x-text="selected.location"></span></span>
                        </div>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm" @click="selected = null">Ganti</button>
                </div>
            </template>
            <div x-show="!selected">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 !text-[18px] text-outline">search</span>
                    <input type="text" class="form-input pl-9" placeholder="Ketik tag, nama, atau serial number aset (min. 2 karakter)..."
                           x-model="q" @input.debounce.300ms="search()">
                </div>
                <p class="form-hint">Hanya menampilkan aset yang dapat diajukan disposal.</p>
                <div class="mt-space-sm border border-border-subtle rounded divide-y divide-border-subtle max-h-72 overflow-y-auto" x-show="results.length">
                    <template x-for="a in results" :key="a.id">
                        <button type="button" class="w-full text-left px-space-md py-space-sm hover:bg-surface flex justify-between gap-space-md" @click="pick(a)">
                            <div>
                                <span class="tag" x-text="a.asset_tag"></span>
                                <span class="ml-2 text-body-md" x-text="a.name"></span>
                                <div class="text-label-sm text-on-surface-variant" x-text="a.category + ' · ' + a.location"></div>
                            </div>
                            <span class="text-label-sm text-on-surface-variant whitespace-nowrap" x-text="a.status"></span>
                        </button>
                    </template>
                </div>
                <p class="text-body-sm text-on-surface-variant mt-space-sm" x-show="loading">Mencari...</p>
                <p class="text-body-sm text-on-surface-variant mt-space-sm" x-show="!loading && searched && !results.length">Aset tidak ditemukan.</p>
            </div>
            @error('asset_id')<p class="form-error">{{ $message }}</p>@enderror
        </x-card>

        <x-card title="Alasan & Kondisi" icon="report">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-space-md">
                <x-field label="Jenis Alasan" name="reason_type" :required="true">
                    <select name="reason_type" id="reason_type" class="form-input" required>
                        <option value="">Pilih alasan</option>
                        @foreach (\App\Enums\DisposalReason::cases() as $c)
                            <option value="{{ $c->value }}" @selected(old('reason_type') === $c->value)>{{ $c->label() }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Metode Rencana" name="planned_method" :required="true">
                    <select name="planned_method" id="planned_method" class="form-input" required>
                        <option value="">Pilih metode</option>
                        @foreach (\App\Enums\DisposalMethod::cases() as $c)
                            <option value="{{ $c->value }}" @selected(old('planned_method') === $c->value)>{{ $c->label() }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Penjelasan Alasan" name="reason" :required="true" class="md:col-span-2">
                    <textarea name="reason" id="reason" rows="3" class="form-input" required maxlength="2000">{{ old('reason') }}</textarea>
                </x-field>
                <x-field label="Deskripsi Kondisi Aset" name="condition_description" class="md:col-span-2">
                    <textarea name="condition_description" id="condition_description" rows="3" class="form-input" maxlength="2000">{{ old('condition_description') }}</textarea>
                </x-field>
            </div>
        </x-card>

        <x-card title="Lampiran Pendukung" icon="attach_file">
            <x-field label="File (maks. 5)" name="attachments" hint="Foto kondisi, berita acara kerusakan, atau dokumen pendukung lainnya.">
                <input type="file" name="attachments[]" multiple class="form-input">
            </x-field>
            @error('attachments.*')<p class="form-error">{{ $message }}</p>@enderror
        </x-card>
    </div>

    <div>
        <div class="card lg:sticky lg:top-space-lg">
            <div class="card-header"><h3 class="card-title">Ringkasan</h3></div>
            <div class="card-body space-y-space-md">
                <dl class="dl-grid">
                    <dt>Aset</dt>
                    <dd x-text="selected ? selected.asset_tag + ' — ' + selected.name : '-'"></dd>
                    <dt>Status Aset</dt>
                    <dd x-text="selected ? selected.status : '-'"></dd>
                </dl>
                <div class="text-body-sm text-on-surface-variant bg-surface rounded p-space-sm border border-border-subtle">
                    Pengajuan akan melalui approval berjenjang. Aset dikunci selama proses berlangsung dan tidak dapat dipakai untuk transaksi lain.
                </div>
                <div class="flex flex-col gap-space-sm">
                    <button type="submit" name="action" value="submit" class="btn btn-primary w-full" :disabled="!selected">
                        <span class="material-symbols-outlined !text-[18px]">send</span> Ajukan
                    </button>
                    <button type="submit" name="action" value="draft" class="btn btn-secondary w-full" :disabled="!selected">Simpan Draft</button>
                    <a href="{{ route('disposals.index') }}" class="btn btn-ghost w-full">Batal</a>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    function disposalForm(initial, oldId) {
        return {
            selected: initial, q: '', results: [], loading: false, searched: false,
            init() {
                if (!this.selected && oldId) {
                    this.fetchAssets('').then(list => { this.selected = list.find(a => a.id === oldId) || null; });
                }
            },
            async fetchAssets(term) {
                const url = new URL(@js(route('assets.search')), window.location.origin);
                url.searchParams.set('scope', 'disposable');
                if (term) url.searchParams.set('q', term);
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return [];
                return (await res.json()).data || [];
            },
            async search() {
                if (this.q.trim().length < 2) { this.results = []; this.searched = false; return; }
                this.loading = true;
                this.results = await this.fetchAssets(this.q.trim());
                this.loading = false; this.searched = true;
            },
            pick(a) { this.selected = a; this.results = []; this.q = ''; },
        };
    }
</script>
@endpush
