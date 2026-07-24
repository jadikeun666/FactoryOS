<?php

namespace App\Services\Dashboard;

use App\Models\Material;
use App\Models\OeeSnapshot;
use App\Models\ReorderAlert;
use App\Models\Schedule;
use App\Models\WorkOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * DashboardKpiService — agregasi KPI ringkasan lintas 3 engine (FR-10, docs/prd.md).
 *
 * PENTING: service ini TIDAK menghitung ulang formula engineering apapun
 * (OEE/EOQ/Safety Stock/Scheduling metrics semua sudah final di service
 * masing-masing engine). Service ini murni membaca/agregasi dari data yang
 * SUDAH dihitung dan tersimpan (Schedule, OeeSnapshot, ReorderAlert,
 * Inventory/InventoryParam) — tidak ada logic engineering baru di sini.
 *
 * Dibuat sebagai service terpisah (bukan logic langsung di
 * DashboardController) supaya konsisten dengan prinsip Service Layer di
 * docs/architecture.md, dan supaya tidak perlu memanggil controller lain
 * (InventoryController) dari controller — pola yang dilarang di
 * docs/engineering-rules.md § 4.
 */
class DashboardKpiService
{
    private const OEE_SCALE = 6;

    /** Status WO yang masih dianggap "aktif" (belum selesai). */
    private const ACTIVE_WO_STATUSES = ['draft', 'scheduled', 'in_progress'];

    /**
     * Engine 1 — Job Shop Scheduler.
     *
     * @return array{
     *     wo_active_count: int,
     *     wo_late_count: int,
     *     active_schedule: array{id: int, algorithm: string, makespan_minutes: string, scheduled_from: string}|null
     * }
     */
    public function engine1Summary(): array
    {
        $woActiveCount = WorkOrder::query()
            ->whereIn('status', self::ACTIVE_WO_STATUSES)
            ->count();

        // WO terlambat: due_date sudah lewat tapi belum selesai (bukan 'done').
        // Pakai whereDate() sesuai aturan engineering-rules.md, bukan whereBetween string.
        $woLateCount = WorkOrder::query()
            ->where('status', '!=', 'done')
            ->whereDate('due_date', '<', Carbon::today())
            ->count();

        // Schedule immutable, tidak ada status "active" eksplisit di skema —
        // yang paling relevan ditampilkan adalah run terbaru (docs/database.md:
        // "Setiap run algoritma = record baru", tidak ada update).
        $latestSchedule = Schedule::query()->latest('created_at')->first();

        return [
            'wo_active_count' => $woActiveCount,
            'wo_late_count'   => $woLateCount,
            'active_schedule' => $latestSchedule ? [
                'id'               => $latestSchedule->id,
                'algorithm'        => $latestSchedule->algorithm,
                'makespan_minutes' => (string) $latestSchedule->makespan_minutes,
                'scheduled_from'   => $latestSchedule->scheduled_from->toIso8601String(),
            ] : null,
        ];
    }

    /**
     * Engine 2 — OEE & Downtime.
     *
     * @return array{
     *     avg_oee_today: string|null,
     *     lowest_oee_work_center: array{work_center_id: int, name: string, oee: string}|null
     * }
     */
    public function engine2Summary(): array
    {
        $todaySnapshots = OeeSnapshot::query()
            ->whereDate('log_date', Carbon::today())
            ->with('workCenter')
            ->get();

        if ($todaySnapshots->isEmpty()) {
            return [
                'avg_oee_today'          => null,
                'lowest_oee_work_center' => null,
            ];
        }

        $avgOeeToday = $this->averageOee($todaySnapshots->pluck('oee'));

        // Jika satu mesin punya beberapa shift hari ini, ambil rata-rata OEE
        // per mesin dulu sebelum mencari yang terendah (adil antar mesin,
        // bukan cuma shift dengan OEE terendah dari satu mesin yang sama).
        $lowest = $todaySnapshots
            ->groupBy('work_center_id')
            ->map(function (Collection $group) {
                /** @var OeeSnapshot $first */
                $first = $group->first();

                return [
                    'work_center_id' => $first->work_center_id,
                    'name'           => $first->workCenter->name,
                    'oee'            => $this->averageOee($group->pluck('oee')),
                ];
            })
            ->sort(fn ($a, $b) => bccomp($a['oee'], $b['oee'], self::OEE_SCALE))
            ->first();

        return [
            'avg_oee_today'          => $avgOeeToday,
            'lowest_oee_work_center' => $lowest,
        ];
    }

    /**
     * Engine 3 — Inventory Optimizer.
     *
     * @return array{
     *     open_alert_count: int,
     *     critical_stock_count: int
     * }
     */
    public function engine3Summary(): array
    {
        $openAlertCount = ReorderAlert::query()
            ->where('status', 'open')
            ->count();

        // "Material dengan stok kritis" = qty_on_hand + qty_on_order <= rop,
        // logic sama persis dengan yang dipakai CheckReorderAlertsJob/MrpService
        // (docs/inventory.md § Reorder Alert Logic), tapi di sini murni HITUNG
        // ULANG UNTUK TAMPILAN (bukan membuat/mengubah ReorderAlert apapun) —
        // supaya angka di dashboard selalu real-time, tidak menunggu job
        // terjadwal jam 06:00 berikutnya.
        $criticalStockCount = Material::query()
            ->whereHas('inventory')
            ->whereHas('inventoryParam')
            ->with(['inventory', 'inventoryParam'])
            ->get()
            ->filter(function (Material $material) {
                $currentQty = bcadd(
                    (string) $material->inventory->qty_on_hand,
                    (string) $material->inventory->qty_on_order,
                    4
                );

                return bccomp($currentQty, (string) $material->inventoryParam->rop, 4) <= 0;
            })
            ->count();

        return [
            'open_alert_count'     => $openAlertCount,
            'critical_stock_count' => $criticalStockCount,
        ];
    }

    /**
     * Rata-rata kolom 'oee' (decimal:6 dari model) dengan bcmath, konsisten
     * dengan pola averageMetric() di OeeCalculatorService (tidak diduplikasi
     * import, cukup ditulis ulang sesederhana ini karena scope-nya cuma
     * agregasi tampilan dashboard, bukan kalkulasi formula OEE itu sendiri).
     */
    private function averageOee(Collection $oeeValues): string
    {
        $sum = '0';
        $count = 0;

        foreach ($oeeValues as $oee) {
            $sum = bcadd($sum, (string) $oee, 12);
            $count++;
        }

        return bcdiv($sum, (string) $count, self::OEE_SCALE);
    }
}