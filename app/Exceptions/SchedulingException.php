<?php

namespace App\Exceptions;

use Exception;

/**
 * Dilempar ketika proses scheduling tidak bisa melanjutkan iterasi karena
 * tidak ada kandidat operasi yang eligible padahal masih ada operasi pending.
 *
 * Penyebab paling umum sesuai docs/scheduling.md § Edge Cases:
 * - Circular dependency pada urutan sequence routing (data error)
 * - WO tanpa routing yang valid lolos validasi sebelumnya
 */
class SchedulingException extends Exception
{
    //
}