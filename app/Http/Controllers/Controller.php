<?php

namespace App\Http\Controllers;

use App\Services\ApprovalService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Setelah perubahan karyawan/akun/role/permission: alihkan approval pending yang approver-nya
     * tidak lagi berwenang, dan beri peringatan bila tidak ada approver pengganti.
     */
    protected function reassignApprovals(RedirectResponse $response): RedirectResponse
    {
        $result = app(ApprovalService::class)->reassignIneligibleSteps();

        if ($result['failed'] > 0) {
            $response->with('warning', "{$result['failed']} approval pending tidak dapat dialihkan karena approver eskalasi belum valid. Atur di Pengaturan → Approver Eskalasi.");
        } elseif ($result['reassigned'] > 0) {
            $response->with('info', "{$result['reassigned']} approval pending dialihkan ke approver pengganti.");
        }

        return $response;
    }
}
