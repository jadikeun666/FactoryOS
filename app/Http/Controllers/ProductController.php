<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\Product;
use App\Models\WorkCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ProductController — CRUD Product + nested BOM & Routing editor
 * (docs/architecture.md § Controllers: "CRUD + nested BOM & routing editor").
 * Thin: tidak ada kalkulasi, validasi inline mengikuti pola controller lain
 * yang sudah ada (ScheduleController, OeeController, MrpController).
 *
 * BOM & Routing item CRUD (storeBom/updateBom/destroyBom, storeRouting/
 * updateRouting/destroyRouting) TETAP di controller ini (bukan controller
 * terpisah) -- konsisten dengan docs yang menyebut satu ProductController
 * untuk keduanya.
 */
class ProductController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Product::class);

        $products = Product::query()
            ->withCount(['billOfMaterials', 'routings'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Products/Index', [
            'products' => $products,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('Products/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'sku'         => ['required', 'string', 'max:50', 'unique:products,sku'],
            'unit'        => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [
            'name.required' => 'Nama produk wajib diisi.',
            'sku.required'  => 'SKU wajib diisi.',
            'sku.unique'    => 'SKU sudah dipakai, gunakan kode lain.',
        ]);

        $data['unit'] = $data['unit'] ?? 'pcs';

        $product = Product::create($data);

        return redirect()
            ->route('products.edit', $product)
            ->with('success', 'Produk berhasil dibuat. Silakan lengkapi BOM dan Routing.');
    }

    /**
     * Halaman edit sekaligus BOM editor + Routing editor (nested).
     */
    public function edit(Product $product): Response
    {
        $this->authorize('update', $product);

        $product->load([
            'billOfMaterials.material',
            'routings' => fn ($q) => $q->orderBy('sequence'),
            'routings.workCenter',
        ]);

        return Inertia::render('Products/Edit', [
            'product'     => $product,
            'materials'   => Material::orderBy('name')->get(['id', 'name', 'sku', 'unit']),
            'workCenters' => WorkCenter::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'sku'         => ['required', 'string', 'max:50', 'unique:products,sku,' . $product->id],
            'unit'        => ['required', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [
            'name.required' => 'Nama produk wajib diisi.',
            'sku.required'  => 'SKU wajib diisi.',
            'sku.unique'    => 'SKU sudah dipakai, gunakan kode lain.',
        ]);

        $product->update($data);

        return back()->with('success', 'Produk berhasil diperbarui.');
    }

    /**
     * Hapus Product. Ditolak jika masih punya Work Order (FK ON DELETE
     * RESTRICT di work_orders.product_id, database.md).
     */
    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        if ($product->workOrders()->exists()) {
            return back()->with('error', 'Produk tidak bisa dihapus karena masih punya Work Order terkait.');
        }

        $product->delete();

        return redirect()
            ->route('products.index')
            ->with('success', 'Produk berhasil dihapus.');
    }

    // ── BOM nested editor ──────────────────────────────────────────

    public function storeBom(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $data = $request->validate([
            'material_id'  => ['required', 'integer', 'exists:materials,id'],
            'qty_per_unit' => ['required', 'numeric', 'min:0.000001'],
            'unit'         => ['required', 'string', 'max:20'],
            'notes'        => ['nullable', 'string', 'max:2000'],
        ], [
            'material_id.required'  => 'Material wajib dipilih.',
            'qty_per_unit.required' => 'Qty per unit wajib diisi.',
            'qty_per_unit.min'      => 'Qty per unit harus lebih besar dari 0.',
        ]);

        // UNIQUE(product_id, material_id) -- cek eksplisit supaya pesan
        // error jelas, bukan SQL constraint violation mentah.
        if ($product->billOfMaterials()->where('material_id', $data['material_id'])->exists()) {
            return back()->with('error', 'Material ini sudah ada di BOM produk. Edit baris yang sudah ada.');
        }

        $product->billOfMaterials()->create($data);

        return back()->with('success', 'Item BOM berhasil ditambahkan.');
    }

    public function updateBom(Request $request, Product $product, \App\Models\BillOfMaterial $bom): RedirectResponse
    {
        $this->authorize('update', $product);
        abort_if($bom->product_id !== $product->id, 404);

        $data = $request->validate([
            'qty_per_unit' => ['required', 'numeric', 'min:0.000001'],
            'unit'         => ['required', 'string', 'max:20'],
            'notes'        => ['nullable', 'string', 'max:2000'],
        ], [
            'qty_per_unit.required' => 'Qty per unit wajib diisi.',
            'qty_per_unit.min'      => 'Qty per unit harus lebih besar dari 0.',
        ]);

        $bom->update($data);

        return back()->with('success', 'Item BOM berhasil diperbarui.');
    }

    public function destroyBom(Product $product, \App\Models\BillOfMaterial $bom): RedirectResponse
    {
        $this->authorize('update', $product);
        abort_if($bom->product_id !== $product->id, 404);

        $bom->delete();

        return back()->with('success', 'Item BOM berhasil dihapus.');
    }

    // ── Routing nested editor ──────────────────────────────────────

    public function storeRouting(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $data = $request->validate([
            'sequence'                 => ['required', 'integer', 'min:1'],
            'work_center_id'           => ['required', 'integer', 'exists:work_centers,id'],
            'std_process_time_minutes' => ['required', 'numeric', 'min:0.0001'],
            'setup_time_minutes'       => ['nullable', 'numeric', 'min:0'],
            'notes'                    => ['nullable', 'string', 'max:2000'],
        ], [
            'sequence.required'                 => 'Urutan operasi wajib diisi.',
            'work_center_id.required'           => 'Mesin wajib dipilih.',
            'std_process_time_minutes.required' => 'Waktu proses standar wajib diisi.',
        ]);

        // UNIQUE(product_id, sequence)
        if ($product->routings()->where('sequence', $data['sequence'])->exists()) {
            return back()->with('error', "Urutan operasi #{$data['sequence']} sudah dipakai. Gunakan urutan lain.");
        }

        $data['setup_time_minutes'] = $data['setup_time_minutes'] ?? 0;

        $product->routings()->create($data);

        return back()->with('success', 'Operasi routing berhasil ditambahkan.');
    }

    public function updateRouting(Request $request, Product $product, \App\Models\Routing $routing): RedirectResponse
    {
        $this->authorize('update', $product);
        abort_if($routing->product_id !== $product->id, 404);

        $data = $request->validate([
            'sequence'                 => ['required', 'integer', 'min:1'],
            'work_center_id'           => ['required', 'integer', 'exists:work_centers,id'],
            'std_process_time_minutes' => ['required', 'numeric', 'min:0.0001'],
            'setup_time_minutes'       => ['required', 'numeric', 'min:0'],
            'notes'                    => ['nullable', 'string', 'max:2000'],
        ]);

        if ($product->routings()->where('sequence', $data['sequence'])->where('id', '!=', $routing->id)->exists()) {
            return back()->with('error', "Urutan operasi #{$data['sequence']} sudah dipakai. Gunakan urutan lain.");
        }

        $routing->update($data);

        return back()->with('success', 'Operasi routing berhasil diperbarui.');
    }

    /**
     * Hapus baris routing. Ditolak jika sudah dipakai di wo_operations
     * manapun (FK ON DELETE RESTRICT, database.md) -- WO yang sudah
     * di-generate operasinya dari routing ini akan patah kalau routing
     * dihapus.
     */
    public function destroyRouting(Product $product, \App\Models\Routing $routing): RedirectResponse
    {
        $this->authorize('update', $product);
        abort_if($routing->product_id !== $product->id, 404);

        if ($routing->woOperations()->exists()) {
            return back()->with('error', 'Operasi routing tidak bisa dihapus karena sudah dipakai di wo_operations Work Order yang sudah ada.');
        }

        $routing->delete();

        return back()->with('success', 'Operasi routing berhasil dihapus.');
    }
}