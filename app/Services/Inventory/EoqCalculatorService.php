<?php

namespace App\Services\Inventory;

use App\Models\InventoryParam;

/**
 * EoqCalculatorService — Engine 3: Inventory Optimizer (MRP-lite).
 * @see docs/inventory.md § EOQ, § Safety Stock & Reorder Point
 * @see docs/engineering-rules.md § 1 (bcmath wajib, scale 6)
 *
 * Semua kalkulasi pakai bcmath (bukan float PHP) sesuai aturan wajib.
 * bcmath tidak punya sqrt native -- diimplementasikan manual via
 * Newton-Raphson (lihat bcSqrt()), pola yang sama seperti yang sudah
 * didokumentasikan di docs/inventory.md.
 *
 * CATATAN ROUNDING: bcmath native (bcdiv/bcmul/bcadd) selalu TRUNCATE,
 * bukan round. Konsisten dengan pola di OeeCalculatorService dan
 * DowntimeAnalysisService, kita pakai INTERNAL_SCALE lebih tinggi dari
 * SCALE final untuk presisi antar-langkah, lalu round-half-up manual di
 * langkah terakhir sebelum nilai disimpan/dikembalikan.
 */
class EoqCalculatorService
{
    private const SCALE = 6;
    private const INTERNAL_SCALE = self::SCALE + 4;

    /**
     * EOQ = √(2 × D × S / H)
     *
     * D = annual_demand, S = ordering_cost, H = holding_cost_per_unit_year
     */
    public function computeEoq(InventoryParam $params): string
    {
        $numerator = bcmul(
            bcmul('2', (string) $params->annual_demand, self::INTERNAL_SCALE),
            (string) $params->ordering_cost,
            self::INTERNAL_SCALE
        );

        $underRoot = bcdiv($numerator, (string) $params->holding_cost_per_unit_year, self::INTERNAL_SCALE);

        return $this->round($this->bcSqrt($underRoot, self::INTERNAL_SCALE), self::SCALE);
    }

    /**
     * Safety Stock = Z × σ_d × √(LT)
     *
     * Z = service_level_z, σ_d = demand_std_dev, LT = lead_time_days
     */
    public function computeSafetyStock(InventoryParam $params): string
    {
        $sqrtLeadTime = $this->bcSqrt((string) $params->lead_time_days, self::INTERNAL_SCALE);

        $result = bcmul(
            bcmul((string) $params->service_level_z, (string) $params->demand_std_dev, self::INTERNAL_SCALE),
            $sqrtLeadTime,
            self::INTERNAL_SCALE
        );

        return $this->round($result, self::SCALE);
    }

    /**
     * ROP = (avg_daily_demand × LT) + Safety Stock
     *
     * avg_daily_demand = annual_demand / 365
     */
    public function computeRop(InventoryParam $params): string
    {
        $avgDailyDemand = bcdiv((string) $params->annual_demand, '365', self::INTERNAL_SCALE);
        $cycleStock = bcmul($avgDailyDemand, (string) $params->lead_time_days, self::INTERNAL_SCALE);

        // Ambil safety stock di presisi internal (bukan hasil yang sudah
        // dibulatkan) supaya tidak ada compounding rounding error sebelum
        // penjumlahan akhir -- pola sama seperti cumulative di
        // DowntimeAnalysisService::paretoDowntime().
        $sqrtLeadTime = $this->bcSqrt((string) $params->lead_time_days, self::INTERNAL_SCALE);
        $safetyStockRaw = bcmul(
            bcmul((string) $params->service_level_z, (string) $params->demand_std_dev, self::INTERNAL_SCALE),
            $sqrtLeadTime,
            self::INTERNAL_SCALE
        );

        return $this->round(bcadd($cycleStock, $safetyStockRaw, self::INTERNAL_SCALE), self::SCALE);
    }

    /**
     * Total Annual Cost pada titik EOQ:
     *   Annual Ordering Cost = (D / EOQ) × S
     *   Annual Holding Cost  = (EOQ / 2) × H
     *   TAC = Annual Ordering Cost + Annual Holding Cost
     *
     * @return array{ordering_cost: string, holding_cost: string, total: string}
     */
    public function computeTotalAnnualCost(InventoryParam $params): array
    {
        $eoqInternal = $this->bcSqrt(
            bcdiv(
                bcmul(bcmul('2', (string) $params->annual_demand, self::INTERNAL_SCALE), (string) $params->ordering_cost, self::INTERNAL_SCALE),
                (string) $params->holding_cost_per_unit_year,
                self::INTERNAL_SCALE
            ),
            self::INTERNAL_SCALE
        );

        $annualOrderingCost = bcmul(
            bcdiv((string) $params->annual_demand, $eoqInternal, self::INTERNAL_SCALE),
            (string) $params->ordering_cost,
            self::INTERNAL_SCALE
        );

        $annualHoldingCost = bcmul(
            bcdiv($eoqInternal, '2', self::INTERNAL_SCALE),
            (string) $params->holding_cost_per_unit_year,
            self::INTERNAL_SCALE
        );

        $total = bcadd($annualOrderingCost, $annualHoldingCost, self::INTERNAL_SCALE);

        return [
            'ordering_cost' => $this->round($annualOrderingCost, self::SCALE),
            'holding_cost' => $this->round($annualHoldingCost, self::SCALE),
            'total' => $this->round($total, self::SCALE),
        ];
    }

    /**
     * Hitung EOQ, Safety Stock, dan ROP sekaligus, lalu simpan ke
     * InventoryParam (kolom eoq, safety_stock, rop, last_computed_at).
     * Dipanggil dari Laravel Scheduler mingguan (docs/architecture.md
     * § Laravel Scheduler) atau manual dari controller.
     */
    public function computeAndSave(InventoryParam $params): InventoryParam
    {
        $params->update([
            'eoq' => $this->computeEoq($params),
            'safety_stock' => $this->computeSafetyStock($params),
            'rop' => $this->computeRop($params),
            'last_computed_at' => now(),
        ]);

        return $params->refresh();
    }

    /**
     * bcmath tidak punya sqrt native -- implementasi Newton-Raphson.
     * x_{n+1} = (x_n + n/x_n) / 2, iterasi sampai konvergen atau maksimal
     * 100 iterasi (pola identik dengan yang didokumentasikan di
     * docs/inventory.md).
     *
     * Guard: n = 0 -> hasil 0 (bukan div by zero), karena secara matematis
     * √0 = 0 dan ini kondisi valid (misal ordering_cost atau annual_demand
     * kebetulan 0 pada data yang belum lengkap).
     */
    private function bcSqrt(string $n, int $scale): string
    {
        if (bccomp($n, '0', $scale) === 0) {
            return '0';
        }

        $x = $n;
        for ($i = 0; $i < 100; $i++) {
            $xNew = bcdiv(bcadd($x, bcdiv($n, $x, $scale + 2), $scale + 2), '2', $scale + 2);
            if (bccomp($xNew, $x, $scale) === 0) {
                break;
            }
            $x = $xNew;
        }

        return bcadd($x, '0', $scale);
    }

    /**
     * Round half up ke $scale desimal. bcmath native selalu truncate,
     * jadi kita tambahkan 0.5 pada digit ke-(scale+1) sebelum dipotong ke
     * scale -- pola identik dengan OeeCalculatorService::round().
     *
     * Hanya untuk nilai non-negatif (semua hasil EOQ/Safety Stock/ROP/TAC
     * selalu >= 0 secara matematis pada data valid).
     */
    private function round(string $number, int $scale): string
    {
        $halfStep = '0.' . str_repeat('0', $scale) . '5';

        return bcadd($number, $halfStep, $scale);
    }
}