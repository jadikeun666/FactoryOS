<?php

namespace App\Services\Scheduling;

use App\Models\Schedule;
use App\Models\WorkOrder;
use Illuminate\Support\Collection;

/**
 * GanttBuilderService
 *
 * Mengubah satu record Schedule (beserta assignments) menjadi struktur array
 * yang siap di-JSON-encode untuk konsumsi Gantt Chart D3.js di frontend.
 *
 * Format output WAJIB mengikuti docs/gantt.md § "Data Format dari Backend".
 *
 * Catatan desain (lihat juga ringkasan asumsi di respons):
 * - is_late pada level Work Order dan pada level assignment dihitung dengan
 *   cara yang berbeda secara sengaja:
 *     - WorkOrder.is_late  → apakah WO tersebut secara keseluruhan akan
 *       selesai setelah due_date-nya (dibandingkan terhadap planned_end
 *       operasi TERAKHIR/paling akhir selesai milik WO tsb dalam schedule
 *       ini). Ini konsisten dengan definisi tardiness di docs/scheduling.md
 *       (tardiness dihitung dari last_op_end vs due_date).
 *     - Assignment.is_late → apakah operasi (assignment) individual ini
 *       berakhir setelah due_date WO induknya. Berguna untuk Gantt supaya
 *       bar yang menyebabkan/berkontribusi pada keterlambatan bisa
 *       di-highlight merah, bukan hanya operasi terakhir.
 */
class GanttBuilderService
{
    /**
     * Build data Gantt Chart dari sebuah Schedule.
     *
     * @param  Schedule  $schedule
     * @return array
     */
    public function build(Schedule $schedule): array
    {
        $schedule->loadMissing([
            'assignments.woOperation.workOrder.product',
            'assignments.workCenter',
        ]);

        $assignmentsCollection = $schedule->assignments;

        $workOrders = $assignmentsCollection
            ->map(fn ($assignment) => $assignment->woOperation->workOrder)
            ->unique('id')
            ->values();

        // last planned_end per work order dalam schedule ini, dipakai untuk
        // menentukan is_late di level Work Order (lihat docblock kelas).
        $lastEndByWorkOrder = $assignmentsCollection
            ->groupBy(fn ($assignment) => $assignment->woOperation->work_order_id)
            ->map(fn (Collection $group) => $group->max('end_at'));

        $workCenters = $assignmentsCollection
            ->map(fn ($assignment) => $assignment->workCenter)
            ->unique('id')
            ->sortBy('id')
            ->values();

        return [
            'schedule' => $this->buildScheduleSummary($schedule),
            'work_centers' => $workCenters
                ->map(fn ($workCenter) => $this->buildWorkCenter($workCenter))
                ->all(),
            'work_orders' => $workOrders
                ->map(fn (WorkOrder $workOrder) => $this->buildWorkOrder(
                    $workOrder,
                    $lastEndByWorkOrder->get($workOrder->id)
                ))
                ->all(),
            'assignments' => $assignmentsCollection
                ->sortBy('start_at')
                ->values()
                ->map(fn ($assignment) => $this->buildAssignment($assignment))
                ->all(),
        ];
    }

    private function buildScheduleSummary(Schedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'algorithm' => $schedule->algorithm,
            'makespan_minutes' => $this->toNumber($schedule->makespan_minutes),
            'total_tardiness_minutes' => $this->toNumber($schedule->total_tardiness_minutes),
            'late_wo_count' => (int) $schedule->late_wo_count,
            'mean_flow_time_minutes' => $this->toNumber($schedule->mean_flow_time_minutes),
            'scheduled_from' => $this->toIso($schedule->scheduled_from),
        ];
    }

    private function buildWorkCenter($workCenter): array
    {
        return [
            'id' => $workCenter->id,
            'name' => $workCenter->name,
            'code' => $workCenter->code,
        ];
    }

    private function buildWorkOrder(WorkOrder $workOrder, ?string $lastPlannedEnd): array
    {
        return [
            'id' => $workOrder->id,
            'name' => $workOrder->name ?? $this->fallbackWorkOrderName($workOrder),
            'product' => $workOrder->product?->name,
            'due_date' => $this->toIso($workOrder->due_date),
            'is_late' => $this->isLate($lastPlannedEnd, $workOrder->due_date),
        ];
    }

    private function buildAssignment($assignment): array
    {
        $woOperation = $assignment->woOperation;
        $workOrder = $woOperation->workOrder;

        return [
            'wo_operation_id' => $assignment->wo_operation_id,
            'work_order_id' => $workOrder->id,
            'work_order_name' => $workOrder->name ?? $this->fallbackWorkOrderName($workOrder),
            'work_center_id' => $assignment->work_center_id,
            'work_center_name' => $assignment->workCenter->name,
            'sequence' => $woOperation->sequence,
            'start_at' => $this->toIso($assignment->start_at),
            'end_at' => $this->toIso($assignment->end_at),
            'duration_minutes' => $this->durationMinutes($assignment->start_at, $assignment->end_at),
            'is_late' => $this->isLate($assignment->end_at, $workOrder->due_date),
        ];
    }

    /**
     * Bandingkan sebuah timestamp planned_end terhadap due_date WO.
     * due_date di database bertipe DATE, jadi dibandingkan sampai akhir
     * hari tersebut (23:59:59) agar operasi yang selesai di hari yang sama
     * dengan due_date tidak dianggap terlambat.
     */
    private function isLate(?string $plannedEnd, $dueDate): bool
    {
        if (! $plannedEnd || ! $dueDate) {
            return false;
        }

        $end = \Illuminate\Support\Carbon::parse($plannedEnd);
        $due = \Illuminate\Support\Carbon::parse($dueDate)->endOfDay();

        return $end->greaterThan($due);
    }

    private function durationMinutes($start, $end): int
    {
        return \Illuminate\Support\Carbon::parse($start)
            ->diffInMinutes(\Illuminate\Support\Carbon::parse($end));
    }

    private function toIso($value): ?string
    {
        if (! $value) {
            return null;
        }

        return \Illuminate\Support\Carbon::parse($value)->toIso8601String();
    }

    /**
     * bcmath-computed metrics disimpan sebagai string/NUMERIC di DB.
     * Untuk output JSON Gantt, dikonversi ke float agar mudah dikonsumsi
     * D3.js / Chart.js di frontend (bukan untuk keputusan produksi kritis,
     * jadi konversi float di titik ini aman — lihat docs/engineering-rules.md).
     */
    private function toNumber($value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    private function fallbackWorkOrderName(WorkOrder $workOrder): string
    {
        return 'WO-'.$workOrder->id;
    }
}