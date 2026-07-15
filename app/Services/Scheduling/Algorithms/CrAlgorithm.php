<?php

namespace App\Services\Scheduling\Algorithms;

use App\Models\WoOperation;
use App\Services\Scheduling\Contracts\SchedulingAlgorithmInterface;
use Carbon\Carbon;

/**
 * CR — Critical Ratio.
 *
 * Score = (due_date - now).inMinutes / total_remaining_processing_time  (ascending)
 *
 * CR < 1.0 → WO sudah kritis / akan terlambat
 * CR = 1.0 → tepat waktu jika dikerjakan sekarang
 * CR > 1.0 → masih ada slack
 *
 * Dipilih operasi dengan CR TERKECIL (paling kritis). Ini adalah rule paling
 * adaptif karena mempertimbangkan baik due date maupun sisa beban kerja WO,
 * tapi butuh dihitung ulang setiap kali ada operasi lain selesai dijadwalkan
 * (karena total_remaining_processing_time berubah).
 *
 * Referensi: docs/scheduling.md § Dispatching Rules > CR, dan § Edge Cases
 * (baris "CR denominator = 0 (semua op selesai) → CR = 0, prioritas tertinggi").
 */
class CrAlgorithm implements SchedulingAlgorithmInterface
{
    public function score(WoOperation $op, Carbon $now, array $remainingByWo): string
    {
        $scale = 6;

        $dueDate = Carbon::parse($op->workOrder->due_date);

        // Selisih due_date - now dalam menit. Bisa negatif jika WO sudah lewat due date
        // (nilai negatif akan menghasilkan CR negatif, yang tetap valid sebagai prioritas
        // tertinggi karena bccomp akan menempatkannya di urutan paling kecil/paling kritis).
        $diffMinutes = (string) $now->diffInMinutes($dueDate, false);

        $workOrderId = $op->work_order_id;

        // total_remaining_processing_time = sum(process + setup) semua operasi WO ini
        // yang BELUM selesai dijadwalkan, dihitung ulang oleh caller (JobShopSchedulerService)
        // sebelum setiap iterasi ranking, lalu di-pass melalui $remainingByWo.
        $totalRemaining = $remainingByWo[$workOrderId] ?? '0';

        // Edge case: denominator = 0 (seluruh operasi WO ini sudah selesai dijadwalkan,
        // hanya operasi ini yang tersisa dan tepat sedang dihitung, atau data anomali).
        // Sesuai docs/scheduling.md: CR = 0 → prioritas tertinggi.
        if (bccomp($totalRemaining, '0', $scale) === 0) {
            return '0.000000';
        }

        return bcdiv($diffMinutes, $totalRemaining, $scale);
    }

    public function code(): string
    {
        return 'cr';
    }
}