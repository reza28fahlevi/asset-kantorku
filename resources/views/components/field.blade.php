{{-- Pembungkus field form: label + slot input + hint + pesan error. --}}
@props(['label' => null, 'name' => null, 'required' => false, 'hint' => null])
<div {{ $attributes }}>
    @if ($label)
        <label @if ($name) for="{{ $name }}" @endif class="form-label">{{ $label }}@if ($required)<span class="text-error"> *</span>@endif</label>
    @endif
    {{ $slot }}
    @if ($hint)<p class="form-hint">{{ $hint }}</p>@endif
    @if ($name)
        @error($name)<p class="form-error">{{ $message }}</p>@enderror
    @endif
</div>
