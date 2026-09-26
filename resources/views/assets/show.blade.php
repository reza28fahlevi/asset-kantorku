@extends('layouts.app')

@php
    use App\Enums\AssetStatus;
    $st = $asset->status;
    $holder = $asset->currentHolder();
    $money = fn ($v) => $v === null ? '-' : 'Rp '.number_format((float) $v, 0, ',', '.');
    $statusActions = [];
    if ($st === AssetStatus::Available) {
        $statusActions[] = ['repair', 'Masuk Perbaikan', 'build', 'btn-accent'];
    }
    if ($st === AssetStatus::InRepair) {
        $statusActions[] = ['repaired', 'Selesai Perbaikan', 'task_alt', 'btn-success'];
    }
    if (in_array($st, [AssetStatus::Available, AssetStatus::InRepair], true)) {
        $statusActions[] = ['lost', 'Laporkan Hilang', 'report', 'btn-danger'];
    }
    if ($st === AssetStatus::Lost) {
        $statusActions[] = ['found', 'Ditemukan Kembali', 'search_check', 'btn-success'];
    }
@endphp

@section('title', $asset->name)
@section('subtitle', $asset->asset_tag.' · '.($asset->category?->name ?? '-'))

@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <a href="{{ route('assets.index') }}" class="hover:text-on-surface">Register Aset</a>
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span class="text-on-surface font-semibold">{{ $asset->asset_tag }}</span>
@endsection

@section('actions')
    @permission('asset.update')
        @unless ($asset->isDisposed())
            <a href="{{ route('assets.edit', $asset) }}" class="btn btn-secondary">
                <span class="material-symbols-outlined !text-[18px]">edit</span> Ubah Data
            </a>
        @endunless
    @endpermission
@endsection

@section('content')
    {{-- Ringkasan --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-space-md mb-space-lg">
        <div class="card card-body">
            <p class="text-label-sm text-on-surface-variant uppercase">Status</p>
            <div class="mt-space-xs"><x-badge :enum="$asset->status" /></div>
        </div>
        <div class="card card-body">
            <p class="text-label-sm text-on-surface-variant uppercase">Kondisi</p>
            <div class="mt-space-xs"><x-badge :enum="$asset->condition" /></div>
        </div>
        <div class="card card-body">
            <p class="text-label-sm text-on-surface-variant uppercase">Pemegang</p>
            <p class="mt-space-xs text-body-md font-semibold text-on-surface">
                @if ($holder && $holder->exists)
                    <a href="{{ route('employees.show', $holder) }}" class="hover:underline">{{ $holder->name }}</a>
                @else
                    -
                @endif
            </p>
        </div>
        <div class="card card-body">
            <p class="text-label-sm text-on-surface-variant uppercase">Lokasi</p>
            <p class="mt-space-xs text-body-md font-semibold text-on-surface">{{ $asset->location?->name ?? '-' }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-space-lg">
        <div class="lg:col-span-2 space-y-space-lg">
            <x-card title="Informasi Aset" icon="info">
                <dl class="dl-grid">
                    <dt>Asset Tag</dt><dd><span class="tag">{{ $asset->asset_tag }}</span></dd>
                    <dt>Nama</dt><dd>{{ $asset->name }}</dd>
                    <dt>Kategori</dt><dd>{{ $asset->category?->name ?? '-' }}</dd>
                    <dt>Merek / Model</dt><dd>{{ collect([$asset->brand, $asset->model])->filter()->implode(' / ') ?: '-' }}</dd>
                    <dt>Serial Number</dt><dd class="font-mono">{{ $asset->serial_number ?: '-' }}</dd>
                    <dt>Departemen</dt><dd>{{ $asset->department?->name ?? '-' }}</dd>
                    <dt>Spesifikasi</dt><dd class="whitespace-pre-line">{{ $asset->specification ?: '-' }}</dd>
                    <dt>Catatan</dt><dd class="whitespace-pre-line">{{ $asset->notes ?: '-' }}</dd>
                </dl>
            </x-card>

            <x-card title="Pembelian & Garansi" icon="receipt_long">
                <dl class="dl-grid">
                    <dt>Vendor</dt><dd>{{ $asset->vendor?->name ?? '-' }}</dd>
                    <dt>Tanggal Beli</dt><dd>{{ $asset->purchase_date?->format('d M Y') ?? '-' }}</dd>
                    <dt>Nilai Beli</dt><dd>{{ $money($asset->purchase_cost) }}</dd>
                    <dt>Garansi s.d.</dt>
                    <dd>
                        {{ $asset->warranty_end_date?->format('d M Y') ?? '-' }}
                        @if ($asset->warranty_end_date)
                            @if ($asset->warranty_end_date->isPast())
                                <x-badge color="neutral">Kedaluwarsa</x-badge>
                            @elseif ($asset->isWarrantyExpiringSoon())
                                <x-badge color="repair">Segera berakhir</x-badge>
                            @endif
                        @endif
                    </dd>
                    <dt>Asal Procurement</dt>
                    <dd>
                        @if ($asset->procurementItem?->procurementRequest)
                            <a href="{{ route('procurements.show', $asset->procurementItem->procurementRequest) }}" class="text-secondary hover:underline">{{ $asset->procurementItem->procurementRequest->request_no }}</a>
                        @else
                            -
                        @endif
                    </dd>
                </dl>
            </x-card>

            <x-card title="Riwayat Assignment" icon="assignment_ind" :padding="false">
                @if ($asset->assignments->isEmpty())
                    <x-empty icon="assignment_ind" message="Belum pernah ditugaskan." />
                @else
                    <table class="table">
                        <thead><tr><th>Karyawan</th><th>Diserahkan</th><th>Dikembalikan</th><th>Kondisi Keluar</th><th>Kondisi Masuk</th><th>No. BAST</th></tr></thead>
                        <tbody>
                            @foreach ($asset->assignments as $as)
                                <tr>
                                    <td class="font-semibold">{{ $as->employee?->name ?? '-' }}</td>
                                    <td>{{ $as->assigned_at?->format('d M Y') ?? '-' }}</td>
                                    <td>
                                        @if ($as->returned_at)
                                            {{ $as->returned_at->format('d M Y') }}
                                        @else
                                            <x-badge color="assigned">Aktif</x-badge>
                                        @endif
                                    </td>
                                    <td>@if ($as->condition_out)<x-badge :enum="$as->condition_out" />@else - @endif</td>
                                    <td>@if ($as->condition_in)<x-badge :enum="$as->condition_in" />@else - @endif</td>
                                    <td class="font-mono text-label-md">{{ $as->handover_document_no ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>

            <x-card title="Riwayat Peminjaman" icon="schedule" :padding="false">
                @if ($asset->loans->isEmpty())
                    <x-empty icon="schedule" message="Belum pernah dipinjam." />
                @else
                    <table class="table">
                        <thead><tr><th>Peminjam</th><th>Keluar</th><th>Jatuh Tempo</th><th>Kembali</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach ($asset->loans as $loan)
                                <tr>
                                    <td class="font-semibold">{{ $loan->borrower?->name ?? '-' }}</td>
                                    <td>{{ $loan->checked_out_at?->format('d M Y') ?? '-' }}</td>
                                    <td>
                                        {{ $loan->due_at?->format('d M Y') ?? '-' }}
                                        @if ($loan->isOverdue())<x-badge color="disposal">Terlambat</x-badge>@endif
                                    </td>
                                    <td>{{ $loan->returned_at?->format('d M Y') ?? '-' }}</td>
                                    <td>
                                        @if ($loan->status)<x-badge :enum="$loan->status" />@endif
                                        @if ($loan->is_late)<x-badge color="disposal">Telat</x-badge>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>

            <x-card title="Histori Aset" icon="history">
                @if ($asset->events->isEmpty())
                    <x-empty icon="history" message="Belum ada histori." />
                @else
                    <ol class="relative border-l border-border-subtle ml-space-sm space-y-space-lg">
                        @foreach ($asset->events as $ev)
                            <li class="ml-space-lg">
                                <span class="absolute -left-[7px] mt-1.5 h-3 w-3 rounded-full border-2 border-surface-card bg-status-{{ $ev->event_type->color() }}"></span>
                                <div class="flex flex-wrap items-center gap-space-sm">
                                    <span class="text-body-sm font-semibold text-on-surface">{{ $ev->event_type->label() }}</span>
                                    @if ($ev->from_status && $ev->to_status && $ev->from_status !== $ev->to_status)
                                        <span class="flex items-center gap-1">
                                            <x-badge :enum="$ev->from_status" />
                                            <span class="material-symbols-outlined !text-[14px] text-outline">arrow_forward</span>
                                            <x-badge :enum="$ev->to_status" />
                                        </span>
                                    @endif
                                </div>
                                <p class="text-label-sm text-on-surface-variant mt-0.5">
                                    {{ $ev->occurred_at?->format('d M Y H:i') }}
                                    @if ($ev->performedBy) &middot; oleh {{ $ev->performedBy->name }} @endif
                                    @if ($ev->relatedEmployee) &middot; karyawan: {{ $ev->relatedEmployee->name }} @endif
                                    @if ($ev->fromLocation || $ev->toLocation)
                                        &middot; {{ $ev->fromLocation?->name ?? '-' }} &rarr; {{ $ev->toLocation?->name ?? '-' }}
                                    @endif
                                </p>
                                @if ($ev->notes)
                                    <p class="text-body-sm text-on-surface mt-space-xs">{{ $ev->notes }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-card>
        </div>

        <aside class="lg:col-span-1 space-y-space-lg">
            @permission('asset.status')
                @if (count($statusActions))
                    <x-card title="Aksi Status" icon="sync_alt">
                        <div class="space-y-space-md" x-data="{ open: null }">
                            @foreach ($statusActions as [$action, $label, $icon, $btn])
                                <div>
                                    <button type="button" class="btn {{ $btn }} w-full" @click="open = open === '{{ $action }}' ? null : '{{ $action }}'">
                                        <span class="material-symbols-outlined !text-[18px]">{{ $icon }}</span> {{ $label }}
                                    </button>
                                    <form method="POST" action="{{ route('assets.status', $asset) }}" x-show="open === '{{ $action }}'" x-cloak
                                          class="mt-space-sm space-y-space-sm p-space-md rounded border border-border-subtle bg-surface-container-low"
                                          onsubmit="return confirm('Yakin melakukan aksi: {{ $label }}?')">
                                        @csrf
                                        <input type="hidden" name="action" value="{{ $action }}">
                                        @if (in_array($action, ['repaired', 'found'], true))
                                            <div>
                                                <label class="form-label">Kondisi</label>
                                                <select name="condition" class="form-input">
                                                    <option value="">-- Tidak berubah --</option>
                                                    @foreach (\App\Enums\AssetCondition::cases() as $cond)
                                                        <option value="{{ $cond->value }}">{{ $cond->label() }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endif
                                        <div>
                                            <label class="form-label">Catatan</label>
                                            <textarea name="notes" rows="2" maxlength="1000" class="form-input"></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-sm w-full">Konfirmasi</button>
                                    </form>
                                </div>
                            @endforeach
                            <p class="form-hint">Aset yang ditugaskan/dipinjam harus dikembalikan melalui proses pengembalian.</p>
                        </div>
                    </x-card>
                @endif
            @endpermission

            @if ($asset->activeAssignment || $asset->activeLoan)
                <x-card title="Penggunaan Aktif" icon="person_pin">
                    @if ($asset->activeAssignment)
                        <dl class="dl-grid">
                            <dt>Jenis</dt><dd><x-badge color="assigned">Assignment</x-badge></dd>
                            <dt>Karyawan</dt><dd>{{ $asset->activeAssignment->employee?->name ?? '-' }}</dd>
                            <dt>Sejak</dt><dd>{{ $asset->activeAssignment->assigned_at?->format('d M Y') ?? '-' }}</dd>
                        </dl>
                    @endif
                    @if ($asset->activeLoan)
                        <dl class="dl-grid">
                            <dt>Jenis</dt><dd><x-badge color="loan">Peminjaman</x-badge></dd>
                            <dt>Peminjam</dt><dd>{{ $asset->activeLoan->borrower?->name ?? '-' }}</dd>
                            <dt>Jatuh Tempo</dt>
                            <dd>
                                {{ $asset->activeLoan->due_at?->format('d M Y') ?? '-' }}
                                @if ($asset->activeLoan->isOverdue())<x-badge color="disposal">Terlambat</x-badge>@endif
                            </dd>
                            @if ($asset->activeLoan->loanRequest)
                                <dt>Pengajuan</dt>
                                <dd><a href="{{ route('loans.show', $asset->activeLoan->loanRequest) }}" class="text-secondary hover:underline">Lihat pengajuan</a></dd>
                            @endif
                        </dl>
                    @endif
                </x-card>
            @endif

            @if ($asset->disposalRequests->isNotEmpty())
                <x-card title="Pengajuan Disposal" icon="delete_sweep" :padding="false">
                    <table class="table">
                        <tbody>
                            @foreach ($asset->disposalRequests as $dr)
                                <tr>
                                    <td><a href="{{ route('disposals.show', $dr) }}" class="font-mono text-secondary hover:underline">{{ $dr->request_no }}</a></td>
                                    <td class="text-right">@if ($dr->status)<x-badge :enum="$dr->status" />@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
            @endif

            <x-card title="Lampiran" icon="attach_file" :padding="false">
                @if ($asset->attachments->isEmpty())
                    <x-empty icon="attach_file" message="Tidak ada lampiran." />
                @else
                    <ul class="divide-y divide-border-subtle">
                        @foreach ($asset->attachments as $att)
                            <li class="px-space-lg py-space-sm flex items-center gap-space-sm">
                                <span class="material-symbols-outlined !text-[18px] text-outline">description</span>
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('attachments.download', $att) }}" class="block truncate text-body-sm text-secondary hover:underline">{{ $att->original_name }}</a>
                                    <p class="text-label-sm text-on-surface-variant">
                                        {{ \App\Models\Attachment::CATEGORIES[$att->category] ?? $att->category }} &middot; {{ $att->humanSize() }}
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </aside>
    </div>
@endsection
