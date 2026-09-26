<?php

namespace Tests\Unit\Policies;

use App\Enums\DisposalStatus;
use App\Enums\LoanStatus;
use App\Enums\ProcurementStatus;
use App\Enums\RequestStatus;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Tests\Unit\Concerns\CreatesDomainFixtures;

/** Lampiran hanya boleh diunduh user yang berhak melihat dokumen induknya. */
class AttachmentPolicyTest extends TestCase
{
    use CreatesDomainFixtures, DatabaseTransactions;

    private function attachmentFor(Model $owner): Attachment
    {
        return Attachment::create([
            'attachable_type' => $owner->getMorphClass(),
            'attachable_id' => $owner->getKey(),
            'category' => 'OTHER',
            'original_name' => 'bukti.pdf',
            'stored_path' => 'attachments/x/bukti.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
        ]);
    }

    private function can(User|string $user, Attachment $attachment): bool
    {
        $user = is_string($user) ? $this->user($user) : $user;

        return Gate::forUser($user)->allows('download', $attachment->fresh());
    }

    public function test_lampiran_aset_untuk_pemilik_permission_asset_view(): void
    {
        $attachment = $this->attachmentFor($this->makeAsset());

        foreach (['staff', 'manager.it', 'admin.aset', 'auditor', 'sysadmin'] as $name) {
            $this->assertTrue($this->can($name, $attachment), $name);
        }
        $this->assertFalse($this->can($this->makeUser(null), $attachment), 'user tanpa role');
    }

    public function test_lampiran_yatim_tanpa_dokumen_induk_ditolak(): void
    {
        $attachment = Attachment::create([
            'attachable_type' => 'asset', 'attachable_id' => 999999999, 'original_name' => 'x.pdf',
            'stored_path' => 'attachments/asset/999999999/x.pdf', 'size_bytes' => 1,
        ]);

        $this->assertFalse($this->can('admin.aset', $attachment));
        $this->assertFalse($this->can('auditor', $attachment));
    }

    public function test_lampiran_procurement_mengikuti_hak_lihat_procurement(): void
    {
        $procurement = $this->makeProcurement(5, ProcurementStatus::PendingApproval, $this->user('staff')->id);
        $attachment = $this->attachmentFor($procurement);

        $this->assertTrue($this->can('staff', $attachment), 'pemohon');
        $this->assertTrue($this->can('auditor', $attachment), 'view_all');
        $this->assertFalse($this->can('direktur', $attachment), 'manager tidak terkait');
        $this->assertFalse($this->can($this->makeUser($this->makeEmployee()->id, ['staff']), $attachment), 'staff lain');
    }

    public function test_lampiran_penerimaan_barang_mengikuti_procurement_induknya(): void
    {
        $procurement = $this->makeProcurement(5, ProcurementStatus::PartiallyReceived, $this->user('staff')->id);
        $attachment = $this->attachmentFor($this->makeReceipt($procurement));

        $this->assertTrue($this->can('staff', $attachment));
        $this->assertTrue($this->can('admin.aset', $attachment));
        $this->assertFalse($this->can('direktur', $attachment));
    }

    public function test_lampiran_serah_terima_assignment_mengikuti_permintaan_assignment(): void
    {
        $request = $this->makeAssignmentRequest(3, 5, RequestStatus::Fulfilled, $this->user('admin.aset')->id);
        $attachment = $this->attachmentFor($this->makeAssignment(5, $request));

        $this->assertTrue($this->can('staff', $attachment), 'penerima aset');
        $this->assertTrue($this->can('admin.aset', $attachment));
        $this->assertFalse($this->can('direktur', $attachment));
    }

    public function test_lampiran_assignment_tanpa_permintaan_untuk_view_all_dan_pemegang(): void
    {
        $attachment = $this->attachmentFor($this->makeAssignment(5)); // pemegang: staff (emp 5)

        $this->assertTrue($this->can('admin.aset', $attachment));
        $this->assertTrue($this->can('auditor', $attachment));
        $this->assertTrue($this->can('staff', $attachment), 'Pemegang boleh mengunduh berita acaranya sendiri');
        $this->assertFalse($this->can('manager.it', $attachment));
        $this->assertFalse($this->can('direktur', $attachment));
    }

    public function test_lampiran_assignment_bukan_milik_sendiri_ditolak_tanpa_view_all(): void
    {
        $attachment = $this->attachmentFor($this->makeAssignment(7)); // pemegang: Dimas (tanpa akun)

        $this->assertFalse($this->can('staff', $attachment));
        $this->assertTrue($this->can('auditor', $attachment));
    }

    public function test_lampiran_loan_mengikuti_permintaan_loan(): void
    {
        $request = $this->makeLoanRequest(5, 5, RequestStatus::Fulfilled, $this->user('staff')->id);
        $attachment = $this->attachmentFor($this->makeLoan(5, LoanStatus::CheckedOut, $request));

        $this->assertTrue($this->can('staff', $attachment));
        $this->assertTrue($this->can('auditor', $attachment));
        $this->assertFalse($this->can('direktur', $attachment));
    }

    public function test_lampiran_loan_tanpa_permintaan_untuk_view_all_dan_peminjam(): void
    {
        $attachment = $this->attachmentFor($this->makeLoan(5)); // peminjam: staff (emp 5)

        $this->assertTrue($this->can('admin.aset', $attachment));
        $this->assertTrue($this->can('auditor', $attachment));
        $this->assertTrue($this->can('staff', $attachment), 'Peminjam boleh mengunduh berita acaranya sendiri');
        $this->assertFalse($this->can('manager.it', $attachment));
    }

    public function test_lampiran_disposal_mengikuti_hak_lihat_disposal(): void
    {
        $attachment = $this->attachmentFor($this->makeDisposal(5, DisposalStatus::Completed, $this->user('staff')->id));

        $this->assertTrue($this->can('staff', $attachment));
        $this->assertTrue($this->can('admin.aset', $attachment));
        $this->assertTrue($this->can('auditor', $attachment));
        $this->assertFalse($this->can('manager.it', $attachment));
        $this->assertFalse($this->can('sysadmin', $attachment));
    }
}
