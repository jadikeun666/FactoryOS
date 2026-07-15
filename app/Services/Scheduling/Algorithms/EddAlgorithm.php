<?php

namespace App\Services\Scheduling\Algorithms;

use App\Models\WoOperation;
use App\Services\Scheduling\Contracts\SchedulingAlgorithmInterface;
use Carbon\Carbon;

/**
 * EDD — Earliest Due Date.
 *
 * Score = work_order.due_date  (ascending)
 *
 * Dipilih operasi dari Work Order dengan due date paling awal, terlepas dari
 * mesin mana operasi tersebut berada atau berapa lama waktu prosesnya.
 * Bagus untuk minimize maximum lateness, tapi tidak mempertimbangkan sisa
 * waktu proses sehingga WO "kecil" (proses cepat) bisa saja tetap terlambat.
 *
 * Referensi: docs/scheduling.md § Dispatching Rules > EDD
 */
class EddAlgorithm implements SchedulingAlgorithmInterface
{
    public function score(WoOperation $op, Carbon $now, array $remainingByWo): string
    {
        // due_date adalah kolom DATE (tanpa jam). Kita konversi ke Carbon lalu
        // ambil unix timestamp sebagai representasi numerik string agar bisa
        // dibandingkan dengan bccomp() secara konsisten dengan algoritma lain.
        $dueDate = Carbon::parse($op->workOrder->due_date);

        return (string) $dueDate->timestamp;
    }

    public function code(): string
    {
        return 'edd';
    }
}