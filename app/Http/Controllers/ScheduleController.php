<?php

namespace App\Http\Controllers;

use App\Exceptions\ScheduleApplyException;
use App\Http\Requests\ApplyScheduleRequest;
use App\Models\Schedule;
use App\Services\Scheduling\GanttBuilderService;
use App\Services\Scheduling\JobShopSchedulerService;
use App\Services\Scheduling\ScheduleApplierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * ScheduleController — thin controller sesuai docs/architecture.md § Controllers.
 *
 * run() dan compareAll() adalah stub yang mendelegasikan ke
 * JobShopSchedulerService (sudah ada & teruji), disertakan di sini hanya
 * agar struktur controller sesuai spesifikasi di architecture.md.
 * ganttData() dan apply() diimplementasikan penuh.
 */
class ScheduleController extends Controller
{
    public function __construct(
        private readonly JobShopSchedulerService $scheduler,
        private readonly GanttBuilderService $gantt,
        private readonly ScheduleApplierService $applier,
    ) {
    }

    /**
     * POST /schedules/run
     */
    public function run(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'algorithm' => 'required|in:spt,edd,cr,fifo',
            'start_from' => 'nullable|date',
        ]);

        $startFrom = $validated['start_from'] ?? now();

        $this->scheduler->run($validated['algorithm'], \Illuminate\Support\Carbon::parse($startFrom));

        return back()->with('success', 'Schedule berhasil dijalankan.');
    }

    /**
     * POST /schedules/compare-all
     */
    public function compareAll(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_from' => 'nullable|date',
        ]);

        $startFrom = $validated['start_from'] ?? now();

        return response()->json(
            $this->scheduler->compareAll(\Illuminate\Support\Carbon::parse($startFrom))
        );
    }

    /**
     * GET /api/schedules/{schedule}/gantt-data
     */
    public function ganttData(Schedule $schedule): JsonResponse
    {
        return response()->json($this->gantt->build($schedule));
    }

    /**
     * POST /schedules/apply
     *
     * Menerapkan schedule terpilih (hasil compareAll) ke wo_operations dan
     * mentransisikan status Work Order terkait. Dipanggil dari tombol
     * "Terapkan Jadwal" di resources/js/Pages/Schedules/Compare.vue.
     */
    public function apply(ApplyScheduleRequest $request): RedirectResponse
    {
        try {
            $result = $this->applier->apply((int) $request->validated('schedule_id'));
        } catch (ScheduleApplyException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = sprintf(
            'Schedule #%d diterapkan: %d operasi diperbarui, %d Work Order ditransisikan ke scheduled.',
            $result['schedule_id'],
            $result['updated_operations'],
            count($result['transitioned_work_order_ids']),
        );

        if (! empty($result['skipped_operation_ids']) || ! empty($result['skipped_work_order_ids'])) {
            $message .= sprintf(
                ' %d operasi dan %d Work Order dilewati karena sudah berjalan/selesai.',
                count($result['skipped_operation_ids']),
                count($result['skipped_work_order_ids']),
            );
        }

        return back()->with('success', $message);
    }
}