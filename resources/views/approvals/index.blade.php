@extends('layouts.app')

@section('title', 'Inbox Persetujuan')
@section('subtitle', 'Kelola dan beri keputusan atas permohonan procurement, penugasan, peminjaman, dan penghapusan aset dari tim Anda.')

@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span class="text-on-surface font-semibold">Approval Inbox</span>
@endsection

@php
    $types = \App\Enums\ApprovalType::cases();
    $currentType = request('type');
    $first = $steps->first();
    $histParam = $tab === 'history' ? 'history' : null;
@endphp

@section('content')
<div class="space-y-space-lg">
    {{-- KPI --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-gutter">
        <x-stat label="Menunggu Persetujuan" :value="$pendingCount" icon="hourglass_top" color="pending"
                :href="route('approvals.index')" hint="Antrean keputusan Anda"/>
        <x-stat label="Ditampilkan" :value="$steps->total()" icon="inbox" color="assigned"
                :hint="$tab === 'pending' ? 'Item di antrean (sesuai filter)' : 'Item di riwayat (sesuai filter)'"/>
        <x-stat label="Riwayat Keputusan" value="Lihat" icon="history" color="neutral"
                :href="route('approvals.index', ['tab' => 'history'])" hint="Keputusan yang telah Anda ambil"/>
    </div>

    {{-- Filter bar --}}
    <div class="card px-space-lg py-space-md flex flex-wrap items-center gap-space-sm">
        <div class="flex rounded-lg border border-border-subtle overflow-hidden mr-space-md">
            <a href="{{ route('approvals.index', array_filter(['type' => $currentType])) }}"
               class="px-space-md py-1.5 text-label-md {{ $tab === 'pending' ? 'bg-primary-container text-white' : 'text-on-surface-variant hover:bg-surface-subtle' }}">
                Antrean @if ($pendingCount)<span class="ml-1 px-1.5 rounded-full bg-status-pending text-white text-[11px]">{{ $pendingCount }}</span>@endif
            </a>
            <a href="{{ route('approvals.index', array_filter(['tab' => 'history', 'type' => $currentType])) }}"
               class="px-space-md py-1.5 text-label-md {{ $tab === 'history' ? 'bg-primary-container text-white' : 'text-on-surface-variant hover:bg-surface-subtle' }}">Riwayat</a>
        </div>
        <a href="{{ route('approvals.index', array_filter(['tab' => $histParam])) }}"
           class="px-space-sm py-1 rounded text-label-md {{ ! $currentType ? 'bg-surface-subtle text-on-surface font-semibold' : 'text-on-surface-variant hover:text-on-surface' }}">Semua Permohonan</a>
        @foreach ($types as $type)
            <a href="{{ route('approvals.index', array_filter(['tab' => $histParam, 'type' => $type->value])) }}"
               class="px-space-sm py-1 rounded text-label-md {{ $currentType === $type->value ? 'bg-surface-subtle text-on-surface font-semibold' : 'text-on-surface-variant hover:text-on-surface' }}">
                {{ $type->label() }}
            </a>
        @endforeach
    </div>

    @if ($steps->isEmpty())
        <div class="card">
            <x-empty icon="task_alt" :message="$tab === 'pending' ? 'Tidak ada permintaan yang menunggu keputusan Anda.' : 'Belum ada riwayat keputusan.'"/>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-gutter items-start" x-data="{ active: {{ $first->id }} }">
            {{-- Daftar antrean --}}
            <div class="lg:col-span-2 space-y-space-sm">
                <div class="flex items-center justify-between px-1">
                    <p class="text-label-sm uppercase text-outline">Daftar {{ $tab === 'pending' ? 'Antrean Tiket' : 'Riwayat' }}</p>
                    <p class="text-label-sm font-normal text-outline">Menampilkan {{ $steps->count() }} dari {{ $steps->total() }}</p>
                </div>
                @foreach ($steps as $step)
                    @php
                        $approval = $step->approvalRequest;
                        $subject = $approval->subject();
                    @endphp
                    <button type="button" @click="active = {{ $step->id }}"
                            class="card w-full text-left p-space-md transition-colors border-l-4"
                            :class="active === {{ $step->id }} ? 'border-l-secondary-container shadow-md' : 'border-l-transparent hover:border-border-strong'">
                        <div class="flex items-center justify-between gap-space-sm">
                            <div class="flex items-center gap-space-sm min-w-0">
                                <span class="tag truncate">{{ $subject?->request_no ?? '#'.$approval->id }}</span>
                                <x-badge :enum="$approval->request_type"/>
                            </div>
                            <span class="text-label-sm font-normal text-outline shrink-0 flex items-center gap-1">
                                <span class="material-symbols-outlined !text-[14px]">schedule</span>
                                {{ ($step->decided_at ?? $approval->submitted_at ?? $step->created_at)?->diffForHumans() }}
                            </span>
                        </div>
                        <p class="text-body-md font-semibold text-on-surface mt-space-xs truncate">
                            {{ $subject ? $subject->approvalTitle() : $approval->request_type->label() }}
                        </p>
                        <div class="mt-space-sm flex items-center justify-between gap-space-sm bg-surface-subtle rounded px-space-sm py-space-xs">
                            <div class="min-w-0">
                                <p class="text-body-sm font-semibold truncate">{{ $approval->requester?->name ?? '-' }}</p>
                                <p class="text-label-sm font-normal text-outline truncate">{{ $approval->requester?->employee_no }} · {{ $approval->requester?->department?->name }}</p>
                            </div>
                            @if ($tab === 'history')
                                <x-badge :enum="$step->status"/>
                            @else
                                <span class="text-label-sm text-outline shrink-0">Tahap {{ $step->step_order }}</span>
                            @endif
                        </div>
                    </button>
                @endforeach
                <div class="pt-space-sm">{{ $steps->links() }}</div>
            </div>

            {{-- Detail --}}
            <div class="lg:col-span-3">
                @foreach ($steps as $step)
                    @php
                        $approval = $step->approvalRequest;
                        $subject = $approval->subject();
                        $summary = $subject ? $subject->approvalSummary() : [];
                    @endphp
                    <div x-show="active === {{ $step->id }}" @if (! $loop->first) x-cloak style="display:none" @endif class="card">
                        <div class="card-body space-y-space-lg">
                            <div>
                                <div class="flex flex-wrap items-center gap-space-sm">
                                    <x-badge :enum="$approval->request_type"/>
                                    <span class="tag">{{ $subject?->request_no ?? '#'.$approval->id }}</span>
                                    <x-badge :enum="$step->status"/>
                                </div>
                                <h2 class="text-headline-sm text-on-surface mt-space-sm">
                                    {{ $subject ? $subject->approvalTitle() : $approval->request_type->label() }}
                                </h2>
                                <p class="text-body-sm text-on-surface-variant mt-1">
                                    Diajukan {{ $approval->submitted_at?->format('d M Y H:i') ?? '-' }}
                                    @if ($approval->requester?->department) · Departemen: <span class="font-semibold">{{ $approval->requester->department->name }}</span>@endif
                                </p>
                            </div>

                            {{-- Pemohon --}}
                            <div class="rounded-lg border border-border-subtle bg-surface-subtle p-space-md flex flex-wrap justify-between gap-space-md">
                                <div class="flex items-center gap-space-md">
                                    <span class="w-11 h-11 rounded-full bg-primary-container text-white flex items-center justify-center font-semibold">
                                        {{ strtoupper(mb_substr($approval->requester?->name ?? '?', 0, 1)) }}
                                    </span>
                                    <div>
                                        <p class="text-body-md font-semibold">{{ $approval->requester?->name ?? '-' }}</p>
                                        <p class="text-body-sm text-on-surface-variant">{{ $approval->requester?->job_title }} · {{ $approval->requester?->department?->name }}</p>
                                        <p class="text-label-sm font-normal text-outline font-mono">{{ $approval->requester?->employee_no }} · {{ $approval->requester?->email }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-label-sm uppercase text-outline">Tahap Anda</p>
                                    <p class="text-body-md font-semibold">Tahap {{ $step->step_order }}</p>
                                    <p class="text-label-sm font-normal text-outline">{{ $step->approver_source === \App\Models\ApprovalStep::SOURCE_ESCALATION ? 'Eskalasi' : 'Atasan langsung' }}</p>
                                </div>
                            </div>

                            {{-- Ringkasan dampak --}}
                            @if ($summary)
                                <div>
                                    <h3 class="text-label-md uppercase text-on-surface flex items-center gap-space-xs mb-space-sm">
                                        <span class="material-symbols-outlined !text-[18px]">description</span> Ringkasan & Justifikasi
                                    </h3>
                                    <dl class="dl-grid">
                                        @foreach ($summary as $label => $value)
                                            <dt>{{ $label }}</dt>
                                            <dd class="whitespace-pre-line">{{ $value }}</dd>
                                        @endforeach
                                    </dl>
                                </div>
                            @endif

                            {{-- Rincian item procurement --}}
                            @if ($subject instanceof \App\Models\ProcurementRequest)
                                <div>
                                    <div class="flex items-center justify-between mb-space-sm">
                                        <h3 class="text-label-md uppercase text-on-surface flex items-center gap-space-xs">
                                            <span class="material-symbols-outlined !text-[18px]">list_alt</span> Rincian Item yang Diminta
                                        </h3>
                                        <span class="text-label-sm font-normal text-outline">{{ $subject->items->count() }} jenis · {{ $subject->items->sum('quantity') }} unit</span>
                                    </div>
                                    <div class="overflow-x-auto border border-border-subtle rounded-lg">
                                        <table class="table">
                                            <thead><tr><th>Item & Spesifikasi</th><th class="text-right">Qty</th><th class="text-right">Harga Satuan</th><th class="text-right">Subtotal</th></tr></thead>
                                            <tbody>
                                                @foreach ($subject->items as $item)
                                                    <tr>
                                                        <td>
                                                            <p class="font-semibold">{{ $item->item_name }}</p>
                                                            @if ($item->specification)<p class="text-body-sm text-on-surface-variant">{{ $item->specification }}</p>@endif
                                                        </td>
                                                        <td class="text-right">{{ $item->quantity }}</td>
                                                        <td class="text-right font-mono whitespace-nowrap">Rp {{ number_format((float) $item->estimated_unit_price, 0, ',', '.') }}</td>
                                                        <td class="text-right font-mono whitespace-nowrap">Rp {{ number_format($item->subtotal(), 0, ',', '.') }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="mt-space-sm flex justify-end items-baseline gap-space-md bg-surface-subtle rounded-lg px-space-md py-space-sm">
                                        <span class="text-label-sm uppercase text-outline">Total Komitmen Anggaran</span>
                                        <span class="text-title-md font-mono text-on-surface">Rp {{ number_format((float) $subject->estimated_total, 0, ',', '.') }}</span>
                                    </div>
                                </div>
                            @endif

                            {{-- Lampiran --}}
                            @if ($subject && $subject->relationLoaded('attachments') && $subject->attachments->isNotEmpty())
                                <div>
                                    <h3 class="text-label-md uppercase text-on-surface flex items-center gap-space-xs mb-space-sm">
                                        <span class="material-symbols-outlined !text-[18px]">attach_file</span> Dokumen Pendukung
                                    </h3>
                                    <div class="grid sm:grid-cols-2 gap-space-sm">
                                        @foreach ($subject->attachments as $file)
                                            <a href="{{ route('attachments.download', $file) }}" class="flex items-center gap-space-sm border border-border-subtle rounded-lg p-space-sm hover:bg-surface-subtle">
                                                <span class="material-symbols-outlined text-status-disposal">description</span>
                                                <span class="min-w-0 flex-1">
                                                    <span class="block text-body-sm font-semibold truncate">{{ $file->original_name }}</span>
                                                    <span class="block text-label-sm font-normal text-outline">{{ $file->humanSize() }} · {{ \App\Models\Attachment::CATEGORIES[$file->category] ?? $file->category }}</span>
                                                </span>
                                                <span class="material-symbols-outlined !text-[18px] text-outline">download</span>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if ($subject)
                                <a href="{{ $subject->approvalUrl() }}" class="btn btn-ghost btn-sm">
                                    <span class="material-symbols-outlined !text-[18px]">open_in_new</span> Buka detail permintaan
                                </a>
                            @endif

                            {{-- Keputusan --}}
                            @if ($step->isPending())
                                @php $isOldStep = (string) old('step_id') === (string) $step->id; @endphp
                                <div class="rounded-lg border border-border-subtle bg-surface-subtle p-space-md" x-data="{ comment: @js($isOldStep ? old('comment', '') : '') }">
                                    <div class="flex items-start justify-between gap-space-md mb-space-sm">
                                        <h3 class="text-title-md flex items-center gap-space-xs"><span class="material-symbols-outlined">gavel</span> Keputusan Anda</h3>
                                        <p class="text-label-sm font-normal text-outline text-right">Keputusan bersifat final & tercatat di audit trail</p>
                                    </div>
                                    <x-field label="Catatan / Komentar" :name="$isOldStep ? 'comment' : null" hint="Wajib diisi (min. 5 karakter) bila menolak permintaan.">
                                        <textarea x-model="comment" rows="3" maxlength="2000" class="form-input" placeholder="Tuliskan catatan persetujuan atau alasan penolakan..."></textarea>
                                    </x-field>
                                    <div class="flex flex-wrap justify-end gap-space-sm mt-space-md">
                                        <form method="POST" data-ajax-form data-confirm="Tolak permintaan ini?" data-confirm-button="Ya, tolak" action="{{ route('approvals.reject', $step) }}"
                                              @submit="if (comment.trim().length < 5) { $event.preventDefault(); AppAlert.error('Alasan penolakan wajib diisi minimal 5 karakter.', 'Alasan wajib diisi'); }">
                                            @csrf
                                            <input type="hidden" name="step_id" value="{{ $step->id }}">
                                            <input type="hidden" name="comment" :value="comment">
                                            <button type="submit" class="btn btn-danger"><span class="material-symbols-outlined !text-[18px]">close</span> Tolak Permohonan</button>
                                        </form>
                                        <form method="POST" data-ajax-form data-confirm="Setujui permintaan ini?" data-confirm-button="Ya, setujui" action="{{ route('approvals.approve', $step) }}">
                                            @csrf
                                            <input type="hidden" name="step_id" value="{{ $step->id }}">
                                            <input type="hidden" name="comment" :value="comment">
                                            <button type="submit" class="btn btn-primary"><span class="material-symbols-outlined !text-[18px]">check_circle</span> Setujui Permohonan</button>
                                        </form>
                                    </div>
                                </div>
                            @else
                                <div class="rounded-lg border border-border-subtle p-space-md">
                                    <p class="text-label-sm uppercase text-outline">Keputusan Anda</p>
                                    <div class="flex items-center gap-space-sm mt-1">
                                        <x-badge :enum="$step->status"/>
                                        <span class="text-body-sm text-on-surface-variant">{{ $step->decided_at?->format('d M Y H:i') }}</span>
                                    </div>
                                    @if ($step->comment)<p class="mt-space-sm text-body-sm">{{ $step->comment }}</p>@endif
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
