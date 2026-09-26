<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('roles.index', [
            'roles' => Role::withCount(['users', 'permissions'])->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Role());
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $this->validated($request, null);

        $role = DB::transaction(function () use ($data) {
            $role = Role::create(Arr::except($data, 'permissions'));
            $this->syncPermissions($role, $data['permissions'] ?? []);

            return $role;
        });

        return $this->respond($request, "Role {$role->display_name} berhasil dibuat.", route('admin.roles.index'));
    }

    public function edit(Role $role): View
    {
        return $this->form($role->load('permissions'));
    }

    public function update(Request $request, Role $role): JsonResponse|RedirectResponse
    {
        $data = $this->validated($request, $role);
        if ($role->is_system) {
            unset($data['name']); // slug role bawaan dipakai di kode
        }

        DB::transaction(function () use ($role, $data) {
            $role->update(Arr::except($data, 'permissions'));
            $this->syncPermissions($role, $data['permissions'] ?? []);
        });

        return $this->reassignApprovals(
            $this->respond($request, "Role {$role->display_name} berhasil diperbarui.", route('admin.roles.index'))
        );
    }

    public function destroy(Request $request, Role $role): JsonResponse|RedirectResponse
    {
        if ($role->is_system) {
            return $this->respond($request, 'Role bawaan sistem tidak dapat dihapus.', status: 'error');
        }
        if ($role->users()->exists()) {
            return $this->respond($request, 'Role masih dipakai oleh pengguna.', status: 'error');
        }

        $role->delete();

        return $this->respond($request, "Role {$role->display_name} berhasil dihapus.", route('admin.roles.index'));
    }

    private function form(Role $role): View
    {
        return view('roles.form', [
            'role' => $role,
            'permissionGroups' => Permission::orderBy('group_name')->orderBy('id')->get()->groupBy('group_name'),
        ]);
    }

    private function validated(Request $request, ?Role $role): array
    {
        return $request->validate([
            'name' => [$role?->is_system ? 'nullable' : 'required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', Rule::unique('roles')->ignore($role)],
            'display_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);
    }

    private function syncPermissions(Role $role, array $permissionIds): void
    {
        $changes = $role->permissions()->sync($permissionIds);
        if (array_filter($changes)) {
            AuditLogger::log('permissions_synced', $role, ['detached' => $changes['detached']], ['attached' => $changes['attached']]);
        }
    }
}
