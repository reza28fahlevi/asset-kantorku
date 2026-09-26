<x-card title="Pinjaman Terlambat" icon="schedule" :padding="false">
    <x-slot:actions><a href="{{ route('loans.active', ['filter' => 'overdue']) }}" class="btn btn-ghost btn-sm">Lihat Semua</a></x-slot:actions>
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
                        <td class="text-right"><a href="{{ route('loans.show', $loan->asset_loan_request_id) }}" class="btn btn-ghost btn-sm"><span class="material-symbols-outlined !text-[18px]">chevron_right</span></a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-card>
