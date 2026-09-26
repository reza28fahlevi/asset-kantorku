<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        return view('settings.edit', [
            'settings' => Setting::query()->pluck('value', 'key'),
            'employees' => Employee::active()->with('user')->orderBy('name')->get()
                ->filter(fn (Employee $e) => $e->user?->isApprover()),
        ]);
    }

    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'app_company_name' => ['required', 'string', 'max:150'],
            'approval_escalation_employee_id' => ['nullable', 'exists:employees,id'],
            'loan_max_duration_days' => ['required', 'integer', 'min:1', 'max:365'],
            'attachment_max_size_kb' => ['required', 'integer', 'min:100', 'max:20480'],
        ]);

        $old = Setting::query()->pluck('value', 'key')->all();
        $new = [
            'app.company_name' => $data['app_company_name'],
            'approval.escalation_employee_id' => $data['approval_escalation_employee_id'] ?? null,
            'loan.max_duration_days' => (string) $data['loan_max_duration_days'],
            'attachment.max_size_kb' => (string) $data['attachment_max_size_kb'],
        ];
        foreach ($new as $key => $value) {
            Setting::put($key, $value);
        }
        AuditLogger::log('settings_updated', null, array_intersect_key($old, $new), $new);

        return $this->respond($request, 'Pengaturan berhasil disimpan.');
    }
}
