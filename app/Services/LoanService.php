<?php

namespace App\Services;

use App\Contracts\ApprovalHandler;
use App\Contracts\Approvable;
use App\Enums\AssetEventType;
use App\Enums\AssetStatus;
use App\Enums\ExtensionStatus;
use App\Enums\LoanStatus;
use App\Enums\RequestStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AssetLoan;
use App\Models\AssetLoanRequest;
use App\Models\Employee;
use App\Models\LoanExtensionRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\Concerns\GuardsWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LoanService implements ApprovalHandler
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

    public function create(array $data, User $user, bool $submit): AssetLoanRequest
    {
        $employee = $this->actingEmployee($user);
        $borrower = $user->hasPermission('loan.create_for_others') && ! empty($data['borrower_employee_id'])
            ? Employee::findOrFail($data['borrower_employee_id'])
            : $employee;
        $this->ensureActiveEmployee($borrower, 'Peminjam');
        $this->ensureDuration(Carbon::parse($data['start_date']), Carbon::parse($data['due_date']));

        return DB::transaction(function () use ($data, $user, $employee, $borrower, $submit) {
            $this->assets->lockAvailable($data['asset_ids']);

            $request = AssetLoanRequest::create([
                'request_no' => $this->numbers->requestNumber('LN'),
                'requester_employee_id' => $employee->id,
                'created_by_user_id' => $user->id,
                'borrower_employee_id' => $borrower->id,
                'usage_location_id' => $data['usage_location_id'] ?? null,
                'purpose' => $data['purpose'],
                'start_date' => $data['start_date'],
                'due_date' => $data['due_date'],
                'status' => RequestStatus::Draft,
            ]);
            $request->assets()->attach($data['asset_ids']);

            if ($submit) {
                $this->submit($request, $user);
            }

            return $request;
        });
    }

    public function submit(AssetLoanRequest $request, User $user): void
    {
        DB::transaction(function () use ($request, $user) {
            $request = AssetLoanRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->ensureStatus($request->status, [RequestStatus::Draft], 'mengajukan peminjaman');
            $this->ensureActiveEmployee($request->borrower, 'Peminjam');
            if ($request->start_date->lt(today())) {
                throw new BusinessRuleException('Tanggal mulai sudah lewat. Batalkan dan buat permintaan baru.');
            }
            // Cek ulang durasi: pengaturan maksimal bisa berubah sejak draft dibuat
            $this->ensureDuration($request->start_date, $request->due_date);

            $assetIds = $request->assets()->pluck('assets.id')->all();
            $this->assets->lockAvailable($assetIds);
            $this->assets->ensureNoConflictingRequests($assetIds, $request->start_date, $request->due_date, $request);

            $request->update(['status' => RequestStatus::PendingApproval, 'submitted_at' => now()]);
            $this->approvals->submit($request, $request->requester, $user);
        });
    }


    /**
     * Status yang boleh dibatalkan: draft & menunggu approval, atau sudah disetujui namun belum
     * ditindaklanjuti. Pembatalan setelah disetujui wajib beralasan, tercatat di audit log, dan
     * requester diberi tahu bila dibatalkan oleh petugas.
     */
    public function cancel(AssetLoanRequest $request, ?User $user = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($request, $user, $reason) {
            $request = AssetLoanRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->ensureStatus($request->status, [RequestStatus::Draft, RequestStatus::PendingApproval, RequestStatus::Approved], 'membatalkan peminjaman');
            $wasApproved = $request->status === RequestStatus::Approved;
            $reason = $this->cancelReason($wasApproved, $reason);

            $this->approvals->cancel($request->approvalRequest);
            $request->update(['status' => RequestStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => $reason, 'cancelled_by_user_id' => $user?->id]);
            $this->afterCancel($request, $wasApproved, $user, $reason, route('loans.show', $request));
        });
    }

    // ------------------------------------------------------------ Approval handler (loan & perpanjangan)

    public function onApproved(Model&Approvable $subject, User $decidedBy): void
    {
        if ($subject instanceof LoanExtensionRequest) {
            $this->applyExtension($subject);

            return;
        }

        /** @var AssetLoanRequest $subject */
        $subject->update(['status' => RequestStatus::Approved]);
        $this->notifier->toPermission('loan.checkout', 'Peminjaman siap diserahterimakan',
            "{$subject->request_no} untuk {$subject->borrower->name} telah disetujui.", route('loans.show', $subject), 'bi-box-arrow-up-right');
    }

    public function onRejected(Model&Approvable $subject, User $decidedBy, string $comment): void
    {
        $status = $subject instanceof LoanExtensionRequest ? ExtensionStatus::Rejected : RequestStatus::Rejected;
        $subject->update(['status' => $status]);
    }

    // ------------------------------------------------------------ Operasional

    /** Serah-terima peminjaman: aset → ON_LOAN sampai due date. */
    public function checkout(AssetLoanRequest $request, array $data, User $user): void
    {
        DB::transaction(function () use ($request, $data, $user) {
            $request = AssetLoanRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->ensureStatus($request->status, [RequestStatus::Approved], 'melakukan serah-terima peminjaman');
            $this->ensureActiveEmployee($request->borrower, 'Peminjam');

            $checkedOutAt = Carbon::parse($data['checked_out_at']);
            if ($checkedOutAt->lt($request->start_date->copy()->startOfDay())) {
                throw new BusinessRuleException('Waktu serah-terima tidak boleh sebelum tanggal mulai peminjaman ('.$request->start_date->format('d M Y').').');
            }
            if ($checkedOutAt->isFuture()) {
                throw new BusinessRuleException('Waktu serah-terima tidak boleh di masa depan.');
            }
            $dueAt = $request->due_date->copy()->endOfDay()->startOfSecond();
            if ($dueAt->lte($checkedOutAt)) {
                throw new BusinessRuleException('Waktu serah-terima melewati due date. Ajukan peminjaman baru dengan tanggal yang sesuai.');
            }

            $assets = $this->assets->lockAvailable($request->assets()->pluck('assets.id')->all());
            foreach ($assets as $asset) {
                $loan = AssetLoan::create([
                    'asset_id' => $asset->id,
                    'borrower_employee_id' => $request->borrower_employee_id,
                    'asset_loan_request_id' => $request->id,
                    'usage_location_id' => $request->usage_location_id,
                    'checked_out_at' => $checkedOutAt,
                    'checked_out_by_user_id' => $user->id,
                    'due_at' => $dueAt,
                    'original_due_at' => $dueAt,
                    'condition_out' => $data['condition_out'],
                    'checkout_notes' => $data['checkout_notes'] ?? null,
                    'status' => LoanStatus::CheckedOut,
                ]);
                $this->attachments->store($loan, $data['attachments'] ?? null, 'HANDOVER');

                $this->assets->transition($asset, AssetStatus::OnLoan, AssetEventType::LoanedOut,
                    ['condition' => $data['condition_out']],
                    [
                        'related_employee_id' => $request->borrower_employee_id,
                        'reference_type' => 'asset_loan',
                        'reference_id' => $loan->id,
                        'notes' => "Peminjaman {$request->request_no} s.d. {$dueAt->format('d/m/Y')}",
                        'occurred_at' => $checkedOutAt,
                    ]);
            }

            $request->update(['status' => RequestStatus::Fulfilled, 'fulfilled_at' => now()]);

            $this->notifier->toEmployee($request->borrower, 'Aset pinjaman diserahkan',
                "Aset pada {$request->request_no} wajib dikembalikan paling lambat {$dueAt->translatedFormat('d M Y')}.",
                route('loans.show', $request), 'bi-box-arrow-up-right');
        });
    }

    /** Pengembalian peminjaman; keterlambatan ditandai otomatis. */
    public function returnLoan(AssetLoan $loan, array $data, User $user): void
    {
        DB::transaction(function () use ($loan, $data, $user) {
            $loan = AssetLoan::whereKey($loan->id)->lockForUpdate()->firstOrFail();
            if (! $loan->isActive()) {
                throw new BusinessRuleException('Peminjaman ini sudah ditutup.');
            }
            $returnedAt = Carbon::parse($data['returned_at']);
            if ($returnedAt->lt($loan->checked_out_at)) {
                throw new BusinessRuleException('Waktu kembali tidak boleh sebelum waktu serah-terima.');
            }
            $isLate = $returnedAt->gt($loan->due_at);

            $loan->update([
                'returned_at' => $returnedAt,
                'returned_by_user_id' => $user->id,
                'condition_in' => $data['condition_in'],
                'return_notes' => $data['return_notes'] ?? null,
                'is_late' => $isLate || $loan->is_late,
                'status' => LoanStatus::Returned,
            ]);
            $this->attachments->store($loan, $data['attachments'] ?? null, 'RETURN');

            // Perpanjangan yang masih menunggu tidak relevan lagi
            $loan->extensions()->where('status', ExtensionStatus::PendingApproval->value)->get()
                ->each(function (LoanExtensionRequest $ext) {
                    $this->approvals->cancel($ext->approvalRequest);
                    $ext->update(['status' => ExtensionStatus::Cancelled, 'cancelled_at' => now()]);
                });

            $asset = $loan->asset()->lockForUpdate()->first();
            $this->assets->transition($asset, AssetStatus::from($data['next_status']), AssetEventType::LoanReturned,
                array_filter(['location_id' => $data['location_id'] ?? null, 'condition' => $data['condition_in']]),
                [
                    'related_employee_id' => $loan->borrower_employee_id,
                    'reference_type' => 'asset_loan',
                    'reference_id' => $loan->id,
                    'notes' => trim(($isLate ? 'TERLAMBAT. ' : '').($data['return_notes'] ?? '')),
                    'metadata' => ['late' => $isLate, 'due_at' => $loan->due_at->toDateTimeString()],
                    'occurred_at' => $returnedAt,
                ]);

            if ($isLate) {
                $message = "Aset {$asset->asset_tag} dikembalikan terlambat (due {$loan->due_at->translatedFormat('d M Y')}).";
                $this->notifier->toEmployee($loan->borrower, 'Pengembalian terlambat', $message, route('assets.show', $asset), 'bi-alarm');
                $this->notifier->toPermission('loan.return', 'Pengembalian terlambat', $message, route('assets.show', $asset), 'bi-alarm');
            }
        });
    }

    /** Ajukan perpanjangan due date (melalui approval manager peminjam). */
    public function requestExtension(AssetLoan $loan, array $data, User $user): LoanExtensionRequest
    {
        $employee = $this->actingEmployee($user);
        if ($loan->borrower_employee_id !== $employee->id && ! $user->hasPermission('loan.create_for_others')) {
            throw new BusinessRuleException('Hanya peminjam atau administrator yang dapat mengajukan perpanjangan.');
        }

        return DB::transaction(function () use ($loan, $data, $user, $employee) {
            $loan = AssetLoan::whereKey($loan->id)->lockForUpdate()->firstOrFail();
            if (! $loan->isActive()) {
                throw new BusinessRuleException('Hanya peminjaman aktif yang dapat diperpanjang.');
            }
            if ($loan->extensions()->where('status', ExtensionStatus::PendingApproval->value)->exists()) {
                throw new BusinessRuleException('Masih ada pengajuan perpanjangan yang menunggu approval.');
            }

            $requestedDue = Carbon::parse($data['requested_due_date'])->endOfDay()->startOfSecond();
            if ($requestedDue->lte($loan->due_at)) {
                throw new BusinessRuleException('Due date baru harus setelah due date saat ini.');
            }
            $this->ensureDuration($loan->checked_out_at, $requestedDue);
            $this->assets->ensureNoConflictingRequests([$loan->asset_id], $loan->due_at, $requestedDue, $loan->loanRequest);

            $extension = LoanExtensionRequest::create([
                'request_no' => $this->numbers->requestNumber('EXT'),
                'asset_loan_id' => $loan->id,
                'requester_employee_id' => $employee->id,
                'created_by_user_id' => $user->id,
                'current_due_at' => $loan->due_at,
                'requested_due_at' => $requestedDue,
                'reason' => $data['reason'],
                'status' => ExtensionStatus::PendingApproval,
            ]);
            $this->approvals->submit($extension, $employee, $user);

            return $extension;
        });
    }

    public function cancelExtension(LoanExtensionRequest $extension): void
    {
        DB::transaction(function () use ($extension) {
            $extension = LoanExtensionRequest::whereKey($extension->id)->lockForUpdate()->firstOrFail();
            $this->ensureStatus($extension->status, [ExtensionStatus::PendingApproval], 'membatalkan perpanjangan');
            $this->approvals->cancel($extension->approvalRequest);
            $extension->update(['status' => ExtensionStatus::Cancelled, 'cancelled_at' => now()]);
        });
    }

    /** Dipanggil saat perpanjangan disetujui: cek ulang jadwal lalu perbarui due date. */
    private function applyExtension(LoanExtensionRequest $extension): void
    {
        $loan = AssetLoan::whereKey($extension->asset_loan_id)->lockForUpdate()->firstOrFail();
        if (! $loan->isActive()) {
            throw new BusinessRuleException('Peminjaman sudah ditutup; perpanjangan tidak dapat diterapkan.');
        }
        $this->assets->ensureNoConflictingRequests([$loan->asset_id], $loan->due_at, $extension->requested_due_at, $loan->loanRequest);

        $previousDue = $loan->due_at;
        $loan->update([
            'due_at' => $extension->requested_due_at,
            'status' => $extension->requested_due_at->isFuture() ? LoanStatus::CheckedOut : $loan->status,
        ]);
        $extension->update(['status' => ExtensionStatus::Approved, 'applied_at' => now()]);

        $this->assets->recordEvent($loan->asset, AssetEventType::LoanExtended, [
            'related_employee_id' => $loan->borrower_employee_id,
            'reference_type' => 'loan_extension_request',
            'reference_id' => $extension->id,
            'notes' => "Due date {$previousDue->format('d/m/Y')} → {$extension->requested_due_at->format('d/m/Y')}",
            'metadata' => ['previous_due_at' => $previousDue->toDateTimeString(), 'new_due_at' => $extension->requested_due_at->toDateTimeString()],
        ]);
    }

    /** Tandai peminjaman yang melewati due date sebagai OVERDUE dan kirim notifikasi. */
    public function markOverdue(): int
    {
        $count = 0;
        AssetLoan::query()
            ->where('status', LoanStatus::CheckedOut->value)
            ->where('due_at', '<', now())
            ->with(['asset', 'borrower.user'])
            ->get()
            ->each(function (AssetLoan $candidate) use (&$count) {
                $marked = DB::transaction(function () use ($candidate) {
                    // Validasi ulang dengan row lock: loan bisa saja sudah dikembalikan/diperpanjang sejak dibaca
                    $loan = AssetLoan::whereKey($candidate->id)->lockForUpdate()->first();
                    if (! $loan || $loan->status !== LoanStatus::CheckedOut || ! $loan->due_at->isPast()) {
                        return false;
                    }
                    $loan->setRelations($candidate->getRelations());

                    $loan->update(['status' => LoanStatus::Overdue, 'is_late' => true]);
                    $this->assets->recordEvent($loan->asset, AssetEventType::LoanOverdue, [
                        'related_employee_id' => $loan->borrower_employee_id,
                        'reference_type' => 'asset_loan',
                        'reference_id' => $loan->id,
                        'notes' => "Melewati due date {$loan->due_at->format('d/m/Y H:i')}",
                    ]);
                    $message = "Aset {$loan->asset->asset_tag} melewati batas pengembalian ({$loan->due_at->translatedFormat('d M Y')}).";
                    $this->notifier->toEmployee($loan->borrower, 'Peminjaman terlambat', $message, route('loans.active'), 'bi-alarm');
                    $this->notifier->toPermission('loan.return', 'Peminjaman terlambat', $message, route('loans.active'), 'bi-alarm');

                    return true;
                });
                $count += $marked ? 1 : 0;
            });

        return $count;
    }

    private function ensureDuration(Carbon $start, Carbon $due): void
    {
        $max = (int) Setting::get('loan.max_duration_days', 30);
        if ($max > 0 && $start->copy()->startOfDay()->diffInDays($due->copy()->startOfDay()) > $max) {
            throw new BusinessRuleException("Durasi peminjaman maksimal {$max} hari.");
        }
    }
}
