<?php

namespace App\Services;

use App\Contracts\ApprovalHandler;
use App\Contracts\Approvable;
use App\Enums\AssetEventType;
use App\Enums\AssetStatus;
use App\Enums\DisposalStatus;
use App\Enums\RequestStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Asset;
use App\Models\AssetLoanRequest;
use App\Models\AssignmentRequest;
use App\Models\DisposalRequest;
use App\Models\User;
use App\Services\Concerns\GuardsWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DisposalService implements ApprovalHandler
{
    use GuardsWorkflow;

    /** Status aset yang boleh diajukan disposal (tidak sedang ditugaskan/dipinjam). */
    private const DISPOSABLE = [AssetStatus::Available, AssetStatus::InRepair, AssetStatus::Lost];

    public function __construct(
        private ApprovalService $approvals,
        private AssetService $assets,
        private NumberGenerator $numbers,
        private AttachmentService $attachments,
        private Notifier $notifier,
    ) {
    }

    public function create(array $data, User $user, bool $submit): DisposalRequest
    {
        $employee = $this->actingEmployee($user);

        return DB::transaction(function () use ($data, $user, $employee, $submit) {
            $asset = Asset::whereKey($data['asset_id'])->lockForUpdate()->firstOrFail();
            $this->ensureDisposable($asset);
            if ($asset->disposalRequests()->whereIn('status', ['DRAFT', 'PENDING_APPROVAL', 'APPROVED'])->exists()) {
                throw new BusinessRuleException("Aset {$asset->asset_tag} sudah memiliki pengajuan disposal yang terbuka.");
            }

            $request = DisposalRequest::create([
                'request_no' => $this->numbers->requestNumber('DSP'),
                'asset_id' => $asset->id,
                'requester_employee_id' => $employee->id,
                'created_by_user_id' => $user->id,
                'reason_type' => $data['reason_type'],
                'reason' => $data['reason'],
                'condition_description' => $data['condition_description'] ?? null,
                'planned_method' => $data['planned_method'],
                'status' => DisposalStatus::Draft,
            ]);
            $this->attachments->store($request, $data['attachments'] ?? null, 'DISPOSAL_EVIDENCE');

            if ($submit) {
                $this->submit($request, $user);
            }

            return $request;
        });
    }

    /** Submit: aset dikunci menjadi PENDING_DISPOSAL selama menunggu keputusan. */
    public function submit(DisposalRequest $request, User $user): void
    {
        DB::transaction(function () use ($request, $user) {
            $request = DisposalRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->ensureStatus($request->status, [DisposalStatus::Draft], 'mengajukan disposal');

            $asset = Asset::whereKey($request->asset_id)->lockForUpdate()->firstOrFail();
            $this->ensureDisposable($asset);

            $request->update([
                'status' => DisposalStatus::PendingApproval,
                'asset_status_before' => $asset->status,
                'submitted_at' => now(),
            ]);

            $this->assets->transition($asset, AssetStatus::PendingDisposal, AssetEventType::DisposalRequested, [], [
                'reference_type' => 'disposal_request',
                'reference_id' => $request->id,
                'notes' => "{$request->request_no}: {$request->reason_type->label()}",
            ]);

            $this->approvals->submit($request, $request->requester, $user);
        });
    }

    public function cancel(DisposalRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $request = DisposalRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->ensureStatus($request->status, [DisposalStatus::Draft, DisposalStatus::PendingApproval], 'membatalkan disposal');

            if ($request->status === DisposalStatus::PendingApproval) {
                $this->approvals->cancel($request->approvalRequest);
                $this->restoreAsset($request, AssetEventType::DisposalCancelled, 'Pengajuan dibatalkan');
            }
            $request->update(['status' => DisposalStatus::Cancelled, 'cancelled_at' => now()]);
        });
    }

    public function onApproved(Model&Approvable $subject, User $decidedBy): void
    {
        /** @var DisposalRequest $subject */
        $subject->update(['status' => DisposalStatus::Approved]);
        $this->notifier->toPermission('disposal.execute', 'Disposal siap dieksekusi',
            "{$subject->request_no} ({$subject->asset->asset_tag}) telah disetujui.", route('disposals.show', $subject), 'bi-trash3');
    }

    /** Ditolak: aset kembali ke status operasional sebelumnya. */
    public function onRejected(Model&Approvable $subject, User $decidedBy, string $comment): void
    {
        /** @var DisposalRequest $subject */
        $subject->update(['status' => DisposalStatus::Rejected]);
        $this->restoreAsset($subject, AssetEventType::DisposalRejected, 'Ditolak: '.$comment);
    }

    /** Administrator mencatat pelaksanaan disposal beserta bukti → aset DISPOSED (final). */
    public function execute(DisposalRequest $request, array $data, User $user): void
    {
        DB::transaction(function () use ($request, $data, $user) {
            $request = DisposalRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->ensureStatus($request->status, [DisposalStatus::Approved], 'mengeksekusi disposal');

            $asset = Asset::whereKey($request->asset_id)->lockForUpdate()->firstOrFail();
            if ($asset->status !== AssetStatus::PendingDisposal) {
                throw new BusinessRuleException("Status aset {$asset->asset_tag} tidak valid untuk disposal ({$asset->status->label()}).");
            }

            $request->update([
                'status' => DisposalStatus::Completed,
                'executed_at' => $data['executed_at'],
                'executed_by_user_id' => $user->id,
                'actual_method' => $data['actual_method'],
                'disposal_recipient' => $data['disposal_recipient'] ?? null,
                'proceeds_amount' => $data['proceeds_amount'] ?? null,
                'execution_notes' => $data['execution_notes'] ?? null,
                'completed_at' => now(),
            ]);
            $this->attachments->store($request, $data['attachments'] ?? null, 'DISPOSAL_EVIDENCE');

            $this->assets->transition($asset, AssetStatus::Disposed, AssetEventType::Disposed, [], [
                'reference_type' => 'disposal_request',
                'reference_id' => $request->id,
                'notes' => "{$request->request_no}: {$request->actual_method->label()}",
                'metadata' => array_filter([
                    'recipient' => $data['disposal_recipient'] ?? null,
                    'proceeds_amount' => $data['proceeds_amount'] ?? null,
                ]),
            ]);
        });
    }

    private function ensureDisposable(Asset $asset): void
    {
        if (! in_array($asset->status, self::DISPOSABLE, true)) {
            throw new BusinessRuleException(
                "Aset {$asset->asset_tag} berstatus {$asset->status->label()}. "
                .'Aset yang ditugaskan/dipinjam harus dikembalikan terlebih dahulu.'
            );
        }

        $open = [RequestStatus::PendingApproval->value, RequestStatus::Approved->value];
        $hasOpenRequest = AssignmentRequest::whereIn('status', $open)->whereHas('assets', fn ($q) => $q->whereKey($asset->id))->exists()
            || AssetLoanRequest::whereIn('status', $open)->whereHas('assets', fn ($q) => $q->whereKey($asset->id))->exists();
        if ($hasOpenRequest) {
            throw new BusinessRuleException("Aset {$asset->asset_tag} masih memiliki permintaan assignment/peminjaman yang terbuka.");
        }
    }

    private function restoreAsset(DisposalRequest $request, AssetEventType $event, string $notes): void
    {
        $asset = Asset::whereKey($request->asset_id)->lockForUpdate()->firstOrFail();
        if ($asset->status !== AssetStatus::PendingDisposal) {
            return;
        }

        $this->assets->transition($asset, $request->asset_status_before ?? AssetStatus::Available, $event, [], [
            'reference_type' => 'disposal_request',
            'reference_id' => $request->id,
            'notes' => $notes,
        ]);
    }
}
