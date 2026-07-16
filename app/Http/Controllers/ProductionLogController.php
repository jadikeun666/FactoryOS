<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductionLogRequest;
use App\Http\Requests\UpdateProductionLogRequest;
use App\Models\ProductionLog;
use App\Models\Shift;
use App\Models\WorkCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ProductionLogController — thin controller sesuai docs/architecture.md.
 *
 * Tidak ada kalkulasi OEE di sini — itu terjadi otomatis lewat
 * ProductionLogObserver -> RecalculateOeeJob -> OeeCalculatorService saat
 * ProductionLog::create()/update() dipanggil (lihat docs/oee-formulas.md
 * § Real-time Update Flow).
 */
class ProductionLogController extends Controller
{
    /**
     * Daftar log produksi, dengan filter work center & tanggal opsional.
     */
    public function index(Request $request): Response
    {
        $logs = ProductionLog::query()
            ->with(['workCenter', 'shift'])
            ->when($request->filled('work_center_id'), fn ($q) => $q->where('work_center_id', $request->integer('work_center_id')))
            ->when($request->filled('log_date'), fn ($q) => $q->whereDate('log_date', $request->date('log_date')))
            ->orderByDesc('log_date')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('ProductionLogs/Index', [
            'logs'    => $logs,
            'filters' => $request->only('work_center_id', 'log_date'),
        ]);
    }

    /**
     * Form input log produksi baru (form operator, US-06/US-07).
     */
    public function create(): Response
    {
        return Inertia::render('ProductionLogs/Create', [
            'workCenters' => WorkCenter::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'shifts'      => Shift::query()->where('is_active', true)->orderBy('start_time')->get(['id', 'name', 'start_time', 'end_time']),
        ]);
    }

    /**
     * Simpan log produksi baru, beserta downtime events (jika ada) dalam
     * satu form yang sama (FR-04/US-07). Perhitungan OEE terjadi otomatis
     * lewat Observer setelah create() — tidak dipanggil manual di sini.
     */
    public function store(StoreProductionLogRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $downtimeEvents = $data['downtime_events'] ?? [];
        unset($data['downtime_events']);

        $data['created_by'] = $request->user()->id;

        $log = ProductionLog::create($data);

        // Penulisan langsung tanpa Service: hanya persistence sederhana,
        // tidak ada kalkulasi/percabangan bisnis (sesuai pengecualian di
        // engineering-rules.md § 4 untuk query "sangat sederhana").
        foreach ($downtimeEvents as $event) {
            $log->downtimeEvents()->create($event);
        }

        return redirect()
            ->route('production-logs.show', $log)
            ->with('success', 'Log produksi berhasil disimpan.');
    }

    /**
     * Detail satu log produksi beserta downtime events terkait.
     */
    public function show(ProductionLog $productionLog): Response
    {
        $productionLog->load(['workCenter', 'shift', 'downtimeEvents']);

        return Inertia::render('ProductionLogs/Show', [
            'productionLog' => $productionLog,
        ]);
    }

    /**
     * Form edit log produksi. Ditolak jika sudah validated (immutability).
     */
    public function edit(ProductionLog $productionLog): Response
    {
        $this->authorize('update', $productionLog);

        $productionLog->load('downtimeEvents');

        return Inertia::render('ProductionLogs/Edit', [
            'productionLog' => $productionLog,
        ]);
    }

    /**
     * Update log produksi. Perhitungan OEE otomatis di-recompute lewat
     * Observer setelah update() (selama belum validated).
     */
    public function update(UpdateProductionLogRequest $request, ProductionLog $productionLog): RedirectResponse
    {
        $this->authorize('update', $productionLog);

        $productionLog->update($request->validated());

        return redirect()
            ->route('production-logs.show', $productionLog)
            ->with('success', 'Log produksi berhasil diperbarui.');
    }

    /**
     * Hapus log produksi. Ditolak jika sudah validated (immutability).
     */
    public function destroy(ProductionLog $productionLog): RedirectResponse
    {
        $this->authorize('delete', $productionLog);

        $productionLog->delete();

        return redirect()
            ->route('production-logs.index')
            ->with('success', 'Log produksi berhasil dihapus.');
    }

    /**
     * Aksi "validate": supervisor/admin menandai log sebagai final
     * (is_validated = true). Setelah ini log tidak bisa diedit/dihapus.
     */
    public function validateAction(ProductionLog $productionLog): RedirectResponse
    {
        $this->authorize('validateLog', $productionLog);

        $productionLog->update([
            'is_validated' => true,
            'validated_at' => now(),
        ]);

        return back()->with('success', 'Log produksi berhasil divalidasi.');
    }
}