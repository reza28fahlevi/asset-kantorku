{{--
    Tombol batalkan permintaan. Bila sudah disetujui ($approved), wajib alasan (min. 5 karakter)
    lewat dialog; selain itu cukup konfirmasi.
--}}
@props(['action', 'approved' => false, 'label' => 'permintaan ini'])
@if ($approved)
    <div x-data="{ open: false, reason: @js(old('cancel_reason', '')) }" class="inline">
        <button type="button" class="btn btn-danger" @click="open = true">
            <span class="material-symbols-outlined !text-[18px]">cancel</span> Batalkan
        </button>
        <div x-cloak x-show="open" x-transition.opacity class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-space-lg" role="dialog" @keydown.escape.window="open = false">
            <form method="POST" action="{{ $action }}" class="card w-full max-w-md text-left" @click.outside="open = false"
                  @submit="if (reason.trim().length < 5) { $event.preventDefault(); alert('Alasan pembatalan wajib diisi minimal 5 karakter.'); }">
                @csrf
                <div class="card-header">
                    <h3 class="card-title flex items-center gap-space-sm"><span class="material-symbols-outlined text-error">cancel</span> Batalkan {{ $label }}</h3>
                    <button type="button" class="btn btn-ghost btn-sm" @click="open = false"><span class="material-symbols-outlined !text-[18px]">close</span></button>
                </div>
                <div class="card-body space-y-space-md">
                    <div class="rounded border border-amber-200 bg-amber-50 p-space-sm text-body-sm text-on-surface-variant">
                        Permintaan ini <strong>sudah disetujui</strong>. Pembatalan bersifat final, tercatat di audit log, dan pemohon akan diberi tahu.
                    </div>
                    <x-field label="Alasan pembatalan" name="cancel_reason" :required="true">
                        <textarea name="cancel_reason" x-model="reason" rows="3" maxlength="1000" class="form-input" placeholder="mis. Kebutuhan dibatalkan, aset dialihkan ke proyek lain..." required></textarea>
                    </x-field>
                    <div class="flex justify-end gap-space-sm">
                        <button type="button" class="btn btn-ghost" @click="open = false">Kembali</button>
                        <button type="submit" class="btn btn-danger">Batalkan Permintaan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@else
    <x-confirm-form :action="$action" method="POST" :confirm="'Batalkan '.$label.'?'" button="btn btn-danger">
        <span class="material-symbols-outlined !text-[18px]">cancel</span> Batalkan
    </x-confirm-form>
@endif
