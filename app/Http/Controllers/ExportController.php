<?php

namespace App\Http\Controllers;

use App\Exceptions\ExportNotAllowedException;
use App\Jobs\GenerateExcelMrpReportJob;
use App\Jobs\GenerateExcelOeeTrendReportJob;
use App\Jobs\GeneratePdfOeeReportJob;
use App\Jobs\GeneratePdfReportJob;
use App\Models\MrpRun;
use App\Models\OeeSnapshot;
use App\Models\ProductionLog;
use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ExportController — thin, semua generasi file didelegasikan ke Job
 * (docs/exports.md § Export Guard, § Download Flow).
 *
 * PENTING: endpoint export mengembalikan JSON murni ("Export sedang
 * diproses") karena generasi file selalu background job — endpoint ini
 * HARUS dikonsumsi frontend via fetch(), BUKAN router.post() Inertia
 * (lihat claude.md § Catatan Teknis Penting, pola sama dengan MrpController).
 */
class ExportController extends Controller
{
    /**
     * POST /exports/schedule/{schedule}/pdf
     */
    public function schedulePdf(Schedule $schedule): JsonResponse
    {
        if (! $schedule->assignments()->exists()) {
            throw new ExportNotAllowedException('Schedule belum memiliki assignments.');
        }

        GeneratePdfReportJob::dispatch($schedule, auth()->id());

        return response()->json(['message' => 'Export sedang diproses']);
    }

    /**
     * GET /exports/schedule/{schedule}/pdf/status
     *
     * Dipoll frontend setelah schedulePdf() dipanggil, sampai job selesai
     * dan path tersimpan di Cache. TIDAK ada tabel DB untuk tracking export
     * (lihat catatan di GeneratePdfReportJob) — murni baca dari Cache.
     */
    public function schedulePdfStatus(Schedule $schedule): JsonResponse
    {
        $path = Cache::get("export:schedule_pdf:{$schedule->id}:".auth()->id());

        return response()->json([
            'ready' => $path !== null,
            'path'  => $path,
        ]);
    }

    /**
     * POST /exports/oee/pdf
     *
     * Body: date (required, Y-m-d), work_center_id (nullable).
     */
    public function oeePdf(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date'           => 'required|date',
            'work_center_id' => 'nullable|integer|exists:work_centers,id',
        ]);

        $date = Carbon::parse($validated['date']);
        $workCenterId = $validated['work_center_id'] ?? null;

        $hasLogs = ProductionLog::query()
            ->whereDate('log_date', $date->toDateString())
            ->when($workCenterId !== null, fn ($q) => $q->where('work_center_id', $workCenterId))
            ->exists();

        if (! $hasLogs) {
            throw new ExportNotAllowedException('Tidak ada log produksi untuk tanggal ini.');
        }

        GeneratePdfOeeReportJob::dispatch($date, $workCenterId, auth()->id());

        return response()->json(['message' => 'Export sedang diproses']);
    }

    /**
     * GET /exports/oee/pdf/status?date=2026-07-24&work_center_id=1
     */
    public function oeePdfStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date'           => 'required|date',
            'work_center_id' => 'nullable|integer',
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();
        $workCenterId = $validated['work_center_id'] ?? 'all';

        $path = Cache::get("export:oee_pdf:{$date}:{$workCenterId}:".auth()->id());

        return response()->json([
            'ready' => $path !== null,
            'path'  => $path,
        ]);
    }

    /**
     * POST /exports/mrp/{mrpRun}/excel
     */
    public function mrpExcel(MrpRun $mrpRun): JsonResponse
    {
        if (! $mrpRun->requirements()->exists()) {
            throw new ExportNotAllowedException('MRP Run belum memiliki requirements.');
        }

        GenerateExcelMrpReportJob::dispatch($mrpRun, auth()->id());

        return response()->json(['message' => 'Export sedang diproses']);
    }

    /**
     * GET /exports/mrp/{mrpRun}/excel/status
     */
    public function mrpExcelStatus(MrpRun $mrpRun): JsonResponse
    {
        $path = Cache::get("export:mrp_excel:{$mrpRun->id}:".auth()->id());

        return response()->json([
            'ready' => $path !== null,
            'path'  => $path,
        ]);
    }

    /**
     * POST /exports/oee-trend/excel
     *
     * Body: month (required, format Y-m, mis. "2026-07").
     */
    public function oeeTrendExcel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        $from = Carbon::parse($validated['month'].'-01')->startOfMonth();
        $to = $from->copy()->endOfMonth();

        $hasSnapshots = OeeSnapshot::query()
            ->whereDate('log_date', '>=', $from->toDateString())
            ->whereDate('log_date', '<=', $to->toDateString())
            ->exists();

        if (! $hasSnapshots) {
            throw new ExportNotAllowedException('Tidak ada data OEE untuk bulan ini.');
        }

        GenerateExcelOeeTrendReportJob::dispatch($from, $to, auth()->id());

        return response()->json(['message' => 'Export sedang diproses']);
    }

    /**
     * GET /exports/oee-trend/excel/status?month=2026-07
     */
    public function oeeTrendExcelStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        $path = Cache::get("export:oee_trend_excel:{$validated['month']}:".auth()->id());

        return response()->json([
            'ready' => $path !== null,
            'path'  => $path,
        ]);
    }

    /**
     * GET /exports/download?path=exports/schedule_1_20260724_143022.pdf
     *
     * Auth-gated (route di dalam middleware('auth')) sebagai guard minimal
     * sesuai docs/exports.md § Download Flow. Path divalidasi hanya boleh
     * di dalam folder "exports/" untuk mencegah path traversal ke file lain
     * di disk 'local'.
     */
    public function download(): StreamedResponse
    {
        $path = request()->query('path', '');

        if (! is_string($path) || ! str_starts_with($path, 'exports/') || str_contains($path, '..')) {
            abort(403, 'Path export tidak valid.');
        }

        if (! Storage::disk('local')->exists($path)) {
            abort(404, 'File export tidak ditemukan (mungkin sudah dibersihkan setelah 7 hari).');
        }

        return Storage::disk('local')->download($path);
    }
}