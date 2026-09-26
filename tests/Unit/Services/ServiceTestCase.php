<?php

namespace Tests\Unit\Services;

use App\Enums\ApprovalStatus;
use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\EmploymentStatus;
use App\Models\ApprovalStep;
use App\Models\Asset;
use App\Models\AssetEvent;
use App\Models\Employee;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Basis unit test service domain (database asset_kantorku_test, data demo, tiap test di-rollback).
 * Aset dibuat khusus per test agar tidak berebut row lock dengan test paralel lain.
 */
abstract class ServiceTestCase extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();
    }

    protected function user(string $name): User
    {
        return User::where('email', "{$name}@kantorku.test")->firstOrFail();
    }

    protected function employee(int $id): Employee
    {
        return Employee::findOrFail($id);
    }

    protected function setEmploymentStatus(int $employeeId, EmploymentStatus $status): void
    {
        Employee::whereKey($employeeId)->update(['employment_status' => $status->value]);
    }

    /** Aset uji baru (default kategori FUR tanpa nomor seri, lokasi 1, AVAILABLE). */
    protected function makeAsset(array $attributes = []): Asset
    {
        $tag = 'UT2-'.Str::upper(Str::random(10));

        return Asset::create(array_merge([
            'asset_tag' => $tag,
            'name' => "Aset Uji {$tag}",
            'asset_category_id' => 8,
            'location_id' => 1,
            'status' => AssetStatus::Available,
            'condition' => AssetCondition::Good,
        ], $attributes))->refresh();
    }

    protected function setAssetStatus(Asset $asset, AssetStatus $status): void
    {
        Asset::whereKey($asset->id)->update(['status' => $status->value]);
    }

    protected function pdf(string $name = 'bukti.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 20, 'application/pdf');
    }

    protected function pendingStep(Model $subject): ApprovalStep
    {
        return $subject->fresh()->approvalRequest->steps()
            ->where('status', ApprovalStatus::Pending->value)->firstOrFail();
    }

    protected function approverOf(Model $subject): User
    {
        return User::where('employee_id', $this->pendingStep($subject)->approver_employee_id)->firstOrFail();
    }

    /** Putuskan approval sebagai approver yang dituju. */
    protected function decide(Model $subject, bool $approve, ?string $comment = null): void
    {
        $step = $this->pendingStep($subject);
        $approver = User::where('employee_id', $step->approver_employee_id)->firstOrFail();
        $this->actingAs($approver);
        app(ApprovalService::class)->decide($step, $approver, $approve, $comment ?? ($approve ? 'OK disetujui' : 'Ditolak untuk pengujian'));
    }

    protected function approve(Model $subject): void
    {
        $this->decide($subject, true);
    }

    protected function reject(Model $subject, string $comment = 'Ditolak untuk pengujian'): void
    {
        $this->decide($subject, false, $comment);
    }

    protected function lastEvent(Asset $asset): AssetEvent
    {
        return AssetEvent::where('asset_id', $asset->id)->orderByDesc('id')->firstOrFail();
    }

    protected function eventTypes(Asset $asset): array
    {
        return AssetEvent::where('asset_id', $asset->id)->orderBy('id')->get()
            ->map(fn (AssetEvent $e) => $e->event_type->value)->all();
    }
}
