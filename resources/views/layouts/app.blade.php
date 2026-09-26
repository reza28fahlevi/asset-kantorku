@php
    $user = auth()->user();
    $menu = [
        'Main Menu' => [
            ['Dashboard', 'dashboard', 'dashboard', 'dashboard', ['dashboard.view']],
            ['Asset Register', 'assets.index', 'assets.*', 'inventory_2', ['asset.view']],
        ],
        'Transaksi & Alur' => [
            ['Approval Inbox', 'approvals.index', 'approvals.*', 'mark_email_unread', ['approval.decide'], $pendingApprovalCount],
            ['Pengadaan', 'procurements.index', 'procurements.*', 'shopping_cart_checkout', []],
            ['Serah Terima', 'assignments.index', 'assignments.*', 'assignment_ind', []],
            ['Peminjaman', 'loans.index', 'loans.*', 'swap_horiz', []],
            ['Disposal', 'disposals.index', 'disposals.*', 'delete_forever', []],
        ],
        'Master Data' => [
            ['Karyawan', 'masters.employees.index', 'masters.employees.*', 'badge', ['employee.view']],
            ['Departemen', 'masters.departments.index', 'masters.departments.*', 'corporate_fare', ['master.view', 'master.manage']],
            ['Lokasi', 'masters.locations.index', 'masters.locations.*', 'location_on', ['master.view', 'master.manage']],
            ['Kategori Aset', 'masters.categories.index', 'masters.categories.*', 'category', ['master.view', 'master.manage']],
            ['Vendor', 'masters.vendors.index', 'masters.vendors.*', 'storefront', ['master.view', 'master.manage']],
        ],
        'Laporan & Administrasi' => [
            ['Laporan', 'reports.index', 'reports.*', 'analytics', ['report.view']],
            ['Pengguna', 'admin.users.index', 'admin.users.*', 'manage_accounts', ['user.manage']],
            ['Role & Hak Akses', 'admin.roles.index', 'admin.roles.*', 'admin_panel_settings', ['role.manage']],
            ['Pengaturan', 'admin.settings.edit', 'admin.settings.*', 'settings', ['setting.manage']],
            ['Audit Log', 'admin.audit.index', 'admin.audit.*', 'receipt_long', ['audit.view']],
        ],
    ];
    $initials = collect(explode(' ', $user->name))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    @include('layouts.partials.head')
    @stack('head')
</head>
<body class="bg-surface-canvas font-sans text-body-md text-on-surface antialiased" x-data="{ sidebar: false }">
{{-- Sidebar --}}
<div x-cloak x-show="sidebar" @click="sidebar = false" class="fixed inset-0 bg-black/40 z-40 lg:hidden"></div>
<aside :class="sidebar ? 'translate-x-0' : ''" class="fixed left-0 top-0 h-full w-64 bg-primary-container z-50 flex flex-col shadow-[0_1px_8px_rgba(0,0,0,0.08)] transition-transform -translate-x-full lg:translate-x-0">
    <a href="{{ route('dashboard') }}" class="h-16 px-space-md flex items-center gap-space-sm bg-tertiary-container shrink-0">
        <img src="{{ asset('images/logo.svg') }}" alt="Logo" class="h-8 w-8"/>
        <div class="flex flex-col">
            <span class="text-title-md text-on-primary font-bold tracking-tight leading-tight">AssetPro</span>
            <span class="text-label-sm text-on-primary-container uppercase tracking-wider">Enterprise</span>
        </div>
    </a>
    <div class="p-space-md mx-space-sm my-space-sm rounded-lg bg-inverse-surface/40 flex items-center gap-space-md">
        <div class="w-8 h-8 rounded-full bg-secondary-container text-on-secondary-container flex items-center justify-center text-label-md shrink-0">{{ $initials }}</div>
        <div class="flex flex-col min-w-0 flex-1">
            <span class="text-label-md text-on-primary truncate">{{ $user->name }}</span>
            <span class="text-label-sm text-secondary-container uppercase tracking-tight truncate">{{ $user->roles->pluck('name')->implode(', ') ?: 'Pengguna' }}</span>
        </div>
    </div>
    <div class="flex-1 overflow-y-auto px-space-xs py-space-sm space-y-space-md">
        @foreach ($menu as $group => $items)
            @php $items = array_filter($items, fn ($i) => empty($i[4]) || $user->hasAnyPermission($i[4])); @endphp
            @continue(empty($items))
            <div>
                <span class="px-space-md text-label-sm text-on-primary-container uppercase tracking-wider block mb-1">{{ $group }}</span>
                <nav class="space-y-0.5">
                    @foreach ($items as $item)
                        @php $active = request()->routeIs($item[2]); @endphp
                        <a href="{{ route($item[1]) }}" @if ($active) aria-current="page" @endif
                           class="flex items-center justify-between px-space-md py-space-sm rounded transition-colors {{ $active ? 'bg-inverse-surface text-on-primary font-semibold' : 'text-on-primary-container hover:bg-inverse-surface hover:text-on-primary' }}">
                            <span class="flex items-center gap-space-sm">
                                <span class="material-symbols-outlined !text-[18px]">{{ $item[3] }}</span>
                                <span class="text-body-sm">{{ $item[0] }}</span>
                            </span>
                            @if (! empty($item[5]))
                                <span class="bg-error text-on-error text-label-sm px-1.5 py-0.5 rounded-full">{{ $item[5] }}</span>
                            @endif
                        </a>
                    @endforeach
                </nav>
            </div>
        @endforeach
    </div>
    <div class="p-space-md bg-tertiary-container text-center">
        <span class="text-label-sm text-on-tertiary-container">{{ config('app.name', 'AssetPro') }} · Enterprise Edition</span>
    </div>
</aside>

<div class="lg:pl-64 flex flex-col min-h-screen">
    {{-- Topbar --}}
    <header class="sticky top-0 h-16 bg-surface-card/90 backdrop-blur-xl shadow-[0_1px_8px_rgba(0,0,0,0.04)] z-30 flex items-center justify-between px-gutter gap-space-md">
        <div class="flex items-center gap-space-md min-w-0">
            <button type="button" @click="sidebar = true" class="lg:hidden p-space-xs text-on-surface-variant hover:bg-surface-subtle rounded"><span class="material-symbols-outlined">menu</span></button>
            @permission('asset.view')
                <form action="{{ route('assets.index') }}" method="GET" class="relative hidden md:flex items-center">
                    <span class="material-symbols-outlined absolute left-space-md text-outline !text-[18px]">search</span>
                    <input name="q" value="{{ request()->routeIs('assets.index') ? request('q') : '' }}" class="w-80 h-9 pl-9 pr-3 bg-surface-subtle border-0 rounded text-body-sm placeholder:text-outline focus:ring-1 focus:ring-secondary" placeholder="Cari aset, asset tag, serial number..." type="search"/>
                </form>
            @endpermission
        </div>
        <div class="flex items-center gap-space-md">
            {{-- Notifikasi --}}
            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                <button type="button" @click="open = !open" class="relative p-space-xs text-on-surface-variant hover:text-on-surface hover:bg-surface-subtle rounded">
                    <span class="material-symbols-outlined">notifications</span>
                    @if ($unreadNotificationCount)
                        <span class="absolute -top-0.5 -right-0.5 min-w-4 h-4 px-1 rounded-full bg-error text-on-error text-[10px] font-semibold flex items-center justify-center ring-2 ring-surface-card">{{ $unreadNotificationCount > 9 ? '9+' : $unreadNotificationCount }}</span>
                    @endif
                </button>
                <div x-cloak x-show="open" x-transition class="absolute right-0 mt-2 w-80 card overflow-hidden z-50">
                    <div class="card-header py-space-sm">
                        <span class="text-label-md">Notifikasi</span>
                        @if ($unreadNotificationCount)
                            <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="text-label-sm text-secondary hover:underline">Tandai semua dibaca</button></form>
                        @endif
                    </div>
                    <div class="max-h-80 overflow-y-auto divide-y divide-border-subtle">
                        @forelse ($unreadNotifications as $n)
                            <a href="{{ route('notifications.open', $n->id) }}" class="block px-space-md py-space-sm hover:bg-surface-subtle">
                                <p class="text-body-sm text-on-surface font-medium">{{ $n->data['title'] ?? 'Notifikasi' }}</p>
                                @if (! empty($n->data['message']))<p class="text-label-sm font-normal text-on-surface-variant line-clamp-2">{{ $n->data['message'] }}</p>@endif
                                <p class="text-label-sm font-normal text-outline mt-0.5">{{ $n->created_at->diffForHumans() }}</p>
                            </a>
                        @empty
                            <p class="px-space-md py-space-lg text-center text-body-sm text-outline">Tidak ada notifikasi baru.</p>
                        @endforelse
                    </div>
                    <a href="{{ route('notifications.index') }}" class="block text-center py-space-sm text-label-md text-on-surface-variant hover:bg-surface-subtle border-t border-border-subtle">Lihat semua</a>
                </div>
            </div>
            @if ($pendingApprovalCount)
                <a href="{{ route('approvals.index') }}" class="hidden sm:flex items-center gap-space-xs px-space-md py-space-xs bg-surface-subtle hover:bg-surface-container rounded text-on-surface-variant hover:text-on-surface">
                    <span class="material-symbols-outlined !text-[18px] text-secondary">pending_actions</span>
                    <span class="text-label-md">Tugas Approval</span>
                    <span class="bg-secondary-container text-on-secondary-container text-label-sm px-1.5 py-0.5 rounded-full">{{ $pendingApprovalCount }}</span>
                </a>
            @endif
            {{-- User menu --}}
            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                <button type="button" @click="open = !open" class="flex items-center gap-space-sm pl-space-sm">
                    <span class="w-8 h-8 rounded-full bg-primary-container text-on-primary flex items-center justify-center text-label-md">{{ $initials }}</span>
                    <span class="hidden md:flex flex-col text-left">
                        <span class="text-label-md text-on-surface leading-tight">{{ $user->name }}</span>
                        <span class="text-label-sm text-status-available flex items-center gap-1 leading-tight"><span class="w-1.5 h-1.5 rounded-full bg-status-available"></span>Online</span>
                    </span>
                    <span class="material-symbols-outlined text-outline !text-[18px]">expand_more</span>
                </button>
                <div x-cloak x-show="open" x-transition class="absolute right-0 mt-2 w-52 card py-1 z-50">
                    <a href="{{ route('profile.edit') }}" class="flex items-center gap-space-sm px-space-md py-space-sm text-body-sm hover:bg-surface-subtle"><span class="material-symbols-outlined !text-[18px]">person</span>Profil Saya</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button class="w-full flex items-center gap-space-sm px-space-md py-space-sm text-body-sm text-error hover:bg-surface-subtle"><span class="material-symbols-outlined !text-[18px]">logout</span>Keluar</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    {{-- Breadcrumb, judul & aksi halaman --}}
    <div class="w-full px-gutter py-space-md bg-surface-card border-b border-border-subtle flex flex-wrap items-center justify-between gap-space-md">
        <div class="min-w-0">
            <div class="flex items-center gap-space-xs text-label-md text-outline flex-wrap">
                <a href="{{ route('dashboard') }}" class="hover:text-on-surface flex items-center gap-1"><span class="material-symbols-outlined !text-[16px]">home</span>Beranda</a>
                @yield('breadcrumb')
            </div>
            <h1 class="text-headline-sm text-on-surface mt-1">@yield('title')</h1>
            @hasSection('subtitle')<p class="text-body-sm text-on-surface-variant">@yield('subtitle')</p>@endif
        </div>
        <div class="flex flex-wrap items-center gap-space-sm">@yield('actions')</div>
    </div>

    <main class="w-full px-gutter py-space-lg flex-1 space-y-space-lg">
        @include('layouts.partials.flash')
        @yield('content')
    </main>
    <footer class="px-gutter py-space-md text-label-sm font-normal text-outline border-t border-border-subtle">
        &copy; {{ date('Y') }} {{ config('app.name', 'AssetPro') }} — Sistem Manajemen Aset Kantor
    </footer>
</div>
@stack('scripts')
</body>
</html>
