<?php

namespace App\Contracts;

use App\Enums\ApprovalType;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model permintaan yang diproses melalui approval manager.
 */
interface Approvable
{
    public function approvalType(): ApprovalType;

    /** Karyawan yang atasannya menjadi approver (requester / penerima / peminjam). */
    public function approvalSubjectEmployee(): Employee;

    public function approvalTitle(): string;

    /** Ringkasan dampak untuk approval inbox. @return array<string, string> */
    public function approvalSummary(): array;

    public function approvalUrl(): string;

    public function approvalRequest(): BelongsTo;
}
