@props(['icon' => 'inbox', 'message' => 'Belum ada data.'])
<div {{ $attributes->class('py-space-xl text-center') }}>
    <span class="material-symbols-outlined !text-[40px] text-outline-variant">{{ $icon }}</span>
    <p class="mt-space-sm text-body-sm text-outline">{{ $message }}</p>
    {{ $slot }}
</div>
