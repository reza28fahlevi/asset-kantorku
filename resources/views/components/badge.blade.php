{{-- Badge status. color: available|assigned|loan|repair|disposal|pending|neutral, atau :enum="$model->status" (enum dengan label() + color()). --}}
@props(['color' => null, 'enum' => null])
@php
    $color = $color ?? $enum?->color() ?? 'neutral';
    $map = [
        'available' => 'bg-emerald-50 text-status-available ring-emerald-200',
        'assigned' => 'bg-blue-50 text-status-assigned ring-blue-200',
        'loan' => 'bg-violet-50 text-status-loan ring-violet-200',
        'repair' => 'bg-orange-50 text-status-repair ring-orange-200',
        'disposal' => 'bg-red-50 text-status-disposal ring-red-200',
        'pending' => 'bg-amber-50 text-status-pending ring-amber-200',
        'neutral' => 'bg-slate-100 text-status-neutral ring-slate-200',
    ];
@endphp
<span {{ $attributes->class(['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-label-sm ring-1 ring-inset whitespace-nowrap', $map[$color] ?? $map['neutral']]) }}>
    <span class="w-1.5 h-1.5 rounded-full bg-current"></span>{{ $slot->isEmpty() ? $enum?->label() : $slot }}
</span>
