<?php

namespace App\Services;

use App\Contracts\ApprovalHandler;
use App\Contracts\Approvable;
use App\Enums\AssetEventType;
use App\Enums\AssetStatus;
use App\Enums\RequestStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AssetAssignment;
use App\Models\AssignmentRequest;
use App\Models\Employee;
use App\Models\User;
use App\Services\Concerns\GuardsWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AssignmentService implements ApprovalHandler
{
    use GuardsWorkflow;

    public function __construct(
        private ApprovalService $approvals,
        private AssetService $assets,
        private NumberGenerator $numbers,
        private AttachmentService $attachments,
        private Notifier $notifier,
    ) {
    }

    public function create(array $data, User $user, bool $submit): AssignmentRequest
    {
        $employee = $this->actingEmployee($user);
        $recipient = $user->hasPermission('assignment.create_for_others') && ! empty($data['recipient_employee_id'])
            ? Employee::findOrFail($data['recipient_employee_id'])
            : $employee;
        $this->ensureActiveEmployee($recipient, 'Penerima');

        return DB::transaction(function () use ($data, $user, $employee, $recipient, $submit) {
            $this->assets->lockAvailable($data['asset_ids']);

            $request = AssignmentRequest::create([
                'request_no' => $this->numbers->requestNumber('ASG'),
                'requester_employee_id' => $employee->id,
                'created_by_user_id' => $user->id,
                'recipient_employee_id' => $recipient->id,
                'location_id' => $data['location_id'],
                'purpose' => $data['purpose'],
                'start_date' => $data['start_date'],
                'status' => RequestStatus::Draft,
            ]);
            $request->assets()->attach($data['asset_ids']);

            if ($submit) {
                $this->submit($request, $user);
            }

            return $request;
        });
    }

    public function submit(AssignmentRequest $request, User $user): void
    {
        DB::transaction(function () use ($request, $user) {
            $request = AssignmentRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->ensureStatus($request->status, [RequestStatus::Draft], 'mengajukan assignment');
            $this->ensureActiveEmployee($request->recipient, 'Penerima');

            $assetIds = $request->assets()->pluck('assets.id')->all();
            $this->assets->lockAvailable($assetIds);
            $this->assets->ensureNoConflictingRequests($assetIds, $request->start_date, null, $request);

            $request->update(['status' => RequestStatus::PendingApproval, 'submitted_at' => now()]);
            $this->approvals->submit($request, $request->requester, $user);
        });
    }


    /**
     * Status yang boleh dibatalkan: draft & menunggu approval, atau sudah disetujui namun belum
     * ditindaklanjuti. Pembatalan setelah disetujui wajib beralasan, tercatat di audit log, dan
     * requester diberi tahu bila dibatalkan oleh petugas.
     */
    public function cancel(AssignmentRequest $request, ?User $user = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($request, $user, $reason) {
            $request = AssignmentRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->ensureStatus($request->status, [RequestStatus::Draft, RequestStatus::PendingApproval, RequestStatus::Approved], 'membatalkan assignment');
            $wasApproved = $request->status === RequestStatus::Approved;
            $reason = $this->cancelReason($wasApproved, $reason);

            $this->approvals->cancel($request->approvalRequest);
            $request->update(['status' => RequestStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => $reason, 'cancelled_by_user_id' => $user?->id]);
            $this->afterCancel($request, $wasApproved, $user, $reason, route('assignments.show', $request));
        });
    }

    public function onApproved(Model&Approvable $subject, User $decidedBy): void
    {
        /** @var AssignmentRequest $subject */
        $subject->update(['status' => RequestStatus::Approved]);

        $this->notifier->toPermission(
            'assignment.handover',
            'Assignment siap diserahterimakan',
            "{$subject->request_no} untuk {$subject->recipient->name} telah disetujui.",
            route('assignments.show', $subject),
            'bi-person-check',
        );
    }

    public function onRejected(Model&Approvable $subject, User $decidedBy, string $comment): void
    {
        /** @var AssignmentRequest $subject */
        $subject->update(['status' => RequestStatus::Rejected]);
    }

    /**
     * Serah-terima oleh administrator: aset → ASSIGNED, assignment aktif dibuat.
     * Ketersediaan divalidasi ulang untuk mencegah bentrok dengan transaksi lain.
     */
    public function handover(AssignmentRequest $request, array $data, User $user): void
    {
        DB::transaction(function () use ($request, $data, $user) {
            $request = AssignmentRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->ensureStatus($request->status, [RequestStatus::Approved], 'melakukan serah-terima');
            $this->ensureActiveEmployee($request->recipient, 'Penerima');

            $assets = $this->assets->lockAvailable($request->assets()->pluck('assets.id')->all());
            $assignedAt = Carbon::parse($data['assigned_at']);

            foreach ($assets as $asset) {
                $assignment = AssetAssignment::create([
                    'asset_id' => $asset->id,
                    'employee_id' => $request->recipient_employee_id,
                    'assignment_request_id' => $request->id,
                    'location_id' => $request->location_id,
                    'assigned_at' => $assignedAt,
                    'assigned_by_user_id' => $user->id,
                    'condition_out' => $data['condition_out'],
                    'handover_document_no' => $data['handover_document_no'] ?? null,
                    'handover_notes' => $data['handover_notes'] ?? null,
                ]);
                $this->attachments->store($assignment, $data['attachments'] ?? null, 'HANDOVER');

                $this->assets->transition($asset, AssetStatus::Assigned, AssetEventType::Assigned,
                    ['location_id' => $request->location_id, 'condition' => $data['condition_out']],
                    [
                        'related_employee_id' => $request->recipient_employee_id,
                        'reference_type' => 'asset_assignment',
                        'reference_id' => $assignment->id,
                        'notes' => "Serah-terima {$request->request_no}",
                        'occurred_at' => $assignedAt,
                    ]);
            }

            $request->update(['status' => RequestStatus::Fulfilled, 'fulfilled_at' => now()]);

            $this->notifier->toEmployee($request->recipient, 'Aset diserahterimakan',
                "Aset pada {$request->request_no} kini menjadi tanggung jawab Anda.", route('assignments.show', $request), 'bi-person-check');
        });
    }

    /** Pengembalian assignment: status aset ditentukan dari hasil inspeksi. */
    public function returnAsset(AssetAssignment $assignment, array $data, User $user): void
    {
        DB::transaction(function () use ($assignment, $data, $user) {
            $assignment = AssetAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            if (! $assignment->isActive()) {
                throw new BusinessRuleException('Assignment ini sudah ditutup.');
            }
            $returnedAt = Carbon::parse($data['returned_at']);
            if ($returnedAt->lt($assignment->assigned_at)) {
                throw new BusinessRuleException('Tanggal kembali tidak boleh sebelum tanggal serah-terima.');
            }

            $assignment->update([
                'returned_at' => $returnedAt,
                'returned_by_user_id' => $user->id,
                'condition_in' => $data['condition_in'],
                'return_notes' => $data['return_notes'] ?? null,
            ]);
            $this->attachments->store($assignment, $data['attachments'] ?? null, 'RETURN');

            $asset = $assignment->asset()->lockForUpdate()->first();
            $this->assets->transition($asset, AssetStatus::from($data['next_status']), AssetEventType::AssignmentReturned,
                ['location_id' => $data['location_id'], 'condition' => $data['condition_in']],
                [
                    'related_employee_id' => $assignment->employee_id,
                    'reference_type' => 'asset_assignment',
                    'reference_id' => $assignment->id,
                    'notes' => $data['return_notes'] ?? null,
                    'occurred_at' => $returnedAt,
                ]);
        });
    }
}
