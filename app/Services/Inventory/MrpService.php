<?php

namespace App\Services\Inventory;

use App\Models\Inventory;
use App\Models\InventoryParam;
use App\Models\InventoryTransaction;
use App\Models\Material;
use App\Models\MrpRequirement;
use App\Models\MrpRun;
use App\Models\ReorderAlert;
use App\Models\Schedule;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * MrpService — Engine 3: Material Requirements Planning (MRP-lite).
 * @see docs/inventory.md § MRP
 * @see docs/engineering-rules.md § 1 (bcmath wajib), § 9 (urutan Schedule -> MRP)
 *
 * bcmath scale 6 konsisten dengan EoqCalculatorService/OeeCalculatorService.
 * bcmath native selalu truncate -> round-half-up manual di titik akhir
 * setiap kalkulasi (pola identik dengan service lain, lihat round()/
 * roundSigned() di bawah -- disalin persis dari pola
 * OeeCalculatorService::round()/roundSigned()).
 *
 * CATATAN SKEMA: tabel mrp_requirements hanya punya satu kolom tanggal
 * (period_date), tidak ada kolom terpisah untuk "tanggal rilis order"
 * (period_date - lead_time_days seperti dijelaskan di docs/inventory.md
 * § MRP backward scheduling). Planned_order_release DISIMPAN pada baris
 * period_date yang sama dengan periode kebutuhan (need-date), bukan pada
 * baris tanggal rilis terpisah -- migration final tidak diubah sesi ini.
 * Tanggal rilis tetap dihitung dan dikembalikan di computeRequirements()
 * untuk keperluan pemakai lain (mis. alert/logging), lihat key
 * 'release_date' pada setiap baris hasil.
 *
 * CATATAN SCHEDULED_RECEIPTS: sesuai docs/inventory.md, diambil dari
 * inventory_transactions type='in'. Skema inventory_transactions bersifat
 * historical-immutable (hanya created_at, tidak ada kolom "tanggal PO akan
 * datang"), dan prd.md mencatat modul Purchase Order di luar scope v1.
 * Query tetap diimplementasikan apa adanya terhadap created_at -- pada v1
 * hasilnya secara alami akan 0 untuk periode masa depan karena belum ada
 * modul yang mengisi transaksi bertanggal maju. Tidak di-hardcode.
 */
class MrpService
{
    private const SCALE = 6;
    private const INTERNAL_SCALE = self::SCALE + 4;

    public function __construct(
        private readonly EoqCalculatorService $eoq,
    ) {
    }

    /**
     * Jalankan MRP untuk satu Schedule: BOM explosion seluruh Work Order
     * dalam schedule tsb, lalu netting period-by-period per material.
     */
    public function run(int $scheduleId): MrpRun
    {
        $schedule = Schedule::query()
            ->with([
                'assignments.woOperation.workOrder.product.billOfMaterials',
            ])
            ->findOrFail($scheduleId);

        // Kelompokkan assignment per Work Order, cari planned_end dari
        // OPERASI TERAKHIR (end_at maksimum) sebagai periode kebutuhan
        // material WO tsb -- konsisten dengan "operasi terakhir jatuh pada
        // periode t" di docs/inventory.md § Logic Period-by-Period.
        $lastEndByWorkOrder = $schedule->assignments
            ->groupBy(fn ($a) => $a->woOperation->work_order_id)
            ->map(fn (Collection $group) => $group->max('end_at'));

        // Gross requirement gabungan lintas semua WO dalam schedule ini:
        // [material_id => [ 'Y-m-d' => qty_string ]]
        $grossByMaterial = [];

        foreach ($lastEndByWorkOrder as $workOrderId => $lastEnd) {
            /** @var WorkOrder $workOrder */
            $workOrder = $schedule->assignments
                ->firstWhere(fn ($a) => $a->woOperation->work_order_id === $workOrderId)
                ->woOperation->workOrder;

            $exploded = $this->explodeBom($workOrder, Carbon::parse($lastEnd));

            foreach ($exploded as $materialId => $dateQtyMap) {
                foreach ($dateQtyMap as $date => $qty) {
                    $grossByMaterial[$materialId][$date] = bcadd(
                        $grossByMaterial[$materialId][$date] ?? '0',
                        $qty,
                        self::INTERNAL_SCALE
                    );
                }
            }
        }

        return DB::transaction(function () use ($scheduleId, $grossByMaterial) {
            $mrpRun = MrpRun::create([
                'schedule_id' => $scheduleId,
                'computed_at' => now(),
                'created_by'  => Auth::id(),
            ]);

            $rows = [];
            foreach ($grossByMaterial as $materialId => $grossReqs) {
                $material = Material::findOrFail($materialId);
                $requirements = $this->computeRequirements($material, $grossReqs, now());

                foreach ($requirements as $req) {
                    $rows[] = [
                        'mrp_run_id'            => $mrpRun->id,
                        'material_id'           => $materialId,
                        'period_date'           => $req['period_date'],
                        'gross_requirement'     => $req['gross_requirement'],
                        'scheduled_receipts'    => $req['scheduled_receipts'],
                        'projected_on_hand'     => $req['projected_on_hand'],
                        'net_requirement'       => $req['net_requirement'],
                        'planned_order_release' => $req['planned_order_release'],
                        'created_at'            => now(),
                    ];
                }
            }

            if (! empty($rows)) {
                MrpRequirement::insert($rows);
            }

            return $mrpRun->fresh('requirements');
        });
    }

    /**
     * BOM Explosion untuk satu Work Order: kalikan qty WO dengan
     * qty_per_unit tiap material di BOM produk WO tsb, ditempatkan pada
     * periode = tanggal operasi terakhir WO ($dueDate, sesuai parameter).
     *
     * @return array<int, array<string, string>> [material_id => ['Y-m-d' => qty_string]]
     */
    public function explodeBom(WorkOrder $wo, Carbon $dueDate): array
    {
        $periodKey = $dueDate->toDateString();
        $result = [];

        foreach ($wo->product->billOfMaterials as $bomLine) {
            $requirement = bcmul(
                (string) $wo->qty,
                (string) $bomLine->qty_per_unit,
                self::INTERNAL_SCALE
            );

            $result[$bomLine->material_id][$periodKey] = bcadd(
                $result[$bomLine->material_id][$periodKey] ?? '0',
                $requirement,
                self::INTERNAL_SCALE
            );
        }

        return $result;
    }

    /**
     * Netting period-by-period untuk satu material, sesuai
     * docs/inventory.md § Logic Period-by-Period (Backward Scheduling).
     *
     * Periode yang dihitung adalah tanggal-tanggal yang benar-benar punya
     * gross requirement (diurutkan ascending), bukan grid kalender tetap --
     * on-hand awal diambil dari inventory.qty_on_hand material ini.
     *
     * @param array<string, string> $grossReqs ['Y-m-d' => qty_string]
     * @return array<int, array{
     *   period_date: string, gross_requirement: string, scheduled_receipts: string,
     *   projected_on_hand: string, net_requirement: string, planned_order_release: string,
     *   release_date: string|null
     * }>
     */
    public function computeRequirements(Material $material, array $grossReqs, Carbon $from): array
    {
        $inventoryParam = $material->inventoryParam;
        $leadTimeDays = $inventoryParam?->lead_time_days ?? 0;

        $onHand = $material->inventory?->qty_on_hand ?? '0';
        $projectedOnHand = (string) $onHand;

        $periods = collect($grossReqs)->sortKeys();
        $rows = [];

        foreach ($periods as $dateKey => $grossRequirement) {
            $periodDate = Carbon::parse($dateKey);

            $scheduledReceipts = $this->getScheduledReceipts($material->id, $periodDate);

            // Net_Requirement(t) = max(0, GrossReq(t) - ProjOnHand(t-1) - ScheduledReceipts(t))
            $netRaw = bcsub(
                bcsub($grossRequirement, $projectedOnHand, self::INTERNAL_SCALE),
                $scheduledReceipts,
                self::INTERNAL_SCALE
            );
            $netRequirement = bccomp($netRaw, '0', self::INTERNAL_SCALE) > 0 ? $netRaw : '0';

            // Projected_On_Hand(t) = ProjOnHand(t-1) + ScheduledReceipts(t) - GrossReq(t)
            $projectedOnHandAfter = bcsub(
                bcadd($projectedOnHand, $scheduledReceipts, self::INTERNAL_SCALE),
                $grossRequirement,
                self::INTERNAL_SCALE
            );

            $plannedOrderRelease = '0';
            $releaseDate = null;

            if (bccomp($netRequirement, '0', self::INTERNAL_SCALE) > 0) {
                $plannedOrderRelease = $this->roundUpToEoq($material, $netRequirement);
                $releaseDate = $periodDate->copy()->subDays($leadTimeDays)->toDateString();
            }

            $rows[] = [
                'period_date'            => $periodDate->toDateString(),
                'gross_requirement'      => $this->round($grossRequirement),
                'scheduled_receipts'     => $this->round($scheduledReceipts),
                'projected_on_hand'      => $this->roundSigned($projectedOnHandAfter, self::SCALE),
                'net_requirement'        => $this->round($netRequirement),
                'planned_order_release'  => $this->round($plannedOrderRelease),
                'release_date'           => $releaseDate,
            ];

            // Projected on-hand periode ini jadi ProjOnHand(t-1) periode berikutnya.
            // Pakai nilai presisi internal (bukan yang sudah dibulatkan) supaya
            // tidak ada compounding rounding error antar-periode.
            $projectedOnHand = $projectedOnHandAfter;
        }

        return $rows;
    }

    /**
     * Scan seluruh material yang punya InventoryParam, bandingkan
     * qty_on_hand + qty_on_order terhadap ROP. Buat ReorderAlert baru
     * (status 'open') jika stok <= ROP dan belum ada alert open untuk
     * material tsb. Sesuai docs/inventory.md § Reorder Alert Logic.
     */
    public function checkReorderAlerts(): Collection
    {
        $created = collect();

        InventoryParam::query()
            ->whereNotNull('rop')
            ->with(['material.inventory'])
            ->each(function (InventoryParam $param) use (&$created) {
                $material = $param->material;
                $inventory = $material->inventory;

                $currentQty = bcadd(
                    (string) ($inventory->qty_on_hand ?? '0'),
                    (string) ($inventory->qty_on_order ?? '0'),
                    self::SCALE
                );

                if (bccomp($currentQty, (string) $param->rop, self::SCALE) > 0) {
                    return;
                }

                $hasOpenAlert = ReorderAlert::query()
                    ->where('material_id', $material->id)
                    ->where('status', 'open')
                    ->exists();

                if ($hasOpenAlert) {
                    return;
                }

                $alert = ReorderAlert::create([
                    'material_id' => $material->id,
                    'current_qty' => $currentQty,
                    'rop_qty'     => $param->rop,
                    'eoq_qty'     => $param->eoq ?? $this->eoq->computeEoq($param),
                    'status'      => 'open',
                ]);

                $created->push($alert);
            });

        return $created;
    }

    /**
     * Scheduled_Receipts(t) = jumlah inventory_transactions type='in'
     * yang created_at jatuh pada tanggal $date. WAJIB whereDate() (bukan
     * whereBetween string) sesuai claude.md § Catatan Teknis Penting,
     * supaya konsisten lintas PostgreSQL & SQLite (testing).
     */
    private function getScheduledReceipts(int $materialId, Carbon $date): string
    {
        $sum = InventoryTransaction::query()
            ->where('material_id', $materialId)
            ->where('type', 'in')
            ->whereDate('created_at', $date->toDateString())
            ->sum('qty');

        return bcadd((string) $sum, '0', self::INTERNAL_SCALE);
    }

    /**
     * Bulatkan net requirement ke atas ke kelipatan EOQ material
     * (docs/inventory.md: "qty = roundUpToEoq(Net_Requirement(t))").
     *
     * Guard: jika material tidak punya InventoryParam atau EOQ tidak
     * bisa dihitung (mis. ordering_cost/holding_cost belum diisi),
     * fallback ke net requirement itu sendiri (order tepat sejumlah
     * kebutuhan, tanpa pembulatan EOQ) -- lebih aman daripada gagal total.
     */
    private function roundUpToEoq(Material $material, string $netRequirement): string
    {
        $param = $material->inventoryParam;

        if (! $param) {
            return $this->round($netRequirement);
        }

        $eoq = (string) ($param->eoq ?? $this->eoq->computeEoq($param));

        if (bccomp($eoq, '0', self::INTERNAL_SCALE) <= 0) {
            return $this->round($netRequirement);
        }

        // ceil(netRequirement / eoq) * eoq via bcmath (tidak ada bcceil native).
        $divided = bcdiv($netRequirement, $eoq, self::INTERNAL_SCALE);
        $truncated = bcadd($divided, '0', 0); // truncate ke integer (scale 0 = floor untuk positif)

        if (bccomp($divided, $truncated, self::INTERNAL_SCALE) > 0) {
            $truncated = bcadd($truncated, '1', 0);
        }

        return $this->round(bcmul($truncated, $eoq, self::INTERNAL_SCALE));
    }

    /**
     * Round half up ke SCALE desimal. Pola identik dengan
     * OeeCalculatorService::round() -- bcmath native selalu truncate,
     * jadi tambahkan 0.5 pada digit ke-(scale+1) sebelum dipotong ke scale.
     * Hanya untuk nilai non-negatif -- gunakan roundSigned() untuk nilai
     * yang berpotensi negatif (mis. projected_on_hand saat stok defisit).
     */
    private function round(string $number, ?int $scale = null): string
    {
        $scale ??= self::SCALE;

        $halfStep = '0.' . str_repeat('0', $scale) . '5';

        return bcadd($number, $halfStep, $scale);
    }

    /**
     * Round half up yang aware terhadap nilai negatif. Pola identik dengan
     * OeeCalculatorService::roundSigned() -- deteksi tanda via string
     * (bukan bccomp) lalu delegasikan ke round() untuk nilai absolutnya.
     */
    private function roundSigned(string $number, int $scale): string
    {
        $negative = str_starts_with($number, '-');
        $abs = $negative ? substr($number, 1) : $number;

        $rounded = $this->round($abs, $scale);

        return $negative ? '-' . $rounded : $rounded;
    }
}