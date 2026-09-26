<?php

namespace App\Http\Controllers\Master;

use App\Enums\EmploymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use App\Support\Like;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q'));

        $employees = Employee::query()
            ->with('department', 'manager', 'user')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'ilike', Like::contains($term))
                ->orWhere('employee_no', 'ilike', Like::contains($term))
                ->orWhere('email', 'ilike', Like::contains($term))))
            ->when($request->query('department_id'), fn ($q, $d) => $q->where('department_id', $d))
            ->when($request->query('status'), fn ($q, $s) => $q->where('employment_status', $s))
            ->orderBy('employee_no')
            ->paginate(15)
            ->withQueryString();

        return view('employees.index', [
            'employees' => $employees,
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function show(Employee $employee): View
    {
        $employee->load([
            'department', 'manager', 'workLocation', 'user.roles', 'subordinates',
            'activeAssignments.asset.category', 'activeLoans.asset.category',
        ]);

        return view('employees.show', compact('employee'));
    }

    public function create(): View
    {
        return $this->form(new Employee(['employment_status' => EmploymentStatus::Active]));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $employee = Employee::create($this->validated($request, null));

        return $this->respond($request, 'Karyawan berhasil ditambahkan.', route('masters.employees.show', $employee));
    }

    public function edit(Employee $employee): View
    {
        return $this->form($employee);
    }

    public function update(Request $request, Employee $employee): JsonResponse|RedirectResponse
    {
        $employee->update($this->validated($request, $employee));

        return $this->reassignApprovals(
            $this->respond($request, 'Data karyawan berhasil diperbarui.', route('masters.employees.show', $employee))
        );
    }

    private function form(Employee $employee): View
    {
        return view('employees.form', [
            'employee' => $employee,
            'departments' => Department::active()->orderBy('name')->get(),
            'locations' => Location::active()->orderBy('name')->get(),
            'managers' => Employee::active()->when($employee->exists, fn ($q) => $q->whereKeyNot($employee->id))->orderBy('name')->get(),
        ]);
    }

    private function validated(Request $request, ?Employee $employee): array
    {
        $data = $request->validate([
            'employee_no' => ['required', 'string', 'max:30', Rule::unique('employees')->ignore($employee)],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('employees')->ignore($employee)],
            'phone' => ['nullable', 'string', 'max:30'],
            'department_id' => ['required', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'manager_employee_id' => ['nullable', 'exists:employees,id', Rule::notIn([$employee?->id])],
            'job_title' => ['nullable', 'string', 'max:100'],
            'employment_status' => ['required', Rule::enum(EmploymentStatus::class)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'work_location_id' => ['nullable', Rule::exists('locations', 'id')->whereNull('deleted_at')],
        ]);

        // Cegah siklus struktur atasan (A → B → A)
        if ($employee && ! empty($data['manager_employee_id'])) {
            $manager = Employee::find($data['manager_employee_id']);
            if ($manager && $manager->hasInManagerChain($employee->id)) {
                throw ValidationException::withMessages([
                    'manager_employee_id' => 'Atasan yang dipilih berada di bawah karyawan ini (struktur melingkar).',
                ]);
            }
        }

        return $data;
    }
}
