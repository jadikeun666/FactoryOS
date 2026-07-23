<?php

namespace App\Http\Controllers;

use App\Models\Material;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * MaterialController — thin controller, CRUD sederhana (docs/architecture.md
 * § Controllers: "CRUD + inventory params (EOQ inputs)" -- tapi inventory
 * params (EOQ inputs) sudah punya jalur tersendiri lewat seeder +
 * EoqCalculatorService::computeAndSave(), BUKAN lewat form CRUD Material
 * ini. Form ini murni untuk data dasar material (name/sku/unit/unit_cost),
 * konsisten dengan fillable Material model.
 */
class MaterialController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Material::class);

        $materials = Material::query()
            ->orderBy('name')
            ->get();

        return Inertia::render('Materials/Index', [
            'materials' => $materials,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Material::class);

        return Inertia::render('Materials/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Material::class);

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'sku'         => ['required', 'string', 'max:50', 'unique:materials,sku'],
            'unit'        => ['nullable', 'string', 'max:20'],
            'unit_cost'   => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [
            'name.required' => 'Nama material wajib diisi.',
            'sku.required'  => 'SKU wajib diisi.',
            'sku.unique'    => 'SKU sudah dipakai, gunakan kode lain.',
        ]);

        $data['unit'] = $data['unit'] ?? 'pcs';
        $data['unit_cost'] = $data['unit_cost'] ?? 0;

        Material::create($data);

        return redirect()
            ->route('materials.index')
            ->with('success', 'Material berhasil ditambahkan.');
    }

    public function edit(Material $material): Response
    {
        $this->authorize('update', $material);

        return Inertia::render('Materials/Edit', [
            'material' => $material,
        ]);
    }

    public function update(Request $request, Material $material): RedirectResponse
    {
        $this->authorize('update', $material);

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'sku'         => ['required', 'string', 'max:50', 'unique:materials,sku,' . $material->id],
            'unit'        => ['required', 'string', 'max:20'],
            'unit_cost'   => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [
            'name.required' => 'Nama material wajib diisi.',
            'sku.required'  => 'SKU wajib diisi.',
            'sku.unique'    => 'SKU sudah dipakai, gunakan kode lain.',
        ]);

        $material->update($data);

        return redirect()
            ->route('materials.index')
            ->with('success', 'Material berhasil diperbarui.');
    }

    /**
     * Hapus Material. Ditolak jika masih dipakai di BOM manapun (FK ON
     * DELETE RESTRICT di database.md), atau punya Inventory/InventoryParam
     * terkait (supaya tidak orphan data Engine 3).
     */
    public function destroy(Material $material): RedirectResponse
    {
        $this->authorize('delete', $material);

        if ($material->billOfMaterials()->exists()) {
            return back()->with('error', 'Material tidak bisa dihapus karena masih dipakai di BOM produk.');
        }

        if ($material->inventory()->exists() || $material->inventoryParam()->exists()) {
            return back()->with('error', 'Material tidak bisa dihapus karena masih punya data Inventory/EOQ terkait.');
        }

        $material->delete();

        return redirect()
            ->route('materials.index')
            ->with('success', 'Material berhasil dihapus.');
    }
}