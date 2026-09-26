@extends('layouts.app')

@section('title', 'Notifikasi')
@section('subtitle', 'Pemberitahuan terkait pengajuan dan aset Anda')
@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span class="text-on-surface font-semibold">Notifikasi</span>
@endsection

@section('actions')
    @if (auth()->user()->unreadNotifications()->exists())
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button type="submit" class="btn btn-secondary"><span class="material-symbols-outlined !text-[18px]">done_all</span> Tandai Semua Dibaca</button>
        </form>
    @endif
@endsection

@section('content')
<x-card title="Semua Notifikasi" icon="notifications" :padding="false">
    @if ($notifications->isEmpty())
        <div class="p-space-lg"><x-empty icon="notifications_off" message="Belum ada notifikasi." /></div>
    @else
        <ul class="divide-y divide-border-subtle">
            @foreach ($notifications as $n)
                <li>
                    <a href="{{ route('notifications.open', $n->id) }}" @class(['flex items-start gap-space-md px-space-lg py-space-md hover:bg-surface-container-low/60 transition-colors', 'bg-surface-container-low/40' => ! $n->read_at])>
                        <span @class(['w-9 h-9 rounded-lg flex items-center justify-center shrink-0', 'bg-secondary-fixed text-secondary' => ! $n->read_at, 'bg-surface-subtle text-outline' => $n->read_at])>
                            <span class="material-symbols-outlined !text-[18px]">notifications</span>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-space-md">
                                <p @class(['text-body-sm truncate', 'font-semibold text-on-surface' => ! $n->read_at, 'text-on-surface-variant' => $n->read_at])>{{ $n->data['title'] ?? 'Notifikasi' }}</p>
                                <span class="text-label-sm font-normal text-outline whitespace-nowrap">{{ $n->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="text-body-sm text-on-surface-variant mt-0.5">{{ $n->data['message'] ?? '' }}</p>
                            <p class="text-label-sm font-normal text-outline mt-0.5">{{ $n->created_at->format('d M Y H:i') }}</p>
                        </div>
                        @unless ($n->read_at)<span class="w-2 h-2 rounded-full bg-secondary-container mt-2 shrink-0" title="Belum dibaca"></span>@endunless
                    </a>
                </li>
            @endforeach
        </ul>
        @if ($notifications->hasPages())
            <div class="px-space-lg py-space-md border-t border-border-subtle">{{ $notifications->links() }}</div>
        @endif
    @endif
</x-card>
@endsection
