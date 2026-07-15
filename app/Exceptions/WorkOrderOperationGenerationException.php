<?php

namespace App\Exceptions;

use Exception;

/**
 * Dilempar ketika Work Order akan digenerate wo_operations-nya, tapi Product
 * terkait belum memiliki routing sama sekali (routing kosong), atau routing
 * memiliki sequence yang tidak valid (duplikat / tidak berurutan).
 *
 * Sesuai FR-01 di docs/prd.md: "Sistem memvalidasi kelengkapan BOM dan Routing
 * sebelum Work Order bisa dibuat" — exception ini adalah safety net terakhir
 * di layer Service, bukan pengganti validasi di form/controller.
 */
class WorkOrderOperationGenerationException extends Exception
{
    //
}