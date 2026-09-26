<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan bisnis (status tidak valid, aset tidak tersedia, dsb).
 * Dirender sebagai pesan error (SweetAlert) dan redirect kembali.
 */
class BusinessRuleException extends RuntimeException
{
}
