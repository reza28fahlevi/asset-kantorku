<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetLoan;
use App\Models\Attachment;
use App\Models\ProcurementReceipt;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AttachmentPolicy
{
    /** Lampiran hanya dapat diunduh oleh user yang berhak melihat dokumen induknya. */
    public function download(User $user, Attachment $attachment): bool
    {
        $owner = $attachment->attachable;

        return match (true) {
            $owner === null => false,
            $owner instanceof Asset => $user->hasPermission('asset.view'),
            $owner instanceof ProcurementReceipt => Gate::forUser($user)->allows('view', $owner->procurementRequest),
            // Berita acara serah terima/peminjaman: pemegang/peminjam aset selalu boleh mengunduh miliknya
            $owner instanceof AssetAssignment => $this->isOwnEmployee($user, $owner->employee_id)
                || $user->hasPermission('assignment.view_all')
                || ($owner->assignment_request_id && Gate::forUser($user)->allows('view', $owner->assignmentRequest)),
            $owner instanceof AssetLoan => $this->isOwnEmployee($user, $owner->borrower_employee_id)
                || $user->hasPermission('loan.view_all')
                || ($owner->asset_loan_request_id && Gate::forUser($user)->allows('view', $owner->loanRequest)),
            default => Gate::forUser($user)->allows('view', $owner),
        };
    }

    private function isOwnEmployee(User $user, ?int $employeeId): bool
    {
        return $user->employee_id !== null && $employeeId !== null && $user->employee_id === $employeeId;
    }
}
