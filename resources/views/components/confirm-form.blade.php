{{-- Tombol aksi (POST/PUT/DELETE) dengan konfirmasi browser. --}}
@props(['action', 'method' => 'POST', 'confirm' => 'Lanjutkan aksi ini?', 'button' => 'btn btn-secondary'])
<form method="POST" action="{{ $action }}" onsubmit="return confirm({{ Js::from($confirm) }})" {{ $attributes->class('inline') }}>
    @csrf
    @if (strtoupper($method) !== 'POST') @method($method) @endif
    <button type="submit" class="{{ $button }}">{{ $slot }}</button>
</form>
