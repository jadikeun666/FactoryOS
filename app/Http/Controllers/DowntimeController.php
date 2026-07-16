<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDowntimeEventRequest;
use App\Models\DowntimeEvent;
use App\Models\ProductionLog;
use Illuminate\Http\RedirectResponse;

/**
 * DowntimeController — CRUD downtime_events dalam satu production_log
 * (docs/architecture.md § Controllers). Otorisasi didelegasikan ke
 * ProductionLogPolicy::update() milik parent log — tidak ada
 * DowntimeEventPolicy terpisah, karena mutability downtime event mengikuti
 * mutability log induknya (is_validated).
 */
class DowntimeController extends Controller
{
    /**
     * Tambah downtime event ke log produksi yang sudah ada (di luar form
     * awal saat store ProductionLog).
     */
    public function store(StoreDowntimeEventRequest $request, ProductionLog $productionLog): RedirectResponse
    {
        $this->authorize('update', $productionLog);

        $productionLog->downtimeEvents()->create($request->validated());

        return back()->with('success', 'Downtime event berhasil ditambahkan.');
    }

    /**
     * Update satu downtime event.
     */
    public function update(StoreDowntimeEventRequest $request, ProductionLog $productionLog, DowntimeEvent $downtimeEvent): RedirectResponse
    {
        $this->authorize('update', $productionLog);

        $downtimeEvent->update($request->validated());

        return back()->with('success', 'Downtime event berhasil diperbarui.');
    }

    /**
     * Hapus satu downtime event.
     */
    public function destroy(ProductionLog $productionLog, DowntimeEvent $downtimeEvent): RedirectResponse
    {
        $this->authorize('update', $productionLog);

        $downtimeEvent->delete();

        return back()->with('success', 'Downtime event berhasil dihapus.');
    }
}