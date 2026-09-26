{{-- Modal pengajuan perpanjangan. Butuh parent x-data="{ extend }" dan $loan (AssetLoan). --}}
<div x-show="extend" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-space-md text-left whitespace-normal" @keydown.escape.window="extend = false">
    <div class="bg-surface-card rounded-lg shadow-xl w-full max-w-md" @click.outside="extend = false">
        <div class="card-header"><h3 class="card-title">Perpanjang {{ $loan->asset->asset_tag }} &mdash; {{ $loan->asset->name }}</h3></div>
        <form method="POST" action="{{ route('loans.extend', $loan) }}" class="card-body space-y-space-md">
            @csrf
            <p class="text-body-sm text-on-surface-variant">Jatuh tempo saat ini: <strong class="text-on-surface">{{ $loan->due_at->format('d M Y') }}</strong>. Perpanjangan memerlukan approval.</p>
            <x-field label="Jatuh Tempo Baru" :required="true">
                <input type="date" name="requested_due_date" min="{{ max(now()->addDay(), $loan->due_at->copy()->addDay())->toDateString() }}" class="form-input" required>
            </x-field>
            <x-field label="Alasan" :required="true" hint="Minimal 5 karakter.">
                <textarea name="reason" rows="3" minlength="5" maxlength="2000" class="form-input" required></textarea>
            </x-field>
            <div class="flex justify-end gap-space-sm pt-space-sm">
                <button type="button" class="btn btn-ghost" @click="extend = false">Batal</button>
                <button class="btn btn-primary">Ajukan Perpanjangan</button>
            </div>
        </form>
    </div>
</div>
