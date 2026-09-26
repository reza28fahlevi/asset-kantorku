@use('App\Enums\AssetStatus')
@php
    $count = fn (AssetStatus $s) => (int) ($statusCounts[$s->value] ?? 0);
    $life = [
        ['Tersedia (Stok)', $count(AssetStatus::Available), 'text-status-available', 'Siap ditugaskan atau dipinjamkan'],
        ['Operasional', $count(AssetStatus::Assigned) + $count(AssetStatus::OnLoan), 'text-status-assigned', $count(AssetStatus::Assigned).' ditugaskan + '.$count(AssetStatus::OnLoan).' dipinjam'],
        ['Perbaikan', $count(AssetStatus::InRepair), 'text-status-repair', 'Sedang dalam perbaikan'],
        ['Menuju Disposal', $count(AssetStatus::PendingDisposal) + $count(AssetStatus::Lost), 'text-status-disposal', $count(AssetStatus::PendingDisposal).' menunggu disposal, '.$count(AssetStatus::Lost).' hilang'],
        ['Dihapus', $count(AssetStatus::Disposed), 'text-status-neutral', 'Telah dihapus dari operasional'],
    ];
@endphp
<x-card title="Siklus Hidup Aset" icon="autorenew">
    <ol class="space-y-space-md">
        @foreach ($life as $i => [$label, $total, $tone, $desc])
            <li class="flex gap-space-sm">
                <span class="w-5 h-5 rounded-full bg-primary-container text-white text-[11px] flex items-center justify-center shrink-0 mt-0.5">{{ $i + 1 }}</span>
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between gap-space-sm"><span class="text-body-sm font-semibold">{{ $label }}</span><span class="font-mono text-body-sm {{ $tone }}">{{ $total }} Unit</span></div>
                    <p class="text-label-sm font-normal text-on-surface-variant">{{ $desc }}</p>
                </div>
            </li>
        @endforeach
    </ol>
</x-card>
