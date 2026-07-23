<?php

namespace App\Http\Controllers;

use App\Models\Material;
use Illuminate\Http\JsonResponse;

/**
 * InventoryController — thin, read-only controller untuk menyajikan status
 * stok tiap material (qty_on_hand vs safety_stock vs ROP), dikonsumsi oleh
 * RopGauge.vue (FR-06: "visual perbandingan stok on-hand vs safety stock
 * vs ROP").
 *
 * PENTING: controller ini TIDAK menghitung apapun. EOQ/Safety Stock/ROP
 * sudah dihitung & disimpan sebelumnya oleh EoqCalculatorService::
 * computeAndSave() (final, tidak diubah) — di sini murni query baca dari
 * kolom yang sudah tersimpan di inventory_params.
 *
 * Hanya material yang punya Inventory + InventoryParam (yaitu material
 * yang dipakai di BOM, lihat DatabaseSeeder Step 8) yang akan muncul di
 * response ini — material lain tidak relevan untuk MRP/reorder.
 */
class InventoryController extends Controller
{
    /**
     * GET /inventory/status
     */
    public function status(): JsonResponse
    {
        $materials = Material::query()
            ->whereHas('inventory')
            ->whereHas('inventoryParam')
            ->with(['inventory', 'inventoryParam'])
            ->get()
            ->map(fn (Material $material) => [
                'material_id'   => $material->id,
                'name'          => $material->name,
                'sku'           => $material->sku,
                'unit'          => $material->unit,
                'qty_on_hand'   => $material->inventory->qty_on_hand,
                'qty_on_order'  => $material->inventory->qty_on_order,
                'safety_stock'  => $material->inventoryParam->safety_stock,
                'rop'           => $material->inventoryParam->rop,
                'eoq'           => $material->inventoryParam->eoq,
                'last_updated'  => $material->inventory->last_updated,
            ]);

        return response()->json($materials);
    }
}