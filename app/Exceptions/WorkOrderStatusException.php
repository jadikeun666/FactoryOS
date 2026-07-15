<?php

namespace App\Exceptions;

use Exception;

/**
 * Dilempar ketika ada percobaan transisi status Work Order yang tidak valid
 * (mis. draft → done langsung, atau menghapus WO yang sudah in_progress/done).
 *
 * Alur status yang valid (docs/database.md § work_orders):
 * draft → scheduled → in_progress → done
 *                                 → late (bisa terjadi dari scheduled/in_progress)
 */
class WorkOrderStatusException extends Exception
{
    //
}