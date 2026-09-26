{{-- Tombol aksi (POST/PUT/DELETE) via AJAX dengan konfirmasi & hasil SweetAlert. --}}
@props(['action', 'method' => 'POST', 'confirm' => 'Lanjutkan aksi ini?', 'button' => 'btn btn-secondary'])
<form method="POST" data-ajax-form action="{{ $action }}" data-confirm="{{ $confirm }}" {{ $attributes->class('inline') }}>
    @csrf
    @if (strtoupper($method) !== 'POST') @method($method) @endif
    <button type="submit" class="{{ $button }}">{{ $slot }}</button>
</form>
