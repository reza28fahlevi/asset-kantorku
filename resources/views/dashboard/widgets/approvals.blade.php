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
