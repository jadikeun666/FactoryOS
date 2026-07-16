<?php

namespace App\Services\OEE;

use App\Exceptions\InvalidProductionLogException;
use App\Models\OeeSnapshot;
use App\Models\ProductionLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @see docs/oee-formulas.md — formula ISO 22400 (Availability × Performance × Quality)
 * @see docs/engineering-rules.md § 1 (bcmath wajib) dan § 7 (performance cap 1.0)
 *
 * CATATAN: bcmath TIDAK membulatkan, hanya memotong (truncate). Untuk hasil
 * yang match dengan kalkulasi manual matematis biasa, kita bulatkan manual
 * (round half up) di setiap tahap sebelum disimpan ke kolom NUMERIC(8,6).
 */
class OeeCalculatorService
{
    private const SCALE = 6;
    private const INTERNAL_SCALE = 12; // presisi ekstra sebelum dibulatkan ke SCALE

    public function compute(ProductionLog $log): OeeSnapshot
    {
        $planned  = (string) $log->planned_minutes;
        $downtime = (string) $log->downtime_minutes;
        $output   = (string) $log->actual_output;
        $good     = (string) $log->good_output;
        $ict      = (string) $log->ideal_cycle_time_minutes;

        if (bccomp($planned, '0', self::SCALE) === 0) {
            throw new InvalidProductionLogException('Planned minutes tidak boleh 0.');
        }

        $operating = bcsub($planned, $downtime, self::INTERNAL_SCALE);

        if (bccomp($output, '0', self::SCALE) === 0) {
            throw new InvalidProductionLogException('Actual output tidak boleh 0.');
        }

        if (bccomp($operating, '0', self::SCALE) === 0) {
            throw new InvalidProductionLogException('Operating time tidak boleh 0 (downtime = planned minutes).');
        }

        // Availability = (Planned - Downtime) / Planned
        $availability = $this->round(
            bcdiv($operating, $planned, self::INTERNAL_SCALE),
            self::SCALE
        );

        // Performance = (Output × Ideal Cycle Time) / Operating Time, di-cap 1.0
        $theoreticalMax = $this->round(
            bcdiv(bcmul($output, $ict, self::INTERNAL_SCALE), $operating, self::INTERNAL_SCALE),
            self::SCALE
        );
        $performance = bccomp($theoreticalMax, '1.000000', self::SCALE) > 0
            ? '1.000000'
            : $theoreticalMax;

        // Quality = Good Output / Actual Output
        $quality = $this->round(
            bcdiv($good, $output, self::INTERNAL_SCALE),
            self::SCALE
        );

        // OEE = Availability × Performance × Quality (dari nilai yang sudah dibulatkan,
        // konsisten dengan cara docs/oee-formulas.md menyimpan tiap komponen)
        $oee = $this->round(
            bcmul(bcmul($availability, $performance, self::INTERNAL_SCALE), $quality, self::INTERNAL_SCALE),
            self::SCALE
        );

        return OeeSnapshot::updateOrCreate(
            [
                'work_center_id' => $log->work_center_id,
                'log_date'       => $log->log_date,
                'shift_id'       => $log->shift_id,
            ],
            [
                'availability' => $availability,
                'performance'  => $performance,
                'quality'      => $quality,
                'oee'          => $oee,
                'computed_at'  => now(),
            ]
        );
    }

    /**
     * Trend OEE harian per mesin (untuk sparkline chart).
     * Rata-rata semua shift dalam satu tanggal, dihitung dengan bcmath
     * (bukan SQL AVG native) supaya presisi konsisten dengan aturan
     * engineering-rules.md § 1.
     *
     * @return array<int, array{
     *     date: string,
     *     availability: string,
     *     performance: string,
     *     quality: string,
     *     oee: string
     * }>
     */
    public function trendData(int $workCenterId, Carbon $from, Carbon $to): array
    {
        // whereDate() dipakai (bukan whereBetween string tanggal polos) —
        // lihat catatan di DowntimeAnalysisService::paretoDowntime() soal
        // isu perbandingan leksikografis pada kolom ber-cast 'date' di SQLite.
        $snapshots = OeeSnapshot::query()
            ->where('work_center_id', $workCenterId)
            ->whereDate('log_date', '>=', $from->toDateString())
            ->whereDate('log_date', '<=', $to->toDateString())
            ->orderBy('log_date')
            ->get();

        if ($snapshots->isEmpty()) {
            return [];
        }

        // Group per tanggal (bukan per shift) — satu tanggal bisa punya
        // beberapa snapshot (satu per shift).
        $grouped = $snapshots->groupBy(fn (OeeSnapshot $s) => $s->log_date->toDateString());

        $result = [];
        foreach ($grouped as $date => $group) {
            $result[] = [
                'date'         => $date,
                'availability' => $this->averageMetric($group->pluck('availability')),
                'performance'  => $this->averageMetric($group->pluck('performance')),
                'quality'      => $this->averageMetric($group->pluck('quality')),
                'oee'          => $this->averageMetric($group->pluck('oee')),
            ];
        }

        return $result;
    }

    /**
     * Benchmark satu snapshot OEE terhadap target world class (docs/oee-formulas.md
     * § OEE Trend & Benchmark).
     *
     * @return array<string, array{actual: string, world_class: string, gap: string}>
     */
    public function benchmarkVsWorldClass(OeeSnapshot $snapshot): array
    {
        $worldClass = [
            'oee'          => '0.850000',
            'availability' => '0.900000',
            'performance'  => '0.950000',
            'quality'      => '0.999900',
        ];

        $actuals = [
            'oee'          => (string) $snapshot->oee,
            'availability' => (string) $snapshot->availability,
            'performance'  => (string) $snapshot->performance,
            'quality'      => (string) $snapshot->quality,
        ];

        $result = [];
        foreach ($actuals as $key => $actual) {
            $gap = $this->roundSigned(
                bcsub($actual, $worldClass[$key], self::INTERNAL_SCALE),
                self::SCALE
            );

            $result[$key] = [
                'actual'      => $actual,
                'world_class' => $worldClass[$key],
                'gap'         => $gap,
            ];
        }

        return $result;
    }

    /**
     * Rata-rata sekumpulan nilai metrik OEE (string decimal) dengan bcmath.
     */
    private function averageMetric(Collection $values): string
    {
        $sum = '0';
        $count = 0;

        foreach ($values as $value) {
            $sum = bcadd($sum, (string) $value, self::INTERNAL_SCALE);
            $count++;
        }

        return $this->round(bcdiv($sum, (string) $count, self::INTERNAL_SCALE), self::SCALE);
    }

    /**
     * Round half up ke $scale desimal. bcmath native (bcdiv/bcmul/dst) selalu
     * truncate, jadi kita tambahkan 0.5 pada digit ke-(scale+1) sebelum
     * dipotong ke scale — cara standar round half up dengan bcmath.
     *
     * Hanya untuk nilai non-negatif (semua rasio OEE selalu >= 0).
     */
    private function round(string $number, int $scale): string
    {
        $halfStep = '0.' . str_repeat('0', $scale) . '5';

        return bcadd($number, $halfStep, $scale);
    }

    /**
     * Round half up untuk nilai yang BISA negatif (mis. gap benchmark).
     * round() di atas hanya valid untuk non-negatif; helper ini menangani
     * tanda secara terpisah lalu delegasi ke round() yang sama supaya tidak
     * ada duplikasi logika round-half-up.
     */
    private function roundSigned(string $number, int $scale): string
    {
        $negative = str_starts_with($number, '-');
        $abs = $negative ? substr($number, 1) : $number;

        $rounded = $this->round($abs, $scale);

        return $negative ? '-'.$rounded : $rounded;
    }
}