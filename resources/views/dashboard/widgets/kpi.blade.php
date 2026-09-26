@use('App\Enums\AssetStatus')
@php
    $count = fn (AssetStatus $s) => (int) ($statusCounts[$s->value] ?? 0);
    $activeAssets = (int) $statusCounts->sum() - $count(AssetStatus::Disposed);
    $base = max(1, $activeAssets);
@endphp
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-gutter">
    <div class="card p-space-lg bg-primary-container border-primary-container text-white relative overflow-hidden">
        <div class="flex items-start justify-between">
            <p class="text-label-sm uppercase text-on-primary-container">Total Nilai Aset Terdaftar</p>
            <span class="material-symbols-outlined text-secondary-container">account_balance_wallet</span>
        </div>
        <p class="text-headline-sm font-bold mt-space-sm break-words">Rp {{ number_format($totalValue, 0, ',', '.') }}</p>
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
            @if ($myApprovalCount)<x-badge color="pending">Pending</x-badge>@endif
        </div>
        <p class="text-display-md text-on-surface"><span class="text-secondary">{{ $myApprovalCount }}</span> <span class="text-body-md text-on-surface-variant">Pengajuan</span></p>
        <p class="text-label-sm font-normal text-on-surface-variant mt-1">Menunggu keputusan Anda</p>
        @permission('approval.decide')
            <a href="{{ route('approvals.index') }}" class="inline-flex items-center gap-1 text-label-sm text-secondary mt-space-sm hover:underline">Tinjau <span class="material-symbols-outlined !text-[14px]">arrow_forward</span></a>
        @endpermission
    </div>

    <div class="card p-space-lg">
        <div class="flex items-start justify-between">
            <p class="text-label-sm uppercase text-outline">Pemeliharaan &amp; Jatuh Tempo</p>
            <span class="w-10 h-10 rounded-lg flex items-center justify-center bg-red-50 text-status-disposal"><span class="material-symbols-outlined">warning</span></span>
        </div>
        <p class="text-display-md text-on-surface">{{ $overdueCount + $warrantySoon }} <span class="text-body-md text-status-disposal">Perhatian</span></p>
        <p class="text-label-sm font-normal text-status-disposal mt-1">{{ $overdueCount }} pinjaman terlambat &middot; {{ $warrantySoon }} garansi &lt; 30 hari</p>
        <p class="text-label-sm font-normal text-status-repair mt-0.5">{{ $count(AssetStatus::InRepair) }} unit dalam perbaikan &middot; {{ $dueSoonCount }} jatuh tempo &le; 3 hari</p>
    </div>
</div>
