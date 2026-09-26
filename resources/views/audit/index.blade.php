@extends('layouts.app')

@section('title', 'Audit Log')
@section('subtitle', 'Jejak seluruh perubahan data dan aksi penting pengguna')
@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span>Administrasi</span>
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span class="text-on-surface font-semibold">Audit Log</span>
@endsection

@section('content')
<div class="card">
    <form method="GET" class="grid grid-cols-2 md:grid-cols-6 items-end gap-space-md px-space-lg py-space-md border-b border-border-subtle">
        <div>
            <label class="form-label">Pengguna</label>
            <select name="user_id" class="form-input">
                <option value="">Semua</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}" @selected((string) request('user_id') === (string) $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label">Aksi</label>
            <select name="action" class="form-input">
                <option value="">Semua</option>
                @foreach ($actions as $a)
                    <option value="{{ $a }}" @selected(request('action') === $a)>{{ $a }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label">Objek</label>
            <select name="type" class="form-input">
                <option value="">Semua</option>
                @foreach ($types as $t)
                    <option value="{{ $t }}" @selected(request('type') === $t)>{{ class_basename($t) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label">Dari</label>
            <input type="date" name="from" value="{{ request('from') }}" class="form-input">
        </div>
        <div>
            <label class="form-label">Sampai</label>
            <input type="date" name="to" value="{{ request('to') }}" class="form-input">
        </div>
        <div class="flex gap-space-sm">
            @if (request('auditable_id'))<input type="hidden" name="auditable_id" value="{{ request('auditable_id') }}">@endif
            <button class="btn btn-secondary flex-1"><span class="material-symbols-outlined !text-[18px]">filter_list</span> Filter</button>
            @if (request()->hasAny(['user_id', 'action', 'type', 'from', 'to', 'auditable_id']))
                <a href="{{ route('admin.audit.index') }}" class="btn btn-ghost">Reset</a>
            @endif
        </div>
    </form>

    @if ($logs->isEmpty())
        <x-empty icon="history" message="Tidak ada catatan audit." />
    @else
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Objek</th><th>Perubahan</th><th>IP</th></tr></thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr x-data="{ open: false }" class="align-top">
                            <td class="whitespace-nowrap text-body-sm">{{ $log->created_at?->format('d M Y') }}<div class="text-on-surface-variant">{{ $log->created_at?->format('H:i:s') }}</div></td>
                            <td>{{ $log->user?->name ?? 'Sistem' }}</td>
                            <td><span class="tag">{{ $log->action }}</span></td>
                            <td class="text-body-sm">
                                @if ($log->auditable_type)
                                    {{ class_basename($log->auditable_type) }} <span class="text-on-surface-variant">#{{ $log->auditable_id }}</span>
                                @else - @endif
                            </td>
                            <td class="text-body-sm">
                                @php $keys = array_unique(array_merge(array_keys($log->old_values ?? []), array_keys($log->new_values ?? []))); @endphp
                                @if (count($keys))
                                    <button type="button" class="text-secondary hover:underline" @click="open = !open">
                                        <span x-text="open ? 'Sembunyikan' : '{{ count($keys) }} field'"></span>
                                    </button>
                                    <div x-show="open" x-cloak class="mt-space-sm">
                                        <table class="w-full text-label-sm border border-border-subtle">
                                            <tr class="bg-surface"><th class="text-left px-2 py-1">Field</th><th class="text-left px-2 py-1">Sebelum</th><th class="text-left px-2 py-1">Sesudah</th></tr>
                                            @foreach ($keys as $k)
                                                @php
                                                    $fmt = fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (is_bool($v) ? ($v ? 'true' : 'false') : (string) ($v ?? '—'));
                                                @endphp
                                                <tr class="border-t border-border-subtle">
                                                    <td class="px-2 py-1 font-mono">{{ $k }}</td>
                                                    <td class="px-2 py-1 text-error break-all">{{ $fmt(($log->old_values ?? [])[$k] ?? null) }}</td>
                                                    <td class="px-2 py-1 text-status-available break-all">{{ $fmt(($log->new_values ?? [])[$k] ?? null) }}</td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    </div>
                                @else
                                    <span class="text-on-surface-variant">-</span>
                                @endif
                            </td>
                            <td class="text-label-sm text-on-surface-variant font-mono">{{ $log->ip_address ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-space-lg py-space-md border-t border-border-subtle">{{ $logs->links() }}</div>
    @endif
</div>
@endsection
