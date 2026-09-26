<x-card title="Aktivitas Aset Terkini" icon="history">
    @forelse ($recentEvents as $event)
        <div class="flex gap-space-sm py-space-sm border-b border-border-subtle last:border-0">
            <span class="w-8 h-8 rounded bg-surface-subtle text-secondary flex items-center justify-center shrink-0"><span class="material-symbols-outlined !text-[16px]">history</span></span>
            <div class="min-w-0">
                <p class="text-body-sm text-on-surface">
                    <span class="font-semibold">{{ $event->performedBy?->name ?? 'Sistem' }}</span>
                    &middot; {{ $event->event_type?->label() }}
                    @if ($event->asset)<a href="{{ route('assets.show', $event->asset) }}" class="tag text-status-assigned hover:underline">{{ $event->asset->asset_tag }}</a>@endif
                    @if ($event->to_status) &rarr; <x-badge :enum="$event->to_status" />@endif
                </p>
                <p class="text-label-sm font-normal text-outline">{{ $event->occurred_at?->diffForHumans() }}</p>
            </div>
        </div>
    @empty
        <x-empty icon="history" message="Belum ada aktivitas." />
    @endforelse
</x-card>
