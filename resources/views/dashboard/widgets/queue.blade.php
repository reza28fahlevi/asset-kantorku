@php
    $queueDefs = [
        'procurement_order' => ['Procurement siap dipesan (PO)', 'shopping_cart', route('procurements.index', ['status' => 'APPROVED'])],
        'procurement_receive' => ['Procurement menunggu penerimaan', 'inventory', route('procurements.index', ['status' => 'ORDERED'])],
        'assignment_handover' => ['Assignment siap serah terima', 'assignment_ind', route('assignments.index', ['status' => 'APPROVED'])],
        'loan_checkout' => ['Peminjaman siap diserahkan', 'swap_horiz', route('loans.index', ['status' => 'APPROVED'])],
        'disposal_execute' => ['Disposal siap dieksekusi', 'delete_sweep', route('disposals.index', ['status' => 'APPROVED'])],
    ];
    $queue = collect($workQueue)->reject(fn ($v) => $v === null);
@endphp
@if ($queue->isNotEmpty())
    <x-card title="Antrian Kerja Operasional" icon="bolt">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-space-md">
            @foreach ($queue as $key => $total)
                @php [$label, $icon, $url] = $queueDefs[$key]; @endphp
                <a href="{{ $url }}" class="rounded-lg border border-border-subtle bg-surface-canvas p-space-md hover:border-border-strong transition-colors">
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
