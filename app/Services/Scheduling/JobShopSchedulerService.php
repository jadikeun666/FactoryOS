<?php

namespace App\Services\Scheduling;

use App\Exceptions\SchedulingException;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\WoOperation;
use App\Models\WorkOrder;
use App\Services\Scheduling\Algorithms\CrAlgorithm;
use App\Services\Scheduling\Algorithms\EddAlgorithm;
use App\Services\Scheduling\Algorithms\FifoAlgorithm;
use App\Services\Scheduling\Algorithms\SptAlgorithm;
use App\Services\Scheduling\Contracts\SchedulingAlgorithmInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * JobShopSchedulerService — Engine 1.
 *
 * Implementasi persis dari pseudocode di docs/scheduling.md § Algoritma Scheduling.
 * Menjalankan satu dispatching rule (SPT/EDD/CR/FIFO) terhadap seluruh
 * wo_operations milik Work Order berstatus draft/scheduled, menghasilkan
 * satu record Schedule (immutable) beserta schedule_assignments-nya.
 */
class JobShopSchedulerService
{
    /**
     * Status Work Order yang dianggap "belum dijadwalkan final" dan boleh
     * diikutkan dalam sebuah run scheduling.
     */
    private const SCHEDULABLE_WO_STATUSES = ['draft', 'scheduled'];

    /** Scale bcmath untuk metrik waktu (menit). */
    private const TIME_SCALE = 2;

    /**
     * Jalankan satu algoritma dispatching dan simpan hasilnya sebagai
     * record Schedule baru (immutable) + schedule_assignments.
     */
    public function run(string $algorithm, Carbon $startFrom): Schedule
    {
        $algorithmService = $this->resolveAlgorithm($algorithm);

        $workOrders = $this->loadSchedulableWorkOrders();

        [$assignments, $scheduledOps] = $this->executeDispatching($workOrders, $algorithmService, $startFrom);

        $metrics = $this->computeMetrics($assignments, $workOrders);

        return DB::transaction(function () use ($algorithmService, $metrics, $startFrom, $assignments) {
            /** @var Schedule $schedule */
            $schedule = Schedule::create([
                'algorithm'               => $algorithmService->code(),
                'makespan_minutes'        => $metrics['makespan_minutes'],
                'total_tardiness_minutes' => $metrics['total_tardiness_minutes'],
                'late_wo_count'           => $metrics['late_wo_count'],
                'mean_flow_time_minutes'  => $metrics['mean_flow_time_minutes'],
                'scheduled_from'          => $startFrom,
                'created_by'              => Auth::id(),
            ]);

            $rows = [];
            foreach ($assignments as $slotIndex => $assignment) {
                $rows[] = [
                    'schedule_id'     => $schedule->id,
                    'wo_operation_id' => $assignment['wo_operation_id'],
                    'work_center_id'  => $assignment['work_center_id'],
                    'start_at'        => $assignment['planned_start'],
                    'end_at'          => $assignment['planned_end'],
                    'slot_index'      => $slotIndex,
                    'created_at'      => now(),
                ];
            }

            // Bulk insert sesuai docs/scheduling.md step 4.
            ScheduleAssignment::insert($rows);

            return $schedule;
        });
    }

    /**
     * Jalankan keempat algoritma sekaligus (SPT, EDD, CR, FIFO) dan kembalikan
     * array [ algorithm_code => Schedule ] untuk ditampilkan sebagai perbandingan
     * di Scheduling/Compare.vue.
     */
    public function compareAll(Carbon $startFrom): array
    {
        $results = [];

        foreach (['spt', 'edd', 'cr', 'fifo'] as $algorithmCode) {
            $results[$algorithmCode] = $this->run($algorithmCode, $startFrom);
        }

        return $results;
    }

    /**
     * Hitung metrik agregat dari hasil assignments.
     *
     * @param array      $assignments Array asosiatif hasil executeDispatching():
     *                                setiap elemen berisi keys wo_operation_id,
     *                                work_order_id, work_center_id, planned_start, planned_end.
     * @param EloquentCollection $workOrders  Koleksi WorkOrder yang ikut dijadwalkan (untuk due_date & release_date).
     */
    public function computeMetrics(array $assignments, EloquentCollection $workOrders): array
    {
        $scale = self::TIME_SCALE;

        if (empty($assignments)) {
            return [
                'makespan_minutes'        => '0.00',
                'total_tardiness_minutes' => '0.00',
                'late_wo_count'           => 0,
                'mean_flow_time_minutes'  => '0.00',
            ];
        }

        // makespan = max(planned_end) di seluruh assignments
        $makespanEnd = collect($assignments)->max(fn ($a) => $a['planned_end']->getTimestamp());
        $makespanStart = collect($assignments)->min(fn ($a) => $a['planned_start']->getTimestamp());
        $makespanMinutes = bcdiv((string) ($makespanEnd - $makespanStart), '60', $scale);

        // Kelompokkan assignment per work_order_id untuk mencari last_end per WO.
        $assignmentsByWo = collect($assignments)->groupBy('work_order_id');

        $totalTardiness = '0.00';
        $totalFlowTime = '0.00';
        $lateWoCount = 0;
        $woCount = 0;

        foreach ($workOrders as $workOrder) {
            $woAssignments = $assignmentsByWo->get($workOrder->id);

            // WO tanpa assignment (mis. tidak punya operasi) dilewati dari perhitungan metrik.
            if (! $woAssignments || $woAssignments->isEmpty()) {
                continue;
            }

            $woCount++;

            /** @var Carbon $lastEnd */
            $lastEnd = $woAssignments->max('planned_end');

            $dueDate = Carbon::parse($workOrder->due_date)->endOfDay();
            $releaseDate = Carbon::parse($workOrder->release_date)->startOfDay();

            // tardiness = max(0, last_end - due_date), dalam menit.
            // Selisih dihitung via timestamp (detik) lalu dikonversi ke menit dengan bcmath
            // supaya presisi terjaga sesuai docs/engineering-rules.md.
            $rawTardinessSeconds = $lastEnd->getTimestamp() - $dueDate->getTimestamp();
            $rawTardinessMinutes = bcdiv((string) $rawTardinessSeconds, '60', $scale);

            if (bccomp($rawTardinessMinutes, '0', $scale) > 0) {
                $tardinessMinutes = $rawTardinessMinutes;
                $lateWoCount++;
            } else {
                $tardinessMinutes = '0.00';
            }

            // flow_time = last_end - release_date, dalam menit
            $flowTimeSeconds = $lastEnd->getTimestamp() - $releaseDate->getTimestamp();
            $flowTimeMinutes = bcdiv((string) $flowTimeSeconds, '60', $scale);

            $totalTardiness = bcadd($totalTardiness, $tardinessMinutes, $scale);
            $totalFlowTime = bcadd($totalFlowTime, $flowTimeMinutes, $scale);
        }

        $meanFlowTime = $woCount > 0
            ? bcdiv($totalFlowTime, (string) $woCount, $scale)
            : '0.00';

        return [
            'makespan_minutes'        => $makespanMinutes,
            'total_tardiness_minutes' => $totalTardiness,
            'late_wo_count'           => $lateWoCount,
            'mean_flow_time_minutes'  => $meanFlowTime,
        ];
    }

    /**
     * Ambil semua WO yang schedulable beserta operasi (urut sequence) dan
     * relasi routing/work_center yang dibutuhkan algoritma.
     */
    private function loadSchedulableWorkOrders(): EloquentCollection
    {
        return WorkOrder::query()
            ->whereIn('status', self::SCHEDULABLE_WO_STATUSES)
            ->with([
                'operations' => fn ($q) => $q->orderBy('sequence'),
                'operations.routing',
                'operations.workCenter',
            ])
            ->get();
    }

    /**
     * Inti algoritma dispatching — persis mengikuti pseudocode
     * docs/scheduling.md § Algoritma Scheduling, langkah 1 & 2.
     *
     * @return array{0: array, 1: array} [assignments[], scheduledOps[]]
     */
    private function executeDispatching(
        EloquentCollection $workOrders,
        SchedulingAlgorithmInterface $algorithmService,
        Carbon $startFrom
    ): array {
        /** @var Collection<int, WoOperation> $allOps */
        $allOps = $workOrders->flatMap(fn (WorkOrder $wo) => $wo->operations);

        if ($allOps->isEmpty()) {
            return [[], []];
        }

        // 1. INISIALISASI
        $machineAvailableAt = [];
        foreach ($allOps as $op) {
            $wcId = $op->work_center_id;
            if (! isset($machineAvailableAt[$wcId])) {
                $machineAvailableAt[$wcId] = $startFrom->copy();
            }
        }

        $jobReadyAt = [];
        foreach ($workOrders as $wo) {
            $releaseDate = Carbon::parse($wo->release_date)->startOfDay();
            $jobReadyAt[$wo->id] = $startFrom->greaterThan($releaseDate) ? $startFrom->copy() : $releaseDate;
        }

        $scheduledOps = []; // op_id => ['start' => Carbon, 'end' => Carbon]
        $assignments = [];

        $pendingOps = $allOps->keyBy('id');

        // Index (work_order_id, sequence) => op_id, dibangun sekali di awal, dipakai
        // untuk mencari prev_op (sequence - 1) pada WO yang sama tanpa perlu menyimpan
        // model WoOperation yang sudah "hilang" dari pendingOps setelah dijadwalkan.
        $opIndexByWoSequence = [];
        foreach ($allOps as $op) {
            $opIndexByWoSequence[$op->work_order_id][$op->sequence] = $op->id;
        }

        // 2. LOOP sampai semua wo_operations terjadwal
        while ($pendingOps->isNotEmpty()) {
            // a. Kumpulkan candidates
            $candidates = $this->collectCandidates($pendingOps, $scheduledOps, $opIndexByWoSequence);

            // b. Jika candidates kosong tapi masih ada operasi pending → error
            if ($candidates->isEmpty()) {
                throw new SchedulingException(
                    'Tidak ada kandidat operasi yang eligible, kemungkinan circular dependency pada routing atau data tidak konsisten.'
                );
            }

            // c. Rank candidates menggunakan dispatching rule.
            //    Untuk CR: hitung total_remaining per WO dari operasi yang BELUM di-scheduled.
            $remainingByWo = $this->computeRemainingByWorkOrder($pendingOps, $scheduledOps);

            $ranked = $candidates
                ->map(function (WoOperation $op) use ($algorithmService, $startFrom, $remainingByWo) {
                    return [
                        'op'    => $op,
                        'score' => $algorithmService->score($op, $startFrom, $remainingByWo),
                    ];
                })
                ->sort(fn ($a, $b) => bccomp($a['score'], $b['score'], 6))
                ->values();

            // d. Ambil candidate teratas
            /** @var WoOperation $chosenOp */
            $chosenOp = $ranked->first()['op'];
            $workCenterId = $chosenOp->work_center_id;
            $workOrderId = $chosenOp->work_order_id;

            $earliestStart = $machineAvailableAt[$workCenterId]->greaterThan($jobReadyAt[$workOrderId])
                ? $machineAvailableAt[$workCenterId]->copy()
                : $jobReadyAt[$workOrderId]->copy();

            $processMinutes = (float) $chosenOp->routing->std_process_time_minutes;
            $setupMinutes = (float) $chosenOp->routing->setup_time_minutes;
            $durationMinutes = $processMinutes + $setupMinutes;

            $plannedStart = $earliestStart->copy();
            // Durasi disimpan dengan presisi detik (durationMinutes bisa mengandung desimal,
            // mis. std_process_time_minutes = 12.5), lalu dibulatkan ke detik terdekat.
            $plannedEnd = $plannedStart->copy()->addSeconds((int) round($durationMinutes * 60));

            // Simpan hasil scheduling operasi ini.
            $scheduledOps[$chosenOp->id] = [
                'start' => $plannedStart,
                'end'   => $plannedEnd,
            ];

            $assignments[] = [
                'wo_operation_id' => $chosenOp->id,
                'work_order_id'   => $workOrderId,
                'work_center_id'  => $workCenterId,
                'planned_start'   => $plannedStart,
                'planned_end'     => $plannedEnd,
            ];

            $machineAvailableAt[$workCenterId] = $plannedEnd->copy();
            $jobReadyAt[$workOrderId] = $plannedEnd->copy();

            // e. Hapus dari pending, ulangi loop.
            $pendingOps->forget($chosenOp->id);
        }

        return [$assignments, $scheduledOps];
    }

    /**
     * Kumpulkan operasi kandidat: sequence == 1 selalu eligible; sequence > 1
     * eligible hanya jika operasi dengan sequence-1 pada WO yang sama sudah
     * ada di $scheduledOps.
     *
     * @param array $opIndexByWoSequence [work_order_id][sequence] => wo_operation_id,
     *                                   dibangun sekali dari seluruh operasi sebelum loop dimulai.
     */
    private function collectCandidates(Collection $pendingOps, array $scheduledOps, array $opIndexByWoSequence): Collection
    {
        return $pendingOps->filter(function (WoOperation $op) use ($scheduledOps, $opIndexByWoSequence) {
            if ($op->sequence === 1) {
                return true;
            }

            $prevOpId = $opIndexByWoSequence[$op->work_order_id][$op->sequence - 1] ?? null;

            // Eligible hanya jika prev_op ada DAN sudah tercatat di scheduled_ops pada run ini.
            return $prevOpId !== null && array_key_exists($prevOpId, $scheduledOps);
        });
    }

    /**
     * Hitung total_remaining_processing_time per work_order untuk keperluan CR.
     * remaining = sum(process_time + setup_time) dari semua operasi WO tsb
     * yang belum ada di $scheduledOps (masih pending).
     *
     * @return array<int, string> [work_order_id => string minutes]
     */
    private function computeRemainingByWorkOrder(Collection $pendingOps, array $scheduledOps): array
    {
        $scale = 6;
        $remaining = [];

        foreach ($pendingOps as $op) {
            /** @var WoOperation $op */
            if (array_key_exists($op->id, $scheduledOps)) {
                continue;
            }

            $duration = bcadd(
                (string) $op->routing->std_process_time_minutes,
                (string) $op->routing->setup_time_minutes,
                $scale
            );

            $woId = $op->work_order_id;
            $remaining[$woId] = bcadd($remaining[$woId] ?? '0', $duration, $scale);
        }

        return $remaining;
    }

    /**
     * Resolve instance algoritma dari kode string ('spt'|'edd'|'cr'|'fifo').
     */
    private function resolveAlgorithm(string $name): SchedulingAlgorithmInterface
    {
        return match (strtolower($name)) {
            'spt'   => new SptAlgorithm(),
            'edd'   => new EddAlgorithm(),
            'cr'    => new CrAlgorithm(),
            'fifo'  => new FifoAlgorithm(),
            default => throw new SchedulingException("Algoritma scheduling tidak dikenal: {$name}"),
        };
    }
}