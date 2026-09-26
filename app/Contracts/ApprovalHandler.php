<?php

namespace App\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Layanan domain yang bereaksi terhadap keputusan approval.
 * Dipanggil di dalam transaksi yang sama dengan keputusan.
 */
interface ApprovalHandler
{
    public function onApproved(Model&Approvable $subject, User $decidedBy): void;

    public function onRejected(Model&Approvable $subject, User $decidedBy, string $comment): void;
}
