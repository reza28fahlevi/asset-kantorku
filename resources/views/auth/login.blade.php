@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
<div class="w-full max-w-md">
    <div class="flex flex-col items-center text-center mb-space-xl">
        <img src="{{ asset('images/logo.svg') }}" alt="{{ config('app.name', 'AssetPro') }}" class="h-14 w-14 mb-space-md">
        <h1 class="text-headline-sm text-white">{{ config('app.name', 'AssetPro') }}</h1>
        <p class="text-body-sm text-on-primary-container mt-1">Sistem Manajemen Aset Kantor</p>
    </div>

    <div class="card p-space-xl">
        <h2 class="text-title-md text-on-surface">Masuk ke akun Anda</h2>
        <p class="text-body-sm text-on-surface-variant mt-1 mb-space-lg">Gunakan email dan password perusahaan.</p>

        <form method="POST" action="{{ route('login') }}" class="space-y-space-md">
            @csrf
            <x-field label="Email" name="email" :required="true">
                <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-input w-full" autocomplete="username" autofocus required>
            </x-field>
            <x-field label="Password" name="password" :required="true">
                <div x-data="{ show: false }" class="relative">
                    <input :type="show ? 'text' : 'password'" type="password" id="password" name="password" class="form-input w-full pr-10" autocomplete="current-password" required>
                    <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 px-space-sm text-outline hover:text-on-surface" tabindex="-1">
                        <span class="material-symbols-outlined !text-[18px]" x-text="show ? 'visibility_off' : 'visibility'">visibility</span>
                    </button>
                </div>
            </x-field>
            <label class="flex items-center gap-space-sm text-body-sm text-on-surface-variant">
                <input type="checkbox" name="remember" value="1" class="rounded border-border-strong" @checked(old('remember'))>
                Ingat saya
            </label>
            <button type="submit" class="btn btn-primary w-full">
                <span class="material-symbols-outlined !text-[18px]">login</span> Masuk
            </button>
        </form>
    </div>
    <p class="text-center text-label-sm font-normal text-on-primary-container mt-space-lg">&copy; {{ date('Y') }} {{ config('app.name', 'AssetPro') }}</p>
</div>
@endsection
