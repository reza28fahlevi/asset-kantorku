{{-- Riwayat persetujuan dari App\Models\ApprovalRequest (relasi steps.approver). --}}
@props(['approval' => null])
@php
    $dot = ['APPROVED' => 'bg-status-available', 'REJECTED' => 'bg-status-disposal', 'PENDING' => 'bg-status-pending'];
@endphp
<x-card title="Alur Persetujuan" icon="fact_check">
    @if (! $approval)
        <p class="text-body-sm text-outline">Belum diajukan untuk persetujuan.</p>
    @else
        <div class="flex items-center justify-between mb-space-md">
            <span class="text-label-sm uppercase text-outline">Status</span>
            <x-badge :enum="$approval->status"/>
        </div>
        <ol class="relative border-l border-border-subtle ml-2 space-y-space-lg">
            @foreach ($approval->steps->sortBy('step_order') as $step)
                <li class="ml-space-lg">
                    <span class="absolute -left-[7px] mt-1 w-3.5 h-3.5 rounded-full ring-4 ring-surface-card {{ $dot[$step->status->value] ?? 'bg-outline-variant' }}"></span>
                    <div class="flex flex-wrap items-center gap-space-sm">
                        <p class="text-body-sm font-semibold">Tahap {{ $step->step_order }} · {{ $step->approver?->name ?? '-' }}</p>
                        <x-badge :enum="$step->status"/>
                    </div>
                    @if ($step->decided_at)
                        <p class="text-label-sm font-normal text-outline">{{ $step->decided_at->format('d M Y H:i') }}</p>
                    @endif
                    @if ($step->comment)
                        <p class="mt-1 text-body-sm text-on-surface-variant bg-surface-subtle rounded px-space-sm py-space-xs">{{ $step->comment }}</p>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
</x-card>
