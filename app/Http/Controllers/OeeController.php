<?php

namespace App\Http\Controllers;

use App\Models\OeeSnapshot;
use App\Models\WorkCenter;
use App\Services\OEE\DowntimeAnalysisService;
use App\Services\OEE\OeeCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * OeeController — thin controller sesuai docs/architecture.md § Controllers.
 *
 * Mengikuti pola nyata ScheduleController (bukan Form Request terpisah
 * seperti "RunScheduleRequest" yang disebut di docs tapi tidak pernah
 * diimplementasikan) — validasi query params inline via $request->validate().
 *
 * Tidak ada kalkulasi apapun di sini. Semua delegasi ke OeeCalculatorService
 * dan DowntimeAnalysisService (keduanya sudah final & teruji — TIDAK diubah).
 */
class OeeController extends Controller
{
    public function __construct(
        private readonly OeeCalculatorService $calculator,
        private readonly DowntimeAnalysisService $downtime,
    ) {
    }

    /**
     * GET /oee/dashboard
     *
     * Halaman utama OEE Dashboard (US-08, US-09, FR-05). Merender data
     * awal untuk satu work center (default: work center aktif pertama,
     * atau ?work_center_id= dari query string), rentang 30 hari terakhir.
     * OeeGauge.vue mengambil live update selanjutnya via Echo, ParetoChart
     * dan trend chart fetch ulang via endpoint JSON di bawah saat user
     * ganti filter tanggal/mesin tanpa reload halaman.
     */
    public function dashboard(Request $request): Response
    {
        $validated = $request->validate([
            'work_center_id' => 'nullable|integer|exists:work_centers,id',
        ]);

        $workCenters = WorkCenter::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $workCenterId = $validated['work_center_id'] ?? $workCenters->first()?->id;

        $to = now();
        $from = now()->subDays(30);

        $latestSnapshot = $workCenterId
            ? OeeSnapshot::query()
                ->where('work_center_id', $workCenterId)
                ->orderByDesc('computed_at')
                ->first()
            : null;

        return Inertia::render('OEE/Dashboard', [
            'workCenters' => $workCenters,
            'selectedWorkCenterId' => $workCenterId,
            'initialSnapshot' => $latestSnapshot,
            'initialTrend' => $workCenterId
                ? $this->calculator->trendData($workCenterId, $from, $to)
                : [],
            'initialPareto' => $this->downtime->paretoDowntime($from, $to, $workCenterId),
            'initialBenchmark' => $latestSnapshot
                ? $this->calculator->benchmarkVsWorldClass($latestSnapshot)
                : null,
            'dateRange' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ]);
    }

    /**
     * GET /api/oee/pareto
     *
     * Dipanggil dari ParetoChart.vue saat user ganti filter tanggal/mesin.
     */
    public function pareto(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'work_center_id' => 'nullable|integer|exists:work_centers,id',
        ]);

        return response()->json(
            $this->downtime->paretoDowntime(
                Carbon::parse($validated['date_from']),
                Carbon::parse($validated['date_to']),
                $validated['work_center_id'] ?? null,
            )
        );
    }

    /**
     * GET /api/oee/trend
     *
     * Dipanggil dari trend chart di OEE/Dashboard.vue saat user ganti mesin
     * atau rentang tanggal.
     */
    public function trend(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'work_center_id' => 'required|integer|exists:work_centers,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        return response()->json(
            $this->calculator->trendData(
                (int) $validated['work_center_id'],
                Carbon::parse($validated['date_from']),
                Carbon::parse($validated['date_to']),
            )
        );
    }

    /**
     * GET /api/oee/snapshots/{oeeSnapshot}/benchmark
     *
     * Dipanggil saat user klik snapshot tertentu di trend chart untuk lihat
     * gap vs world class benchmark (US-09 bagian benchmark).
     */
    public function benchmark(OeeSnapshot $oeeSnapshot): JsonResponse
    {
        return response()->json($this->calculator->benchmarkVsWorldClass($oeeSnapshot));
    }
}