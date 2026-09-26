{{-- Modal pengembalian. Butuh parent x-data="{ open }", $action (URL), $locations, $currentLocationId, $locationRequired, $title. --}}
<div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-space-md text-left whitespace-normal" @keydown.escape.window="open = false">
    <div class="bg-surface-card rounded-lg shadow-xl w-full max-w-lg" @click.outside="open = false">
        <div class="card-header"><h3 class="card-title">{{ $title }}</h3></div>
        <form method="POST" data-ajax-form action="{{ $action }}" enctype="multipart/form-data" class="card-body space-y-space-md">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
                <x-field label="Tanggal Kembali" :required="true">
                    <input type="datetime-local" name="returned_at" value="{{ now()->format('Y-m-d\TH:i') }}" max="{{ now()->format('Y-m-d\TH:i') }}" class="form-input" required>
                </x-field>
                <x-field label="Kondisi Saat Kembali" :required="true">
                    <select name="condition_in" class="form-input" required>
                        @foreach (App\Enums\AssetCondition::cases() as $c)<option value="{{ $c->value }}">{{ $c->label() }}</option>@endforeach
                    </select>
                </x-field>
                <x-field label="Status Aset Berikutnya" :required="true">
                    <select name="next_status" class="form-input" required>
                        @foreach ([App\Enums\AssetStatus::Available, App\Enums\AssetStatus::InRepair, App\Enums\AssetStatus::Lost] as $s)<option value="{{ $s->value }}">{{ $s->label() }}</option>@endforeach
                    </select>
                </x-field>
                <x-field label="Lokasi Penyimpanan" :required="$locationRequired">
                    <select name="location_id" class="form-input" @if ($locationRequired) required @endif>
                        @unless ($locationRequired)<option value="">- Lokasi semula -</option>@endunless
                        @foreach ($locations as $l)<option value="{{ $l->id }}" @selected($l->id === $currentLocationId)>{{ $l->name }}</option>@endforeach
                    </select>
                </x-field>
            </div>
            <x-field label="Catatan">
                <textarea name="return_notes" rows="2" maxlength="2000" class="form-input"></textarea>
            </x-field>
            <x-field label="Lampiran (maks. 5 berkas)">
                <input type="file" name="attachments[]" multiple class="form-input">
            </x-field>
            <div class="flex justify-end gap-space-sm pt-space-sm">
                <button type="button" class="btn btn-ghost" @click="open = false">Batal</button>
                <button class="btn btn-primary">Simpan Pengembalian</button>
            </div>
        </form>
    </div>
</div>
