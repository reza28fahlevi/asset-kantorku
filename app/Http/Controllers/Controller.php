<?php

namespace App\Http\Controllers;

use App\Services\ApprovalService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Respons aksi CRUD. Request AJAX (Accept: application/json, dari form [data-ajax-form]) menerima
     * JSON {status, message, redirect} yang ditampilkan dengan SweetAlert; request biasa tetap
     * redirect + flash sehingga form tanpa JavaScript tetap berfungsi.
     *
     * @param  'success'|'error'|'warning'|'info'  $status
     */
    protected function respond(Request $request, string $message, ?string $redirect = null, string $status = 'success'): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'status' => $status,
                'message' => $message,
                'redirect' => $redirect,
            ], $status === 'error' ? 422 : 200);
        }

        return ($redirect ? redirect($redirect) : back())->with($status, $message);
    }

    /**
     * Setelah perubahan karyawan/akun/role/permission: alihkan approval pending yang approver-nya
     * tidak lagi berwenang, dan beri peringatan bila tidak ada approver pengganti.
     */
    protected function reassignApprovals(JsonResponse|RedirectResponse $response): JsonResponse|RedirectResponse
    {
        $result = app(ApprovalService::class)->reassignIneligibleSteps();

        $notice = match (true) {
            $result['failed'] > 0 => ['warning', "{$result['failed']} approval pending tidak dapat dialihkan karena approver eskalasi belum valid. Atur di Pengaturan → Approver Eskalasi."],
            $result['reassigned'] > 0 => ['info', "{$result['reassigned']} approval pending dialihkan ke approver pengganti."],
            default => null,
        };

        if ($notice) {
            if ($response instanceof JsonResponse) {
                $response->setData([...(array) $response->getData(true), 'notice' => ['status' => $notice[0], 'message' => $notice[1]]]);
            } else {
                $response->with($notice[0], $notice[1]);
            }
        }

        return $response;
    }
}
