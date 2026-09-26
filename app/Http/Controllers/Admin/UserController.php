<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q'));

        $users = User::query()
            ->with('employee.department', 'roles')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'ilike', "%{$term}%")
                ->orWhere('email', 'ilike', "%{$term}%")))
            ->when($request->query('role'), fn ($q, $role) => $q->whereHas('roles', fn ($r) => $r->where('name', $role)))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', ['users' => $users, 'roles' => Role::orderBy('display_name')->get()]);
    }

    public function create(): View
    {
        return $this->form(new User(['is_active' => true]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);

        DB::transaction(function () use ($data) {
            $user = User::create($data);
            $this->syncRoles($user, $data['roles']);
        });

        return redirect()->route('admin.users.index')->with('success', 'Akun pengguna berhasil dibuat.');
    }

    public function edit(User $user): View
    {
        return $this->form($user->load('roles'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        if ($user->is($request->user()) && ! $data['is_active']) {
            return back()->withInput()->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        DB::transaction(function () use ($user, $data) {
            $user->update($data);
            $this->syncRoles($user, $data['roles']);
        });

        return redirect()->route('admin.users.index')->with('success', 'Akun pengguna berhasil diperbarui.');
    }

    private function form(User $user): View
    {
        return view('users.form', [
            'user' => $user,
            'roles' => Role::with('permissions:id,name,display_name,group_name')->orderBy('display_name')->get(),
            // Karyawan yang belum punya akun (atau karyawan milik akun ini)
            'employees' => Employee::query()
                ->where(fn ($q) => $q->whereDoesntHave('user')->when($user->employee_id, fn ($w) => $w->orWhere('id', $user->employee_id)))
                ->orderBy('name')->get(),
        ]);
    }

    private function validated(Request $request, ?User $user): array
    {
        $data = $request->validate([
            'employee_id' => ['nullable', 'exists:employees,id', Rule::unique('users', 'employee_id')->ignore($user)],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['exists:roles,id'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function syncRoles(User $user, array $roleIds): void
    {
        $changes = $user->roles()->sync($roleIds);
        if (array_filter($changes)) {
            AuditLogger::log('roles_synced', $user, ['detached' => $changes['detached']], ['attached' => $changes['attached']]);
        }
    }
}
