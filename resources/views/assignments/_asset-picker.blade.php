{{-- Pemilih aset berbasis Alpine: cari via assets.search, simpan sebagai asset_ids[]. Param: $max --}}
<div x-data="assetPicker(@js(array_values(array_map('intval', (array) old('asset_ids', [])))), {{ $max ?? 50 }})" class="space-y-space-md">
    <div class="flex gap-space-sm">
        <div class="relative flex-1">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline !text-[18px]">search</span>
            <input type="search" x-model="q" @input.debounce.350ms="search()" @keydown.enter.prevent="search()" class="form-input pl-9" placeholder="Cari asset tag, nama, atau serial number aset tersedia...">
        </div>
        <button type="button" class="btn btn-secondary" @click="search()"><span class="material-symbols-outlined !text-[18px]">search</span> Cari</button>
    </div>

    <div x-show="loading" class="text-body-sm text-on-surface-variant">Mencari aset...</div>
    <div x-show="results.length" x-cloak class="border border-border-subtle rounded max-h-64 overflow-y-auto divide-y divide-border-subtle">
        <template x-for="a in results" :key="a.id">
            <div class="flex items-center justify-between px-space-md py-space-sm hover:bg-surface-subtle">
                <div class="min-w-0">
                    <div class="flex items-center gap-space-sm"><span class="tag" x-text="a.asset_tag"></span><span class="text-body-sm font-semibold truncate" x-text="a.name"></span></div>
                    <div class="text-label-sm text-on-surface-variant" x-text="[a.category, a.location, a.serial_number ? 'SN ' + a.serial_number : null].filter(Boolean).join(' · ')"></div>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" :disabled="isSelected(a.id) || selected.length >= max" @click="add(a)">
                    <span class="material-symbols-outlined !text-[18px]" x-text="isSelected(a.id) ? 'check' : 'add'"></span>
                    <span x-text="isSelected(a.id) ? 'Dipilih' : 'Tambah'"></span>
                </button>
            </div>
        </template>
    </div>
    <p x-show="searched && !loading && !results.length" x-cloak class="text-body-sm text-on-surface-variant">Tidak ada aset tersedia yang cocok.</p>

    <div>
        <div class="form-label">Aset Dipilih (<span x-text="selected.length"></span>/<span x-text="max"></span>)</div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Asset Tag</th><th>Nama</th><th>Kategori</th><th>Lokasi</th><th class="w-12"></th></tr></thead>
                <tbody>
                    <template x-for="(a, i) in selected" :key="a.id">
                        <tr>
                            <td><span class="tag" x-text="a.asset_tag"></span><input type="hidden" name="asset_ids[]" :value="a.id"></td>
                            <td x-text="a.name"></td>
                            <td x-text="a.category"></td>
                            <td x-text="a.location"></td>
                            <td><button type="button" class="btn btn-ghost btn-sm !text-error" @click="selected.splice(i, 1)" title="Hapus"><span class="material-symbols-outlined !text-[18px]">close</span></button></td>
                        </tr>
                    </template>
                    <tr x-show="!selected.length"><td colspan="5" class="text-center text-on-surface-variant py-space-lg">Belum ada aset dipilih. Cari lalu klik "Tambah".</td></tr>
                </tbody>
            </table>
        </div>
        @error('asset_ids')<p class="form-error">{{ $message }}</p>@enderror
        @error('asset_ids.*')<p class="form-error">{{ $message }}</p>@enderror
    </div>
</div>

@once
@push('scripts')
<script>
function assetPicker(oldIds, max) {
    return {
        q: '', results: [], loading: false, searched: false, max: max,
        selected: oldIds.map(id => ({ id: id, asset_tag: '#' + id, name: '(dipilih sebelumnya)', category: '-', location: '-' })),
        isSelected(id) { return this.selected.some(s => s.id === id); },
        add(a) { if (!this.isSelected(a.id) && this.selected.length < this.max) this.selected.push(a); },
        async search() {
            this.loading = true;
            try {
                const res = await fetch(@js(route('assets.search')) + '?q=' + encodeURIComponent(this.q), { headers: { 'Accept': 'application/json' } });
                this.results = (await res.json()).data || [];
            } catch (e) { this.results = []; }
            this.loading = false; this.searched = true;
        },
    };
}
</script>
@endpush
@endonce
