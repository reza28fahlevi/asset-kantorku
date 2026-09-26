<?php

namespace App\Services;

use App\Contracts\ApprovalHandler;
use App\Contracts\Approvable;
use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Exceptions\BusinessRuleException;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Pola approval umum: submit → snapshot approver → keputusan (immutable) → handler domain.
 */
class ApprovalService
{
    public function __construct(private Notifier $notifier)
    {
    }

    /**
     * Buat approval request + step manager untuk sebuah permintaan.
     * Harus dipanggil di dalam transaksi bersama perubahan status permintaan.
     */
    public function submit(Model&Approvable $subject, Employee $requester, User $submittedBy): ApprovalRequest
    {
        if (! $requester->isActive()) {
            throw new BusinessRuleException('Karyawan nonaktif tidak dapat mengajukan permintaan baru.');
        }

        [$approver, $source] = $this->resolveApprover($subject->approvalSubjectEmployee(), $requester);

        $approval = ApprovalRequest::create([
            'request_type' => $subject->approvalType(),
            'requester_employee_id' => $requester->id,
            'submitted_by_user_id' => $submittedBy->id,
            'status' => ApprovalStatus::Pending,
            'submitted_at' => now(),
        ]);

        $approval->steps()->create([
            'step_order' => 1,
            'approver_employee_id' => $approver->id,
            'approver_source' => $source,
            'status' => ApprovalStatus::Pending,
        ]);

        $subject->approval_request_id = $approval->id;
        $subject->save();

        $this->notifier->toEmployee(
            $approver,
            'Permintaan approval baru',
            $subject->approvalTitle().' menunggu keputusan Anda.',
            route('approvals.index'),
            'bi-inbox',
        );

        return $approval;
    }

    /**
     * Approver = atasan langsung karyawan subjek (snapshot saat submit).
     * Jika atasan kosong/nonaktif/tanpa akun approver/sama dengan requester → approver eskalasi.
     *
     * @return array{0: Employee, 1: string}
     */
    public function resolveApprover(Employee $subjectEmployee, Employee $requester): array
    {
        $manager = $subjectEmployee->manager;
        if ($this->isEligibleApprover($manager, $requester)) {
            return [$manager, ApprovalStep::SOURCE_MANAGER];
        }

        $escalationId = Setting::get('approval.escalation_employee_id');
        $escalation = $escalationId ? Employee::find($escalationId) : null;
        if ($this->isEligibleApprover($escalation, $requester)) {
            return [$escalation, ApprovalStep::SOURCE_ESCALATION];
        }

        throw new BusinessRuleException(
            'Tidak ditemukan approver yang valid: atasan '.$subjectEmployee->name.' kosong/nonaktif/tidak memiliki akun approver, '
            .'dan approver eskalasi belum dikonfigurasi. Hubungi System Administrator.'
        );
    }

    private function isEligibleApprover(?Employee $candidate, Employee $requester): bool
    {
        return $candidate !== null
            && $candidate->isActive()
            && $candidate->id !== $requester->id
            && $candidate->user?->isApprover() === true;
    }

    /**
     * Putuskan approval step. Validasi ulang dilakukan di dalam transaksi dengan row lock.
     */
    public function decide(ApprovalStep $step, User $user, bool $approve, ?string $comment): ApprovalRequest
    {
        $comment = trim((string) $comment);
        $comment = $comment === '' ? null : $comment;
        if (! $approve && $comment === null) {
            throw new BusinessRuleException('Alasan penolakan wajib diisi.');
        }

        return DB::transaction(function () use ($step, $user, $approve, $comment) {
            /** @var ApprovalStep $step */
            $step = ApprovalStep::whereKey($step->id)->lockForUpdate()->firstOrFail();
            /** @var ApprovalRequest $approval */
            $approval = ApprovalRequest::whereKey($step->approval_request_id)->lockForUpdate()->firstOrFail();

            if (! $user->isApprover() || $user->employee_id !== $step->approver_employee_id) {
                throw new AuthorizationException('Anda bukan approver yang dituju untuk permintaan ini.');
            }
            if ($approval->requester_employee_id === $user->employee_id) {
                throw new AuthorizationException('Anda tidak dapat menyetujui permintaan Anda sendiri.');
            }
            if (! $step->isPending() || $approval->status !== ApprovalStatus::Pending) {
                throw new BusinessRuleException('Permintaan ini sudah diputuskan atau dibatalkan.');
            }

            $step->update([
                'status' => $approve ? ApprovalStatus::Approved : ApprovalStatus::Rejected,
                'comment' => $comment,
                'decided_at' => now(),
                'decided_by_user_id' => $user->id,
            ]);

            $subject = $approval->subject();
            $handler = $this->handlerFor($approval->request_type);

            if (! $approve) {
                $approval->update(['status' => ApprovalStatus::Rejected, 'closed_at' => now()]);
                $handler->onRejected($subject, $user, $comment);
            } elseif (! $approval->steps()->where('status', ApprovalStatus::Pending->value)->exists()) {
                // Semua step selesai (MVP: satu step manager)
                $approval->update(['status' => ApprovalStatus::Approved, 'closed_at' => now()]);
                $handler->onApproved($subject, $user);
            }

            $this->notifier->toEmployee(
                $approval->requester,
                $approve ? 'Permintaan disetujui' : 'Permintaan ditolak',
                $subject->approvalTitle().($approve ? ' telah disetujui.' : ' ditolak: '.$comment),
                $subject->approvalUrl(),
                $approve ? 'bi-check-circle' : 'bi-x-circle',
            );

            return $approval;
        });
    }

    /**
     * Alihkan step PENDING yang approver-nya tidak lagi memenuhi syarat (karyawan nonaktif/cuti,
     * akun nonaktif, atau kehilangan permission approval). Snapshot approver bersifat immutable,
     * sehingga step lama ditutup (CANCELLED + catatan) dan step baru dibuat untuk approver pengganti
     * (atasan terkini bila valid, selain itu approver eskalasi).
     *
     * @param  int|null  $employeeId  batasi ke approver tertentu; null = periksa semua step pending
     * @return array{reassigned: int, failed: int}
     */
    public function reassignIneligibleSteps(?int $employeeId = null): array
    {
        $result = ['reassigned' => 0, 'failed' => 0];

        $steps = ApprovalStep::query()
            ->with('approver.user', 'approvalRequest.requester')
            ->where('status', ApprovalStatus::Pending->value)
            ->when($employeeId, fn ($q) => $q->where('approver_employee_id', $employeeId))
            ->get()
            ->reject(fn (ApprovalStep $step) => $step->approver?->isActive() && $step->approver->user?->isApprover());

        foreach ($steps as $step) {
            try {
                DB::transaction(function () use ($step) {
                    $step = ApprovalStep::whereKey($step->id)->lockForUpdate()->firstOrFail();
                    $approval = ApprovalRequest::whereKey($step->approval_request_id)->lockForUpdate()->firstOrFail();
                    if (! $step->isPending() || $approval->status !== ApprovalStatus::Pending) {
                        return;
                    }

                    $subject = $approval->subject();
                    [$approver, $source] = $this->resolveApprover($subject->approvalSubjectEmployee(), $approval->requester);

                    $step->update([
                        'status' => ApprovalStatus::Cancelled,
                        'comment' => 'Dialihkan otomatis: approver '.($step->approver?->name ?? '#'.$step->approver_employee_id).' tidak lagi aktif/berwenang.',
                    ]);
                    $approval->steps()->create([
                        'step_order' => (int) $approval->steps()->max('step_order') + 1,
                        'approver_employee_id' => $approver->id,
                        'approver_source' => $source,
                        'status' => ApprovalStatus::Pending,
                    ]);

                    AuditLogger::log('approval_reassigned', $approval, ['approver_employee_id' => $step->approver_employee_id], ['approver_employee_id' => $approver->id]);
                    $this->notifier->toEmployee($approver, 'Permintaan approval dialihkan kepada Anda',
                        $subject->approvalTitle().' menunggu keputusan Anda (approver sebelumnya tidak aktif).', route('approvals.index'), 'bi-inbox');
                });
                $result['reassigned']++;
            } catch (BusinessRuleException) {
                // Tidak ada approver pengganti yang valid (eskalasi belum dikonfigurasi)
                $result['failed']++;
            }
        }

        return $result;
    }

    /** Batalkan approval yang masih berjalan (mis. requester membatalkan permintaan). */
    public function cancel(?ApprovalRequest $approval): void
    {
        if (! $approval || $approval->status !== ApprovalStatus::Pending) {
            return;
        }

        $approval->steps()->where('status', ApprovalStatus::Pending->value)->get()
            ->each(fn (ApprovalStep $step) => $step->update(['status' => ApprovalStatus::Cancelled]));

        $approval->update(['status' => ApprovalStatus::Cancelled, 'closed_at' => now()]);
    }

    private function handlerFor(ApprovalType $type): ApprovalHandler
    {
        return app(match ($type) {
            ApprovalType::Procurement => ProcurementService::class,
            ApprovalType::Assignment => AssignmentService::class,
            ApprovalType::Loan, ApprovalType::LoanExtension => LoanService::class,
            ApprovalType::Disposal => DisposalService::class,
        });
    }
}
