@extends('layouts.app')

@section('title', 'Matriks Otorisasi')
@section('subtitle', 'Tentukan menu dan aksi yang dapat diakses setiap role. User dengan beberapa role mendapat gabungan seluruh hak aksesnya.')

@section('breadcrumb')
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span>Role Based Access</span>
    <span class="material-symbols-outlined !text-[14px]">chevron_right</span>
    <span class="text-on-surface font-semibold">Matriks Otorisasi</span>
@endsection

@section('actions')
    <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary"><span class="material-symbols-outlined !text-[18px]">shield_person</span> Kelola Role</a>
    <button type="submit" form="matrix-form" class="btn btn-primary"><span class="material-symbols-outlined !text-[18px]">save</span> Simpan Perubahan</button>
@endsection

@php
    $initial = collect(old('matrix', $granted->all()))
        ->map(fn ($ids) => array_values(array_map('intval', (array) $ids)))
        ->all();
    foreach ($roles as $r) {
        $initial[$r->id] ??= [];
    }
@endphp

@section('content')
<form id="matrix-form" method="POST" action="{{ route('admin.access.update') }}" x-data="accessMatrix(@js($initial))">
    @csrf
    @method('PUT')

    <div class="grid grid-cols-2 md:grid-cols-4 gap-gutter mb-space-lg">
        <x-stat label="Role" :value="$roles->count()" icon="shield_person" color="assigned"/>
        <x-stat label="Permission" :value="$permissionGroups->flatten()->count()" icon="key" color="loan"/>
        <x-stat label="Modul / Menu" :value="$permissionGroups->count()" icon="menu" color="neutral"/>
        <x-stat label="Pengguna Ber-role" :value="$roles->sum('users_count')" icon="group" color="available" hint="Satu user dapat dihitung di beberapa role"/>
    </div>

    <div class="card">
        <div class="card-header flex-wrap">
            <div class="relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline !text-[18px]">search</span>
                <input type="search" x-model="q" class="form-input pl-9 w-72" placeholder="Cari permission atau modul...">
            </div>
            <p class="text-label-sm font-normal text-outline flex items-center gap-1">
                <span class="material-symbols-outlined !text-[16px]">info</span>
                Klik judul kolom role / nama modul untuk memilih semua.
            </p>
        </div>
        <div class="overflow-auto max-h-[70vh]">
            <table class="table">
                <thead class="sticky top-0 z-10">
                    <tr>
                        <th class="min-w-[280px] sticky left-0 z-20 bg-surface-subtle">Menu / Permission</th>
                        @foreach ($roles as $role)
                            <th class="text-center min-w-[120px]">
                                <button type="button" class="hover:text-on-surface" @click="toggleRole({{ $role->id }})" title="Pilih/hapus semua untuk role ini">
                                    <span class="block normal-case text-label-md text-on-surface">{{ $role->display_name }}</span>
                                    <span class="block font-normal normal-case"><span x-text="(matrix[{{ $role->id }}] || []).length"></span> hak · {{ $role->users_count }} user</span>
                                </button>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($permissionGroups as $group => $permissions)
                        @php $groupIds = $permissions->pluck('id')->all(); @endphp
                        <tr class="bg-surface-container-low/70" x-show="groupVisible(@js(strtolower($group.' '.$permissions->pluck('name')->implode(' ').' '.$permissions->pluck('display_name')->implode(' '))))">
                            <td class="sticky left-0 bg-surface-container-low">
                                <button type="button" class="flex items-center gap-space-xs text-label-md uppercase text-on-surface hover:text-secondary" @click="toggleGroupAll(@js($groupIds))">
                                    <span class="material-symbols-outlined !text-[18px] text-secondary">folder_open</span>{{ $group ?: 'Umum' }}
                                </button>
                            </td>
                            @foreach ($roles as $role)
                                <td class="text-center">
                                    <button type="button" class="text-label-sm text-outline hover:text-secondary" @click="toggleGroup({{ $role->id }}, @js($groupIds))"
                                            x-text="countIn({{ $role->id }}, @js($groupIds)) + '/{{ count($groupIds) }}'"></button>
                                </td>
                            @endforeach
                        </tr>
                        @foreach ($permissions as $perm)
                            <tr x-show="rowVisible(@js(strtolower($group.' '.$perm->name.' '.$perm->display_name)))">
                                <td class="sticky left-0 bg-surface-card">
                                    <p class="text-body-sm font-medium text-on-surface">{{ $perm->display_name ?: $perm->name }}</p>
                                    <p class="font-mono text-code-sm text-outline">{{ $perm->name }}</p>
                                    @if ($perm->description)<p class="text-label-sm font-normal text-on-surface-variant">{{ $perm->description }}</p>@endif
                                </td>
                                @foreach ($roles as $role)
                                    <td class="text-center">
                                        <input type="checkbox" name="matrix[{{ $role->id }}][]" value="{{ $perm->id }}"
                                               x-model.number="matrix[{{ $role->id }}]"
                                               class="w-4 h-4 rounded border-border-strong text-primary-container focus:ring-secondary cursor-pointer">
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-space-lg py-space-md border-t border-border-subtle flex flex-wrap items-center justify-between gap-space-md">
            <p class="text-body-sm text-on-surface-variant">Perubahan berlaku langsung untuk semua user pada role terkait dan tercatat di Audit Log.</p>
            <button type="submit" class="btn btn-primary"><span class="material-symbols-outlined !text-[18px]">save</span> Simpan Perubahan</button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    function accessMatrix(initial) {
        return {
            matrix: initial,
            q: '',
            rowVisible(text) { return !this.q || text.includes(this.q.toLowerCase()); },
            groupVisible(text) { return !this.q || text.includes(this.q.toLowerCase()); },
            countIn(roleId, ids) { return ids.filter(id => this.matrix[roleId].includes(id)).length; },
            setMany(roleId, ids, on) {
                const set = new Set(this.matrix[roleId]);
                ids.forEach(id => on ? set.add(id) : set.delete(id));
                this.matrix[roleId] = [...set];
            },
            toggleGroup(roleId, ids) { this.setMany(roleId, ids, this.countIn(roleId, ids) < ids.length); },
            toggleGroupAll(ids) {
                const roles = Object.keys(this.matrix);
                const allOn = roles.every(r => this.countIn(r, ids) === ids.length);
                roles.forEach(r => this.setMany(r, ids, !allOn));
            },
            toggleRole(roleId) {
                const ids = [...document.querySelectorAll(`input[name="matrix[${roleId}][]"]`)].map(el => Number(el.value));
                this.setMany(roleId, ids, this.matrix[roleId].length < ids.length);
            },
        };
    }
</script>
@endpush
