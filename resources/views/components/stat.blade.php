{{-- Kartu KPI. color: available|assigned|loan|repair|disposal|pending|neutral --}}
@props(['label', 'value', 'icon' => null, 'color' => 'neutral', 'href' => null, 'hint' => null])
@php
    $tone = [
        'available' => 'text-status-available bg-emerald-50',
        'assigned' => 'text-status-assigned bg-blue-50',
        'loan' => 'text-status-loan bg-violet-50',
        'repair' => 'text-status-repair bg-orange-50',
        'disposal' => 'text-status-disposal bg-red-50',
        'pending' => 'text-status-pending bg-amber-50',
        'neutral' => 'text-status-neutral bg-slate-100',
    ][$color] ?? 'text-status-neutral bg-slate-100';
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class(['card p-space-lg flex items-start justify-between gap-space-md', 'hover:border-border-strong transition-colors' => $href]) }}>
    <div class="min-w-0">
        <p class="text-label-sm uppercase text-outline">{{ $label }}</p>
        <p class="text-display-md text-on-surface mt-1 truncate">{{ $value }}</p>
        @if ($hint)<p class="text-label-sm font-normal text-on-surface-variant mt-0.5">{{ $hint }}</p>@endif
    </div>
    @if ($icon)
        <span class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 {{ $tone }}"><span class="material-symbols-outlined">{{ $icon }}</span></span>
    @endif
</{{ $tag }}>
