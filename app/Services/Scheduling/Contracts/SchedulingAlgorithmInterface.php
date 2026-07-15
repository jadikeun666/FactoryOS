<?php

namespace App\Services\Scheduling\Contracts;

use App\Models\WoOperation;
use Carbon\Carbon;

/**
 * Kontrak untuk setiap dispatching rule (SPT, EDD, CR, FIFO).
 *
 * Setiap algoritma menghasilkan sebuah "score" berupa string desimal
 * (kompatibel dengan bcmath / bccomp) untuk operasi kandidat tertentu.
 * JobShopSchedulerService akan mengurutkan seluruh kandidat berdasarkan
 * score ini secara ASCENDING (semakin kecil score → semakin prioritas)
 * menggunakan bccomp(), bukan operator perbandingan native PHP,
 * supaya presisi numerik tetap terjaga (lihat docs/engineering-rules.md).
 */
interface SchedulingAlgorithmInterface
{
    /**
     * Hitung score untuk satu wo_operation kandidat.
     *
     * @param WoOperation $op            Operasi kandidat yang akan diberi skor.
     * @param Carbon      $now           Waktu referensi "sekarang" dalam simulasi penjadwalan
     *                                   (bukan wall-clock time, tapi start_time simulasi berjalan).
     * @param array       $remainingByWo Map [work_order_id => string minutes] berisi total sisa
     *                                   waktu proses (process_time + setup_time) dari seluruh
     *                                   operasi yang BELUM selesai dijadwalkan pada WO tersebut.
     *                                   Hanya benar-benar dipakai oleh CrAlgorithm, tapi tetap
     *                                   di-pass ke semua algoritma untuk keseragaman interface.
     *
     * @return string Score dalam bentuk string desimal, siap dibandingkan dengan bccomp().
     *                Semakin kecil nilainya, semakin tinggi prioritas operasi tersebut.
     */
    public function score(WoOperation $op, Carbon $now, array $remainingByWo): string;

    /**
     * Kode singkat algoritma, dipakai sebagai value kolom schedules.algorithm.
     * Contoh: 'spt', 'edd', 'cr', 'fifo'.
     */
    public function code(): string;
}