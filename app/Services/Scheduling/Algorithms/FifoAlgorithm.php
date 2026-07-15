<?php

namespace App\Services\Scheduling\Algorithms;

use App\Models\WoOperation;
use App\Services\Scheduling\Contracts\SchedulingAlgorithmInterface;
use Carbon\Carbon;

/**
 * FIFO — First In First Out.
 *
 * Score = work_order.created_at  (ascending)
 *
 * Urutan murni berdasarkan waktu Work Order dibuat. Tidak ada optimasi
 * sama sekali — dipakai sebagai baseline pembanding untuk 3 algoritma lain
 * (SPT, EDD, CR) di tabel perbandingan Scheduling/Compare.vue.
 *
 * Referensi: docs/scheduling.md § Dispatching Rules > FIFO
 */
class FifoAlgorithm implements SchedulingAlgorithmInterface
{
    public function score(WoOperation $op, Carbon $now, array $remainingByWo): string
    {
        // created_at WO dijadikan unix timestamp string agar konsisten dibandingkan
        // dengan bccomp() bersama algoritma lainnya.
        $createdAt = Carbon::parse($op->workOrder->created_at);

        return (string) $createdAt->timestamp;
    }

    public function code(): string
    {
        return 'fifo';
    }
}