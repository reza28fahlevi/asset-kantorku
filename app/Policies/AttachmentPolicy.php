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
            $owner instanceof AssetAssignment => $owner->assignment_request_id
                ? Gate::forUser($user)->allows('view', $owner->assignmentRequest)
                : $user->hasPermission('assignment.view_all'),
            $owner instanceof AssetLoan => $owner->asset_loan_request_id
                ? Gate::forUser($user)->allows('view', $owner->loanRequest)
                : $user->hasPermission('loan.view_all'),
            default => Gate::forUser($user)->allows('view', $owner),
        };
    }
}
