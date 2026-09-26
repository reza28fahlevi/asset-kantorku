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
    'request_no', 'approval_request_id', 'requester_employee_id', 'created_by_user_id', 'recipient_employee_id',
    'location_id', 'purpose', 'start_date', 'status', 'submitted_at', 'fulfilled_at', 'cancelled_at',
])]
class AssignmentRequest extends Model implements Approvable
{
    public const VIEW_ALL_PERMISSION = 'assignment.view_all';

    /** Kolom karyawan yang juga berhak melihat permintaan. */
    public const BENEFICIARY_COLUMNS = ['recipient_employee_id'];

    use Auditable, InteractsWithApproval;

    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'start_date' => 'date',
            'submitted_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'recipient_employee_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withTrashed();
    }

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'assignment_request_items')->withTimestamps();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

    public function approvalType(): ApprovalType
    {
        return ApprovalType::Assignment;
    }

    /** Approver = manager penerima aset. */
    public function approvalSubjectEmployee(): Employee
    {
        return $this->recipient;
    }

    public function approvalTitle(): string
    {
        return "{$this->request_no} · Assignment untuk {$this->recipient->name}";
    }

    public function approvalSummary(): array
    {
        return [
            'Diajukan oleh' => $this->requester->name,
            'Penerima' => $this->recipient->display_name,
            'Aset' => $this->assets->map(fn ($a) => "{$a->asset_tag} {$a->name}")->implode(', '),
            'Lokasi' => $this->location->name,
            'Mulai' => $this->start_date->translatedFormat('d M Y'),
            'Tujuan' => $this->purpose,
        ];
    }

    public function approvalUrl(): string
    {
        return route('assignments.show', $this);
    }
}
