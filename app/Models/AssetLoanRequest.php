<?php

namespace App\Models;

use App\Contracts\Approvable;
use App\Enums\ApprovalType;
use App\Enums\RequestStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\InteractsWithApproval;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'request_no', 'approval_request_id', 'requester_employee_id', 'created_by_user_id', 'borrower_employee_id',
    'usage_location_id', 'purpose', 'start_date', 'due_date', 'status', 'submitted_at', 'fulfilled_at', 'cancelled_at',
])]
class AssetLoanRequest extends Model implements Approvable
{
    public const VIEW_ALL_PERMISSION = 'loan.view_all';

    /** Kolom karyawan yang juga berhak melihat permintaan. */
    public const BENEFICIARY_COLUMNS = ['borrower_employee_id'];

    use Auditable, InteractsWithApproval;

    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'start_date' => 'date',
            'due_date' => 'date',
            'submitted_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'borrower_employee_id');
    }

    public function usageLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'usage_location_id')->withTrashed();
    }

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'asset_loan_request_items')->withTimestamps();
    }

    public function loans(): HasMany
    {
        return $this->hasMany(AssetLoan::class);
    }

    public function approvalType(): ApprovalType
    {
        return ApprovalType::Loan;
    }

    /** Approver = manager peminjam. */
    public function approvalSubjectEmployee(): Employee
    {
        return $this->borrower;
    }

    public function approvalTitle(): string
    {
        return "{$this->request_no} · Peminjaman oleh {$this->borrower->name}";
    }

    public function approvalSummary(): array
    {
        return [
            'Diajukan oleh' => $this->requester->name,
            'Peminjam' => $this->borrower->display_name,
            'Aset' => $this->assets->map(fn ($a) => "{$a->asset_tag} {$a->name}")->implode(', '),
            'Periode' => $this->start_date->translatedFormat('d M Y').' s.d. '.$this->due_date->translatedFormat('d M Y'),
            'Tujuan' => $this->purpose,
        ];
    }

    public function approvalUrl(): string
    {
        return route('loans.show', $this);
    }
}
