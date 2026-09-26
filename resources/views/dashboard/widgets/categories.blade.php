@php
    $barColors = ['bg-primary-container', 'bg-status-assigned', 'bg-secondary', 'bg-status-loan', 'bg-status-available', 'bg-status-neutral'];
    $catTotal = max(1, (int) $byCategory->sum('total'));
@endphp
<x-card title="Distribusi Aset per Kategori" icon="donut_small">
    <x-slot:actions><a href="{{ route('assets.index') }}" class="btn btn-ghost btn-sm">Register Aset</a></x-slot:actions>
    @if ($byCategory->isEmpty())
        <x-empty icon="category" message="Belum ada aset terdaftar." />
    @else
        <div class="flex h-3 rounded overflow-hidden bg-surface-subtle">
            @foreach ($byCategory as $i => $cat)
                <div class="{{ $barColors[$i % 6] }}" style="width: {{ $cat->total / $catTotal * 100 }}%" title="{{ $cat->name }}"></div>
            @endforeach
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-space-md mt-space-lg">
            @foreach ($byCategory as $i => $cat)
                <div class="rounded-lg bg-surface-canvas border border-border-subtle p-space-md">
                    <p class="text-label-sm text-on-surface flex items-center gap-space-xs truncate"><span class="w-2 h-2 rounded-full {{ $barColors[$i % 6] }}"></span>{{ $cat->name }}</p>
                    <p class="text-headline-sm text-on-surface mt-1">{{ round($cat->total / $catTotal * 100) }}%</p>
                    <p class="text-label-sm font-normal text-on-surface-variant">{{ $cat->total }} Unit</p>
                </div>
            @endforeach
        </div>
    @endif
</x-card>
