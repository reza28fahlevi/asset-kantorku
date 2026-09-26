@php
    $flashTypes = [
        'success' => ['check_circle', 'text-status-available', 'bg-emerald-50 border-emerald-200'],
        'error' => ['error', 'text-error', 'bg-red-50 border-red-200'],
        'warning' => ['warning', 'text-status-pending', 'bg-amber-50 border-amber-200'],
        'info' => ['info', 'text-status-assigned', 'bg-blue-50 border-blue-200'],
    ];
@endphp
@foreach ($flashTypes as $key => [$icon, $color, $box])
    @if (session($key))
        <div x-data="{ show: true }" x-show="show" class="flex items-start gap-space-sm px-space-md py-space-sm rounded border {{ $box }}">
            <span class="material-symbols-outlined {{ $color }}">{{ $icon }}</span>
            <p class="flex-1 text-body-sm text-on-surface">{{ session($key) }}</p>
            <button type="button" @click="show = false" class="text-outline hover:text-on-surface"><span class="material-symbols-outlined !text-[18px]">close</span></button>
        </div>
    @endif
@endforeach
@if ($errors->any())
    <div class="flex items-start gap-space-sm px-space-md py-space-sm rounded border bg-red-50 border-red-200">
        <span class="material-symbols-outlined text-error">error</span>
        <div class="text-body-sm text-on-error-container">
            <p class="font-semibold">Terdapat {{ $errors->count() }} kesalahan pada input:</p>
            <ul class="list-disc ml-5">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    </div>
@endif
