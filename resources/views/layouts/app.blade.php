@php
    $user = auth()->user();
    // Menu sidebar. Item tanpa 'children' = link langsung; dengan 'children' = accordion.
    // 'perm' kosong = semua user login; 'active' = pola nama route untuk status aktif.
    $link = fn ($label, $route, $active, $perm = [], $badge = null) => compact('label', 'route', 'active', 'perm', 'badge');
    $menu = [
        ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'dashboard', 'active' => 'dashboard', 'perm' => ['dashboard.view']],
        ['label' => 'Asset Register', 'icon' => 'inventory_2', 'active' => ['assets.*'], 'perm' => ['asset.view'], 'children' => [
            $link('Katalog Aset', 'assets.index', ['assets.index', 'assets.show', 'assets.edit'], ['asset.view']),
            $link('Registrasi Aset', 'assets.create', 'assets.create', ['asset.create']),
        ]],
        ['label' => 'Approval Inbox', 'icon' => 'mark_email_unread', 'route' => 'approvals.index', 'active' => 'approvals.*', 'perm' => ['approval.decide'], 'badge' => $pendingApprovalCount],
        ['label' => 'Pengadaan Aset', 'icon' => 'shopping_cart_checkout', 'active' => ['procurements.*'], 'children' => [
            $link('Ajukan Pengadaan', 'procurements.create', 'procurements.create', ['procurement.create']),
            $link('Daftar Pengadaan', 'procurements.index', ['procurements.index', 'procurements.show', 'procurements.receive-form']),
        ]],
        ['label' => 'Serah Terima Aset', 'icon' => 'assignment_ind', 'active' => ['assignments.*'], 'children' => [
            $link('Ajukan Serah Terima', 'assignments.create', 'assignments.create', ['assignment.create']),
            $link('Daftar Pengajuan', 'assignments.index', ['assignments.index', 'assignments.show']),
            $link('Aset Dipegang', 'assignments.active', 'assignments.active'),
        ]],
        ['label' => 'Peminjaman Aset', 'icon' => 'swap_horiz', 'active' => ['loans.*'], 'children' => [
            $link('Ajukan Peminjaman', 'loans.create', 'loans.create', ['loan.create']),
            $link('Daftar Pengajuan', 'loans.index', ['loans.index', 'loans.show']),
            $link('Pinjaman Aktif', 'loans.active', 'loans.active'),
        ]],
        ['label' => 'Disposal Aset', 'icon' => 'delete_forever', 'active' => ['disposals.*'], 'children' => [
            $link('Ajukan Penghapusan', 'disposals.create', 'disposals.create', ['disposal.create']),
            $link('Daftar Disposal', 'disposals.index', ['disposals.index', 'disposals.show']),
        ]],
        ['label' => 'Master Data', 'icon' => 'database', 'active' => ['masters.*'], 'children' => [
            $link('Karyawan', 'masters.employees.index', 'masters.employees.*', ['employee.view']),
            $link('Departemen', 'masters.departments.index', 'masters.departments.*', ['master.view', 'master.manage']),
            $link('Lokasi', 'masters.locations.index', 'masters.locations.*', ['master.view', 'master.manage']),
            $link('Kategori Aset', 'masters.categories.index', 'masters.categories.*', ['master.view', 'master.manage']),
            $link('Vendor', 'masters.vendors.index', 'masters.vendors.*', ['master.view', 'master.manage']),
        ]],
        ['label' => 'Laporan', 'icon' => 'analytics', 'route' => 'reports.index', 'active' => 'reports.*', 'perm' => ['report.view']],
        ['label' => 'Administrasi', 'icon' => 'admin_panel_settings', 'active' => ['admin.*'], 'children' => [
            $link('Pengguna', 'admin.users.index', 'admin.users.*', ['user.manage']),
            $link('Role & Hak Akses', 'admin.roles.index', 'admin.roles.*', ['role.manage']),
            $link('Pengaturan', 'admin.settings.edit', 'admin.settings.*', ['setting.manage']),
            $link('Audit Log', 'admin.audit.index', 'admin.audit.*', ['audit.view']),
        ]],
    ];
    $allowed = fn ($item) => empty($item['perm']) || $user->hasAnyPermission($item['perm']);
    $menu = collect($menu)
        ->filter($allowed)
        ->map(function ($item) use ($allowed) {
            if (isset($item['children'])) {
                $item['children'] = array_values(array_filter($item['children'], $allowed));
            }
            return $item;
        })
        ->reject(fn ($item) => isset($item['children']) && empty($item['children']));
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
            <span class="text-title-md text-on-primary font-bold tracking-tight leading-tight">AssetKu</span>
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
    {{-- Menu accordion: grup yang berisi halaman aktif terbuka otomatis, hanya satu grup terbuka sekaligus --}}
    <nav class="flex-1 overflow-y-auto px-space-xs py-space-sm space-y-0.5"
         x-data="{ open: @js($menu->search(fn ($i) => isset($i['children']) && request()->routeIs(...(array) $i['active']))) }">
        <span class="px-space-md text-label-sm text-on-primary-container uppercase tracking-wider block mb-1">Menu Utama</span>
        @foreach ($menu as $key => $item)
            @php $groupActive = request()->routeIs(...(array) $item['active']); @endphp
            @if (isset($item['children']))
                <div>
                    <button type="button" @click="open = open === {{ $key }} ? null : {{ $key }}" :aria-expanded="open === {{ $key }}"
                            class="w-full flex items-center justify-between px-space-md py-space-sm rounded transition-colors {{ $groupActive ? 'text-on-primary font-semibold' : 'text-on-primary-container hover:bg-inverse-surface hover:text-on-primary' }}">
                        <span class="flex items-center gap-space-sm">
                            <span class="material-symbols-outlined !text-[18px] {{ $groupActive ? 'text-secondary-container' : '' }}">{{ $item['icon'] }}</span>
                            <span class="text-body-sm">{{ $item['label'] }}</span>
                        </span>
                        <span class="material-symbols-outlined !text-[18px] transition-transform" :class="open === {{ $key }} && 'rotate-180'">expand_more</span>
                    </button>
                    <div x-show="open === {{ $key }}" x-collapse @if (! $groupActive) x-cloak @endif class="mt-0.5 mb-1 ml-[21px] pl-space-sm border-l border-inverse-surface space-y-0.5">
                        @foreach ($item['children'] as $child)
                            @php $active = request()->routeIs(...(array) $child['active']); @endphp
                            <a href="{{ route($child['route']) }}" @if ($active) aria-current="page" @endif
                               class="block px-space-md py-1.5 rounded text-body-sm transition-colors {{ $active ? 'bg-inverse-surface text-on-primary font-semibold' : 'text-on-primary-container hover:bg-inverse-surface hover:text-on-primary' }}">
                                {{ $child['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @else
                <a href="{{ route($item['route']) }}" @if ($groupActive) aria-current="page" @endif
                   class="flex items-center justify-between px-space-md py-space-sm rounded transition-colors {{ $groupActive ? 'bg-inverse-surface text-on-primary font-semibold' : 'text-on-primary-container hover:bg-inverse-surface hover:text-on-primary' }}">
                    <span class="flex items-center gap-space-sm">
                        <span class="material-symbols-outlined !text-[18px]">{{ $item['icon'] }}</span>
                        <span class="text-body-sm">{{ $item['label'] }}</span>
                    </span>
                    @if (! empty($item['badge']))
                        <span class="bg-error text-on-error text-label-sm px-1.5 py-0.5 rounded-full">{{ $item['badge'] }}</span>
                    @endif
                </a>
            @endif
        @endforeach
    </nav>
    <div class="p-space-md bg-tertiary-container text-center">
        <span class="text-label-sm text-on-tertiary-container">{{ config('app.name', 'AssetKu') }} · Enterprise Edition</span>
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
        &copy; {{ date('Y') }} {{ config('app.name', 'AssetKu') }} — Sistem Manajemen Aset Kantor
    </footer>
</div>
@stack('scripts')
</body>
</html>
