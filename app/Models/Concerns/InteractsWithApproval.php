<?php

namespace App\Models\Concerns;

use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Relasi & helper bersama untuk model permintaan yang memakai approval.
 */
trait InteractsWithApproval
{
    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requester_employee_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** Step approval yang masih menunggu keputusan (jika ada). */
    public function pendingStep(): ?ApprovalStep
    {
        return $this->approvalRequest?->steps->firstWhere('status', \App\Enums\ApprovalStatus::Pending);
    }

    public function isOwnedBy(User $user): bool
    {
        return ($user->employee_id && $this->requester_employee_id === $user->employee_id)
            || $this->created_by_user_id === $user->id;
    }

    /**
     * Cakupan data: user dengan permission "view_all" melihat semua; lainnya hanya permintaan
     * yang ia buat, yang menjadikannya penerima/peminjam, atau yang ia putuskan sebagai approver.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->hasPermission(static::VIEW_ALL_PERMISSION)) {
            return;
        }

        $employeeId = $user->employee_id ?? 0;
        $query->where(function (Builder $q) use ($user, $employeeId) {
            $q->where($this->qualifyColumn('created_by_user_id'), $user->id)
                ->orWhere($this->qualifyColumn('requester_employee_id'), $employeeId);
            foreach (static::BENEFICIARY_COLUMNS as $column) {
                $q->orWhere($this->qualifyColumn($column), $employeeId);
            }
            $q->orWhereIn($this->qualifyColumn('approval_request_id'), ApprovalStep::query()
                ->select('approval_request_id')
                ->where('approver_employee_id', $employeeId));
        });
    }

    public function isVisibleTo(User $user): bool
    {
        return static::query()->whereKey($this->getKey())->visibleTo($user)->exists();
    }
}
