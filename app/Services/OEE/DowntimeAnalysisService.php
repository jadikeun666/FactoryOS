<?php

namespace App\Services\OEE;

use App\Models\DowntimeEvent;
use Illuminate\Support\Carbon;

/**
 * DowntimeAnalysisService — domain terpisah dari OeeCalculatorService.
 *
 * OeeCalculatorService menghitung OEE per ProductionLog (satu record).
 * Service ini menganalisis downtime_events secara agregat lintas rentang
 * tanggal (Pareto analysis), sesuai docs/oee-formulas.md § Pareto Analysis
 * Downtime. Dipisah agar tetap konsisten dengan prinsip "satu Service =
 * satu domain tanggung jawab" (docs/engineering-rules.md § 4).
 */
class DowntimeAnalysisService
{
    private const SCALE = 6;

    // Presisi internal lebih tinggi dari SCALE final, supaya round-half-up
    // di akhir tidak kehilangan digit akibat truncation bcmath di langkah
    // pembagian/perkalian sebelumnya (lihat pola bcSqrt di docs/inventory.md
    // yang juga pakai scale+2 untuk internal precision).
    private const INTERNAL_SCALE = self::SCALE + 4;

    /**
     * Pareto analysis downtime: identifikasi "vital few" penyebab downtime
     * dalam rentang tanggal tertentu, opsional difilter per work center.
     *
     * @return array<int, array{
     *     category: string,
     *     total_minutes: string,
     *     percentage: string,
     *     cumulative: string
     * }>
     */
    public function paretoDowntime(Carbon $from, Carbon $to, ?int $workCenterId = null): array
    {
        // whereDate() dipakai (bukan whereBetween dengan string tanggal polos)
        // supaya konsisten lintas driver DB. Kolom log_date di-cast 'date' di
        // model, yang di SQLite (dipakai testing) diserialisasi sebagai
        // 'YYYY-MM-DD 00:00:00'. whereBetween dengan string 'YYYY-MM-DD' polos
        // membandingkan secara leksikografis dan salah mengeksklusikan baris
        // pada tanggal batas atas. whereDate() memakai fungsi DATE() di level
        // SQL sehingga selalu membandingkan tanggal murni.
        $rows = DowntimeEvent::query()
            ->selectRaw('downtime_events.reason_category as category, SUM(downtime_events.duration_minutes) as total_minutes')
            ->join('production_logs', 'production_logs.id', '=', 'downtime_events.production_log_id')
            ->whereDate('production_logs.log_date', '>=', $from->toDateString())
            ->whereDate('production_logs.log_date', '<=', $to->toDateString())
            ->when($workCenterId !== null, fn ($q) => $q->where('production_logs.work_center_id', $workCenterId))
            ->groupBy('downtime_events.reason_category')
            ->orderByDesc('total_minutes')
            ->get();

        // Guard: tidak ada downtime sama sekali dalam rentang ini — kondisi
        // valid, bukan error data.
        if ($rows->isEmpty()) {
            return [];
        }

        // Total keseluruhan = Σ total_minutes, pakai bcmath (bukan array_sum float)
        $grandTotal = '0';
        foreach ($rows as $row) {
            $grandTotal = bcadd($grandTotal, (string) $row->total_minutes, self::INTERNAL_SCALE);
        }

        // Guard: total 0 (jaga-jaga terhadap data duration_minutes = 0 semua)
        if (bccomp($grandTotal, '0', self::INTERNAL_SCALE) === 0) {
            return [];
        }

        $result = [];
        // Akumulasi cumulative dilakukan di presisi tinggi (rawCumulative),
        // supaya galat pembulatan per baris tidak menumpuk (compounding
        // rounding error) di baris-baris berikutnya. Rounding hanya
        // diterapkan saat nilai akan ditampilkan.
        $rawCumulative = '0';

        foreach ($rows as $row) {
            $totalMinutes = (string) $row->total_minutes;

            // Bagi & kali di presisi tinggi dulu, baru round ke scale final —
            // supaya digit di belakang scale final tidak hilang duluan
            // sebelum sempat dibulatkan (bcmath native truncate, bukan round).
            $rawPercentage = bcmul(
                bcdiv($totalMinutes, $grandTotal, self::INTERNAL_SCALE),
                '100',
                self::INTERNAL_SCALE
            );

            $rawCumulative = bcadd($rawCumulative, $rawPercentage, self::INTERNAL_SCALE);

            $result[] = [
                'category'      => $row->category,
                'total_minutes' => $this->round($totalMinutes, 2),
                'percentage'    => $this->round($rawPercentage, self::SCALE),
                'cumulative'    => $this->round($rawCumulative, self::SCALE),
            ];
        }

        return $result;
    }

    /**
     * Round-half-up manual karena bcmath native (bcdiv/bcmul) selalu truncate,
     * bukan round. Pola sama seperti di OeeCalculatorService.
     */
    private function round(string $number, int $scale): string
    {
        $negative = str_starts_with($number, '-');
        $abs = $negative ? substr($number, 1) : $number;

        $rounded = bcadd($abs, '0.' . str_repeat('0', $scale) . '5', $scale);

        return $negative ? '-' . $rounded : $rounded;
    }
}