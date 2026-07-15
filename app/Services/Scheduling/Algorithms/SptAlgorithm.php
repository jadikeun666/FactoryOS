<?php

namespace App\Services\Scheduling\Algorithms;

use App\Models\WoOperation;
use App\Services\Scheduling\Contracts\SchedulingAlgorithmInterface;
use Carbon\Carbon;

/**
 * SPT — Shortest Processing Time.
 *
 * Score = std_process_time_minutes + setup_time_minutes  (ascending)
 *
 * Dipilih operasi dengan total waktu proses (proses + setup) TERPENDEK
 * di mesin yang sedang idle. Bagus untuk minimize average flow time dan
 * minimize WIP, tapi berisiko starvation untuk WO dengan waktu proses panjang.
 *
 * Referensi: docs/scheduling.md § Dispatching Rules > SPT
 */
class SptAlgorithm implements SchedulingAlgorithmInterface
{
    public function score(WoOperation $op, Carbon $now, array $remainingByWo): string
    {
        $scale = 6;

        // Ambil std_process_time_minutes & setup_time_minutes dari routing terkait operasi ini.
        $processTime = (string) $op->routing->std_process_time_minutes;
        $setupTime   = (string) $op->routing->setup_time_minutes;

        // Score SPT murni penjumlahan waktu proses + setup, tidak bergantung waktu sekarang
        // maupun sisa beban kerja WO lain.
        return bcadd($processTime, $setupTime, $scale);
    }

    public function code(): string
    {
        return 'spt';
    }
}