{{-- Kartu konten. Slot opsional: $actions (di header). padding=false untuk tabel full-width. --}}
@props(['title' => null, 'icon' => null, 'padding' => true])
<section {{ $attributes->class('card') }}>
    @if ($title || isset($actions))
        <div class="card-header">
            <h2 class="card-title flex items-center gap-space-sm">
                @if ($icon)<span class="material-symbols-outlined text-secondary">{{ $icon }}</span>@endif{{ $title }}
            </h2>
            @isset($actions)<div class="flex flex-wrap items-center gap-space-sm">{{ $actions }}</div>@endisset
        </div>
    @endif
    <div @class(['card-body' => $padding, 'overflow-x-auto' => ! $padding])>{{ $slot }}</div>
</section>
