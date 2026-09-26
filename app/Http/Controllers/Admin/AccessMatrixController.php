<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Matriks otorisasi: atur permission (menu & aksi) semua role dalam satu layar. */
class AccessMatrixController extends Controller
{
    public function index(): View
    {
        $roles = Role::with('permissions:id')->withCount('users')->orderBy('id')->get();

        return view('roles.matrix', [
            'roles' => $roles,
            'permissionGroups' => Permission::orderBy('group_name')->orderBy('id')->get()->groupBy('group_name'),
            'granted' => $roles->mapWithKeys(fn (Role $r) => [$r->id => $r->permissions->pluck('id')->all()]),
        ]);
    }

    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'matrix' => ['array'],
            'matrix.*' => ['array'],
            'matrix.*.*' => ['integer', 'exists:permissions,id'],
        ]);
        $matrix = $data['matrix'] ?? [];
        $roles = Role::orderBy('id')->get();

        // Cegah admin mengunci dirinya sendiri dari menu pengaturan hak akses
        $manageId = Permission::where('name', 'role.manage')->value('id');
        $myRoleIds = $request->user()->roles()->pluck('roles.id');
        $keepsAccess = $myRoleIds->contains(fn ($id) => in_array($manageId, array_map('intval', $matrix[$id] ?? []), true));
        if ($manageId && ! $keepsAccess) {
            return $this->respond($request, 'Minimal satu role Anda harus tetap memiliki hak "role.manage" agar tidak kehilangan akses ke menu ini.', status: 'error');
        }

        DB::transaction(function () use ($roles, $matrix) {
            foreach ($roles as $role) {
                $changes = $role->permissions()->sync(array_map('intval', $matrix[$role->id] ?? []));
                if (array_filter($changes)) {
                    AuditLogger::log('permissions_synced', $role, ['detached' => $changes['detached']], ['attached' => $changes['attached']]);
                }
            }
        });

        return $this->reassignApprovals(
            $this->respond($request, 'Matriks otorisasi berhasil disimpan.', route('admin.access.index'))
        );
    }
}
