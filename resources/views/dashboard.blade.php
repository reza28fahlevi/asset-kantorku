@use('App\Enums\AssetStatus')
@extends('layouts.app')

@section('title', 'Dashboard')
@section('subtitle', 'Konsolidasi siklus hidup aset: pengadaan, serah terima, peminjaman, dan disposal')
@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span class="text-on-surface font-semibold">Dashboard</span>
@endsection

@section('actions')
    @permission('procurement.create')
        @if (Route::has('procurements.create'))
            <a href="{{ route('procurements.create') }}" class="btn btn-secondary"><span class="material-symbols-outlined !text-[18px]">add_circle</span> Ajukan Pengadaan</a>
        @endif
    @endpermission
    @permission('asset.create')
        @if (Route::has('assets.create'))
            <a href="{{ route('assets.create') }}" class="btn btn-primary"><span class="material-symbols-outlined !text-[18px]">inventory_2</span> Daftarkan Aset</a>
        @endif
    @endpermission
@endsection

@section('content')
@php
    $count = fn (AssetStatus $s) => (int) ($statusCounts[$s->value] ?? 0);
    $totalAssets = (int) $statusCounts->sum();
    $activeAssets = $totalAssets - $count(AssetStatus::Disposed);
    $money = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
    $canAsset = auth()->user()->hasPermission('asset.view');
    $queueDefs = [
        'procurement_order' => ['Procurement siap dipesan (PO)', 'shopping_cart', 'procurements.index'],
        'procurement_receive' => ['Procurement menunggu penerimaan', 'inventory', 'procurements.index'],
        'assignment_handover' => ['Assignment siap serah terima', 'assignment_ind', 'assignments.index'],
        'loan_checkout' => ['Peminjaman siap diserahkan', 'swap_horiz', 'loans.index'],
        'disposal_execute' => ['Disposal siap dieksekusi', 'delete_sweep', 'disposals.index'],
    ];
    $queue = collect($workQueue)->reject(fn ($v) => $v === null);
    $barColors = ['bg-primary-container', 'bg-status-assigned', 'bg-secondary', 'bg-status-loan', 'bg-status-available', 'bg-status-neutral'];
    $catTotal = max(1, (int) $byCategory->sum('total'));
@endphp

<div class="space-y-gutter">
    {{-- KPI --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-gutter">
        <div class="card p-space-lg bg-primary-container border-primary-container text-white relative overflow-hidden">
            <div class="flex items-start justify-between">
                <p class="text-label-sm uppercase text-on-primary-container">Total Nilai Aset Terdaftar</p>
                <span class="material-symbols-outlined text-secondary-container">account_balance_wallet</span>
            </div>
            <p class="text-headline-sm font-bold mt-space-sm break-words">{{ $money($totalValue) }}</p>
            <p class="text-label-sm font-normal text-on-primary-container mt-1">Nilai perolehan, di luar aset yang telah dihapus</p>
            <div class="absolute bottom-0 left-0 h-1 w-2/3 bg-secondary-container"></div>
        </div>

        <div class="card p-space-lg">
            <div class="flex items-start justify-between">
                <p class="text-label-sm uppercase text-outline">Aset Aktif / Operasional</p>
                <span class="w-10 h-10 rounded-lg flex items-center justify-center bg-blue-50 text-status-assigned"><span class="material-symbols-outlined">devices</span></span>
            </div>
            <p class="text-display-md text-on-surface">{{ number_format($activeAssets, 0, ',', '.') }} <span class="text-body-md text-on-surface-variant">Unit</span></p>
            <p class="text-label-sm font-normal text-on-surface-variant mt-1">
                <span class="text-status-assigned">{{ $count(AssetStatus::Assigned) }} Ditugaskan</span> &middot;
                <span class="text-status-available">{{ $count(AssetStatus::Available) }} Tersedia</span> &middot;
                <span class="text-status-loan">{{ $count(AssetStatus::OnLoan) }} Dipinjam</span>
            </p>
            @php $base = max(1, $activeAssets); @endphp
            <div class="flex h-1.5 rounded-full overflow-hidden bg-surface-subtle mt-space-md">
                <div class="bg-status-assigned" style="width: {{ $count(AssetStatus::Assigned) / $base * 100 }}%"></div>
                <div class="bg-status-available" style="width: {{ $count(AssetStatus::Available) / $base * 100 }}%"></div>
                <div class="bg-status-loan" style="width: {{ $count(AssetStatus::OnLoan) / $base * 100 }}%"></div>
                <div class="bg-status-repair" style="width: {{ $count(AssetStatus::InRepair) / $base * 100 }}%"></div>
            </div>
        </div>

        <div class="card p-space-lg">
            <div class="flex items-start justify-between">
                <p class="text-label-sm uppercase text-outline">Approval Saya</p>
                @if ($myApprovals->isNotEmpty())<x-badge color="pending">Pending</x-badge>@endif
            </div>
            <p class="text-display-md text-on-surface"><span class="text-secondary">{{ $myApprovals->count() }}</span> <span class="text-body-md text-on-surface-variant">Pengajuan</span></p>
            <p class="text-label-sm font-normal text-on-surface-variant mt-1">Menunggu keputusan Anda (maks. 5 ditampilkan)</p>
            @permission('approval.decide')
                <a href="{{ route('approvals.index') }}" class="inline-flex items-center gap-1 text-label-sm text-secondary mt-space-sm hover:underline">Tinjau <span class="material-symbols-outlined !text-[14px]">arrow_forward</span></a>
            @endpermission
        </div>

        <div class="card p-space-lg">
            <div class="flex items-start justify-between">
                <p class="text-label-sm uppercase text-outline">Pemeliharaan &amp; Jatuh Tempo</p>
                <span class="w-10 h-10 rounded-lg flex items-center justify-center bg-red-50 text-status-disposal"><span class="material-symbols-outlined">warning</span></span>
            </div>
            <p class="text-display-md text-on-surface">{{ $overdueLoans->count() + $warrantySoon }} <span class="text-body-md text-status-disposal">Perhatian</span></p>
            <p class="text-label-sm font-normal text-status-disposal mt-1">{{ $overdueLoans->count() }} pinjaman terlambat &middot; {{ $warrantySoon }} garansi &lt; 30 hari</p>
            <p class="text-label-sm font-normal text-status-repair mt-0.5">{{ $count(AssetStatus::InRepair) }} unit dalam perbaikan &middot; {{ $dueSoonCount }} jatuh tempo &le; 3 hari</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-gutter">
        {{-- Kolom utama --}}
        <div class="xl:col-span-2 space-y-gutter">
            @if ($queue->isNotEmpty())
                <x-card title="Antrian Kerja Operasional" icon="bolt">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-space-md">
                        @foreach ($queue as $key => $total)
                            @php [$label, $icon, $route] = $queueDefs[$key]; @endphp
                            <a href="{{ Route::has($route) ? route($route) : '#' }}" class="rounded-lg border border-border-subtle bg-surface-canvas p-space-md hover:border-border-strong transition-colors">
                                <div class="flex items-start justify-between">
                                    <span class="w-9 h-9 rounded bg-surface-card border border-border-subtle flex items-center justify-center"><span class="material-symbols-outlined !text-[18px]">{{ $icon }}</span></span>
                                    <span @class(['text-headline-sm', 'text-secondary' => $total > 0, 'text-outline' => $total == 0])>{{ $total }}</span>
                                </div>
                                <p class="text-body-sm font-semibold text-on-surface mt-space-sm">{{ $label }}</p>
                            </a>
                        @endforeach
                    </div>
                </x-card>
            @endif

            <x-card title="Pinjaman Terlambat" icon="schedule" :padding="false">
                @if ($canAsset)
                    <x-slot:actions><a href="{{ route('loans.index') }}" class="btn btn-ghost btn-sm">Lihat Semua</a></x-slot:actions>
                @endif
                @if ($overdueLoans->isEmpty())
                    <div class="p-space-lg"><x-empty icon="task_alt" message="Tidak ada pinjaman yang terlambat." /></div>
                @else
                    <table class="table">
                        <thead><tr><th>Asset Tag</th><th>Aset</th><th>Peminjam</th><th>Jatuh Tempo</th><th>Keterlambatan</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($overdueLoans as $loan)
                                <tr>
                                    <td class="tag">{{ $loan->asset?->asset_tag }}</td>
                                    <td>{{ $loan->asset?->name }}</td>
                                    <td>{{ $loan->borrower?->name ?? '-' }}</td>
                                    <td class="text-status-disposal">{{ $loan->due_at?->format('d M Y') }}</td>
                                    <td><x-badge color="disposal">{{ $loan->due_at?->diffForHumans(null, true) }}</x-badge></td>
                                    <td class="text-right"><a href="{{ route('loans.show', $loan) }}" class="btn btn-ghost btn-sm"><span class="material-symbols-outlined !text-[18px]">chevron_right</span></a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>

            @if ($canAsset)
                <x-card title="Distribusi Aset per Kategori" icon="donut_small">
                    <x-slot:actions><a href="{{ route('assets.index') }}" class="btn btn-ghost btn-sm">Register Aset</a></x-slot:actions>
                    @if ($byCategory->isEmpty())
                        <x-empty icon="category" message="Belum ada aset terdaftar." />
                    @else
                        <div class="flex h-3 rounded overflow-hidden bg-surface-subtle">
                            @foreach ($byCategory as $i => $cat)
                                <div class="{{ $barColors[$i % 6] }}" style="width: {{ $cat->total / $catTotal * 100 }}%" title="{{ $cat->name }}"></div>
                            @endforeach
                        </div>
                        <div class="grid grid-cols-2 lg:grid-cols-3 gap-space-md mt-space-lg">
                            @foreach ($byCategory as $i => $cat)
                                <div class="rounded-lg bg-surface-canvas border border-border-subtle p-space-md">
                                    <p class="text-label-sm text-on-surface flex items-center gap-space-xs truncate"><span class="w-2 h-2 rounded-full {{ $barColors[$i % 6] }}"></span>{{ $cat->name }}</p>
                                    <p class="text-headline-sm text-on-surface mt-1">{{ round($cat->total / $catTotal * 100) }}%</p>
                                    <p class="text-label-sm font-normal text-on-surface-variant">{{ $cat->total }} Unit</p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-card>
            @endif

            @if ($myAssets->isNotEmpty())
                <x-card title="Aset yang Saya Pegang" icon="devices" :padding="false">
                    <table class="table">
                        <thead><tr><th>Asset Tag</th><th>Nama</th><th>Kategori</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach ($myAssets as $asset)
                                <tr>
                                    <td class="tag">{{ $asset->asset_tag }}</td>
                                    <td>@if ($canAsset)<a href="{{ route('assets.show', $asset) }}" class="hover:underline">{{ $asset->name }}</a>@else{{ $asset->name }}@endif</td>
                                    <td>{{ $asset->category?->name ?? '-' }}</td>
                                    <td><x-badge :enum="$asset->status" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
            @endif
        </div>

        {{-- Kolom samping --}}
        <div class="space-y-gutter">
            <x-card title="Tugas Persetujuan" icon="approval">
                @if ($myApprovals->isNotEmpty())
                    <x-slot:actions><x-badge color="disposal">{{ $myApprovals->count() }} Baru</x-badge></x-slot:actions>
                @endif
                @forelse ($myApprovals as $step)
                    @php $req = $step->approvalRequest; @endphp
                    <div class="rounded-lg border border-border-subtle bg-surface-canvas p-space-md mb-space-sm last:mb-0">
                        <div class="flex items-center justify-between gap-space-sm">
                            @if ($req?->request_type)<x-badge :enum="$req->request_type" />@endif
                            <span class="text-label-sm font-normal text-outline">{{ $req?->submitted_at?->diffForHumans() ?? $step->created_at?->diffForHumans() }}</span>
                        </div>
                        <p class="text-body-sm font-semibold text-on-surface mt-space-sm">Pengajuan #{{ $req?->id }}</p>
                        <p class="text-label-sm font-normal text-on-surface-variant">Pemohon: {{ $req?->requester?->name ?? '-' }} &middot; Tahap {{ $step->step_order }}</p>
                    </div>
                @empty
                    <x-empty icon="inbox" message="Tidak ada persetujuan yang menunggu Anda." />
                @endforelse
                @if ($myApprovals->isNotEmpty())
                    @permission('approval.decide')
                        <a href="{{ route('approvals.index') }}" class="btn btn-accent w-full mt-space-md"><span class="material-symbols-outlined !text-[18px]">inbox</span> Buka Approval Inbox</a>
                    @endpermission
                @endif
            </x-card>

            <x-card title="Siklus Hidup Aset" icon="autorenew">
                @php
                    $life = [
                        ['Tersedia (Stok)', $count(AssetStatus::Available), 'text-status-available', 'Siap ditugaskan atau dipinjamkan'],
                        ['Operasional', $count(AssetStatus::Assigned) + $count(AssetStatus::OnLoan), 'text-status-assigned', $count(AssetStatus::Assigned).' ditugaskan + '.$count(AssetStatus::OnLoan).' dipinjam'],
                        ['Perbaikan', $count(AssetStatus::InRepair), 'text-status-repair', 'Sedang dalam perbaikan'],
                        ['Menuju Disposal', $count(AssetStatus::PendingDisposal) + $count(AssetStatus::Lost), 'text-status-disposal', $count(AssetStatus::PendingDisposal).' menunggu disposal, '.$count(AssetStatus::Lost).' hilang'],
                        ['Dihapus', $count(AssetStatus::Disposed), 'text-status-neutral', 'Telah dihapus dari operasional'],
                    ];
                @endphp
                <ol class="space-y-space-md">
                    @foreach ($life as $i => [$label, $total, $tone, $desc])
                        <li class="flex gap-space-sm">
                            <span class="w-5 h-5 rounded-full bg-primary-container text-white text-[11px] flex items-center justify-center shrink-0 mt-0.5">{{ $i + 1 }}</span>
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between gap-space-sm"><span class="text-body-sm font-semibold">{{ $label }}</span><span class="font-mono text-body-sm {{ $tone }}">{{ $total }} Unit</span></div>
                                <p class="text-label-sm font-normal text-on-surface-variant">{{ $desc }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </x-card>

            @if ($canAsset)
                <x-card title="Aktivitas Aset Terkini" icon="history">
                    @forelse ($recentEvents as $event)
                        <div class="flex gap-space-sm py-space-sm border-b border-border-subtle last:border-0">
                            <span class="w-8 h-8 rounded bg-surface-subtle text-secondary flex items-center justify-center shrink-0"><span class="material-symbols-outlined !text-[16px]">history</span></span>
                            <div class="min-w-0">
                                <p class="text-body-sm text-on-surface">
                                    <span class="font-semibold">{{ $event->performedBy?->name ?? 'Sistem' }}</span>
                                    &middot; {{ $event->event_type?->label() }}
                                    @if ($event->asset)<a href="{{ route('assets.show', $event->asset) }}" class="tag text-status-assigned hover:underline">{{ $event->asset->asset_tag }}</a>@endif
                                    @if ($event->to_status) &rarr; <x-badge :enum="$event->to_status" />@endif
                                </p>
                                <p class="text-label-sm font-normal text-outline">{{ $event->occurred_at?->diffForHumans() }}</p>
                            </div>
                        </div>
                    @empty
                        <x-empty icon="history" message="Belum ada aktivitas." />
                    @endforelse
                </x-card>
            @endif
        </div>
    </div>
</div>
@endsection
