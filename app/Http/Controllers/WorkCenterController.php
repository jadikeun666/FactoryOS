<?php

namespace App\Http\Controllers;

use App\Models\WorkCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * WorkCenterController — thin controller, CRUD + toggle active
 * (docs/architecture.md § Controllers). Otorisasi didelegasikan ke
 * WorkCenterPolicy yang sudah ada (dipakai juga untuk channel broadcasting
 * Engine 2, lihat routes/channels.php) — create/update/delete admin-only,
 * viewAny/view semua user login.
 */
class WorkCenterController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', WorkCenter::class);

        $workCenters = WorkCenter::query()
            ->orderBy('name')
            ->get();

        return Inertia::render('WorkCenters/Index', [
            'workCenters' => $workCenters,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', WorkCenter::class);

        return Inertia::render('WorkCenters/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', WorkCenter::class);

        $data = $request->validate([
            'name'                       => ['required', 'string', 'max:100'],
            'code'                       => ['required', 'string', 'max:20', 'unique:work_centers,code'],
            'capacity_per_shift_minutes' => ['nullable', 'numeric', 'min:0'],
            'setup_time_minutes'         => ['nullable', 'numeric', 'min:0'],
            'is_active'                  => ['nullable', 'boolean'],
            'description'                => ['nullable', 'string', 'max:2000'],
        ], [
            'name.required' => 'Nama mesin wajib diisi.',
            'code.required' => 'Kode mesin wajib diisi.',
            'code.unique'   => 'Kode mesin sudah dipakai, gunakan kode lain.',
        ]);

        $data['capacity_per_shift_minutes'] = $data['capacity_per_shift_minutes'] ?? 480;
        $data['setup_time_minutes'] = $data['setup_time_minutes'] ?? 0;
        $data['is_active'] = $data['is_active'] ?? true;

        WorkCenter::create($data);

        return redirect()
            ->route('work-centers.index')
            ->with('success', 'Mesin berhasil ditambahkan.');
    }

    public function edit(WorkCenter $workCenter): Response
    {
        $this->authorize('update', $workCenter);

        return Inertia::render('WorkCenters/Edit', [
            'workCenter' => $workCenter,
        ]);
    }

    public function update(Request $request, WorkCenter $workCenter): RedirectResponse
    {
        $this->authorize('update', $workCenter);

        $data = $request->validate([
            'name'                       => ['required', 'string', 'max:100'],
            'code'                       => ['required', 'string', 'max:20', 'unique:work_centers,code,' . $workCenter->id],
            'capacity_per_shift_minutes' => ['required', 'numeric', 'min:0'],
            'setup_time_minutes'         => ['required', 'numeric', 'min:0'],
            'is_active'                  => ['required', 'boolean'],
            'description'                => ['nullable', 'string', 'max:2000'],
        ], [
            'name.required' => 'Nama mesin wajib diisi.',
            'code.required' => 'Kode mesin wajib diisi.',
            'code.unique'   => 'Kode mesin sudah dipakai, gunakan kode lain.',
        ]);

        $workCenter->update($data);

        return redirect()
            ->route('work-centers.index')
            ->with('success', 'Mesin berhasil diperbarui.');
    }

    /**
     * Toggle is_active (docs/architecture.md: "CRUD + toggle active").
     * Endpoint terpisah dari update() supaya toggle cepat tidak perlu
     * lewat form penuh.
     */
    public function toggleActive(WorkCenter $workCenter): RedirectResponse
    {
        $this->authorize('update', $workCenter);

        $workCenter->update(['is_active' => ! $workCenter->is_active]);

        return back()->with('success', $workCenter->is_active
            ? 'Mesin diaktifkan.'
            : 'Mesin dinonaktifkan.');
    }

    /**
     * Hapus Work Center. Ditolak jika masih dipakai di Routing manapun
     * (FK ON DELETE RESTRICT di database.md, tapi kita cek eksplisit di
     * sini supaya pesan error jelas, bukan SQL exception mentah).
     */
    public function destroy(WorkCenter $workCenter): RedirectResponse
    {
        $this->authorize('delete', $workCenter);

        if ($workCenter->routings()->exists()) {
            return back()->with('error', 'Mesin tidak bisa dihapus karena masih dipakai di Routing produk.');
        }

        $workCenter->delete();

        return redirect()
            ->route('work-centers.index')
            ->with('success', 'Mesin berhasil dihapus.');
    }
}