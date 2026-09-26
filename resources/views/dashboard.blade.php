@extends('layouts.app')

@section('title', 'Dashboard')
@section('subtitle', 'Konsolidasi siklus hidup aset: pengadaan, serah terima, peminjaman, dan disposal')
@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span class="text-on-surface font-semibold">Dashboard</span>
@endsection

@section('actions')
    <span class="text-label-sm font-normal text-outline hidden md:inline" id="dashboard-updated"></span>
    <button type="button" class="btn btn-ghost" id="dashboard-refresh" title="Muat ulang semua widget">
        <span class="material-symbols-outlined !text-[18px]">refresh</span> Refresh
    </button>
    @permission('procurement.create')
        <a href="{{ route('procurements.create') }}" class="btn btn-secondary"><span class="material-symbols-outlined !text-[18px]">add_circle</span> Ajukan Pengadaan</a>
    @endpermission
    @permission('asset.create')
        <a href="{{ route('assets.create') }}" class="btn btn-primary"><span class="material-symbols-outlined !text-[18px]">inventory_2</span> Daftarkan Aset</a>
    @endpermission
@endsection

@php
    // Kerangka halaman: tiap slot diisi via AJAX dari route dashboard.widget
    $slot = fn (string $name, string $skeleton = 'card') => in_array($name, $widgets, true)
        ? '<div class="dashboard-widget relative group" data-widget="'.$name.'" data-url="'.route('dashboard.widget', $name).'" data-skeleton="'.$skeleton.'"></div>'
        : '';
@endphp

@section('content')
<div class="space-y-gutter">
    {!! $slot('kpi', 'kpi') !!}

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-gutter">
        <div class="xl:col-span-2 space-y-gutter">
            {!! $slot('queue') !!}
            {!! $slot('overdue', 'table') !!}
            {!! $slot('categories') !!}
            {!! $slot('my-assets', 'table') !!}
        </div>
        <div class="space-y-gutter">
            {!! $slot('approvals', 'list') !!}
            {!! $slot('lifecycle', 'list') !!}
            {!! $slot('activity', 'list') !!}
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    const REFRESH_MS = 120000; // auto-refresh tiap 2 menit selama tab aktif

    const bar = (w) => `<div class="h-3 rounded bg-surface-subtle ${w}"></div>`;
    const skeletons = {
        kpi: `<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-gutter">${
            Array(4).fill(`<div class="card p-space-lg space-y-space-md">${bar('w-1/2')}<div class="h-8 rounded bg-surface-subtle w-2/3"></div>${bar('w-3/4')}</div>`).join('')
        }</div>`,
        card: `<div class="card p-space-lg space-y-space-md">${bar('w-1/3')}<div class="h-24 rounded bg-surface-subtle"></div></div>`,
        table: `<div class="card p-space-lg space-y-space-sm">${bar('w-1/3')}${bar('w-full')}${bar('w-full')}${bar('w-5/6')}</div>`,
        list: `<div class="card p-space-lg space-y-space-md">${bar('w-1/2')}${bar('w-full')}${bar('w-4/5')}${bar('w-2/3')}</div>`,
    };
    function load($w, silent) {
        const xhr = $w.data('xhr');
        if (xhr) xhr.abort();
        if (!silent || !$w.children().length) {
            $w.html(`<div class="animate-pulse">${skeletons[$w.data('skeleton')] || skeletons.card}</div>`);
        } else {
            $w.addClass('opacity-60 transition-opacity');
        }

        $w.data('xhr', $.ajax({ url: $w.data('url'), dataType: 'html', cache: false })
            .done(function (html) {
                $w.html($.trim(html) ? html : '').toggleClass('hidden', !$.trim(html));
                if ($.trim(html)) {
                    $w.append(`<button type="button" class="widget-reload absolute top-2 right-2 z-10 p-1 rounded bg-surface-card/80 text-outline hover:text-on-surface opacity-0 group-hover:opacity-100 transition-opacity" title="Muat ulang widget"><span class="material-symbols-outlined !text-[16px]">refresh</span></button>`);
                }
            })
            .fail(function (x, status) {
                if (status === 'abort') return;
                if (x.status === 401 || x.status === 419) { window.location.reload(); return; }
                $w.removeClass('hidden').html(`<div class="card p-space-lg flex items-center justify-between gap-space-md">
                    <span class="text-body-sm text-error flex items-center gap-space-xs"><span class="material-symbols-outlined !text-[18px]">error</span>Gagal memuat widget.</span>
                    <button type="button" class="btn btn-secondary btn-sm widget-reload">Coba lagi</button></div>`);
            })
            .always(function () {
                $w.removeClass('opacity-60').removeData('xhr');
                $('#dashboard-updated').text('Diperbarui ' + new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }));
            }));
    }

    const $widgets = $('.dashboard-widget');
    const loadAll = (silent) => $widgets.each(function () { load($(this), silent); });

    loadAll(false);
    $('#dashboard-refresh').on('click', () => loadAll(true));
    $(document).off('click.dashboard').on('click.dashboard', '.widget-reload', function () { load($(this).closest('.dashboard-widget'), true); });

    const timer = setInterval(function () { if (!document.hidden) loadAll(true); }, REFRESH_MS);

    // Navigasi AJAX: hentikan auto-refresh & request yang berjalan saat meninggalkan dashboard
    window.AppNav && AppNav.onLeave(function () {
        clearInterval(timer);
        $(document).off('click.dashboard');
        $widgets.each(function () { const x = $(this).data('xhr'); if (x) x.abort(); });
    });
});
</script>
@endpush
