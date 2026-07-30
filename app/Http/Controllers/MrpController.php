<?php

namespace App\Http\Controllers;

use App\Models\MrpRun;
use App\Models\ReorderAlert;
use App\Services\Inventory\MrpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Material;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Illuminate\Validation\ValidationException;

/**
 * MrpController — thin controller untuk Engine 3 (MRP), pola identik
 * ScheduleController/OeeController: validasi inline, delegasi penuh ke
 * Service, TIDAK ADA kalkulasi apapun di sini.
 *
 * Endpoint mengembalikan JSON karena belum ada halaman Vue/Inertia untuk
 * MRP (RopGauge.vue, MrpGrid.vue, AlertBanner.vue belum dibuat — lihat
 * claude.md § Not Started). Sesuai brief sesi ini, hanya endpoint minimal
 * yang dibuat; frontend MRP di luar scope.
 *
 * MrpService::run() sudah final dari sesi lalu dan TIDAK diubah di sini.
 */
class MrpController extends Controller
{
    public function __construct(
        private readonly MrpService $mrp,
    ) {
    }

    /**
     * POST /mrp/run
     *
     * Trigger manual MrpService::run() untuk sebuah Schedule tertentu.
     * Biasanya MRP terpicu otomatis via ScheduleCreated event setelah
     * schedules.run (lihat TriggerMrpRunListener); endpoint ini untuk
     * kebutuhan re-run manual (mis. schedule lama yang belum punya MRP,
     * atau MRP perlu diulang setelah perubahan data material/BOM).
     */
    public function run(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'schedule_id' => ['required', 'integer', 'exists:schedules,id'],
        ]);

        $mrpRun = $this->mrp->run((int) $validated['schedule_id']);

        return response()->json($mrpRun, 201);
    }

    /**
     * GET /mrp/runs/{mrpRun}
     *
     * Lihat hasil grid satu MRP run: seluruh MrpRequirement beserta
     * material terkait, dan Schedule asal run tsb.
     */
    public function show(MrpRun $mrpRun): JsonResponse
    {
        return response()->json(
            $mrpRun->load(['requirements.material', 'schedule'])
        );
    }

    /**
     * GET /mrp/alerts
     *
     * List reorder alerts. Filter opsional via query string ?status=open
     * (open|acknowledged|ordered). TIDAK memicu pembuatan alert baru —
     * itu tanggung jawab CheckReorderAlertsJob (scheduled), bukan
     * controller ini. Murni read-only.
     */
    public function alerts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:open,acknowledged,ordered'],
        ]);

        $alerts = ReorderAlert::query()
            ->with('material')
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->latest()
            ->get();

        return response()->json($alerts);
    }

    /**
     * PATCH /mrp/alerts/{reorderAlert}/status
     *
     * Transisi status reorder alert: open -> acknowledged -> ordered.
     * Otorisasi via ReorderAlertPolicy (hanya admin/PPIC, lihat Policy
     * untuk konteks keputusan). Transisi WAJIB berurutan -- tidak boleh
     * loncat (mis. open -> ordered langsung ditolak) dan tidak ada jalur
     * mundur di v1 (keputusan disengaja, konsisten dengan filosofi
     * Immutability Rules docs/engineering-rules.md § 2 -- kalau salah
     * klik, alert baru akan terbit lagi otomatis via CheckReorderAlertsJob
     * harian daripada membuka jalur mundur yang rawan disalahgunakan).
     *
     * Validasi transisi berurutan adalah business rule sederhana, bukan
     * kalkulasi/formula engineering -- ditulis inline di controller sesuai
     * pengecualian "query/logic sederhana boleh di controller" di
     * docs/engineering-rules.md § 4 (pola sama seperti
     * OeeController::latestSnapshotWithBenchmark()).
     */
    public function updateStatus(Request $request, ReorderAlert $reorderAlert): JsonResponse
    {
        $this->authorize('updateStatus', $reorderAlert);

        $validated = $request->validate([
            'status' => ['required', 'in:acknowledged,ordered'],
        ]);

        $allowedTransitions = [
            'open' => 'acknowledged',
            'acknowledged' => 'ordered',
        ];

        $expectedNext = $allowedTransitions[$reorderAlert->status] ?? null;

        if ($expectedNext === null || $validated['status'] !== $expectedNext) {
            throw ValidationException::withMessages([
                'status' => "Transisi status tidak valid: alert berstatus '{$reorderAlert->status}' hanya boleh diubah ke '{$expectedNext}'.",
            ]);
        }

        $reorderAlert->update(['status' => $validated['status']]);

        return response()->json($reorderAlert->load('material'));
    }

    
    public function dashboard(): InertiaResponse
    {
        $latestMrpRun = MrpRun::query()
        ->with(['requirements.material', 'schedule'])
        ->latest('id')
        ->first();

        $alerts = ReorderAlert::query()
        ->with('material')
        ->where('status', 'open')
        ->latest()
        ->get();
        return Inertia::render('Mrp/Dashboard', [
        'initialAlerts' => $alerts,
        'initialMrpRun' => $latestMrpRun,
            ]);
    }
}