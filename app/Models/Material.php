<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'sku', 'unit', 'unit_cost', 'description'];

    protected $casts = [
        'unit_cost' => 'decimal:4',
    ];

    public function billOfMaterials()
    {
        return $this->hasMany(BillOfMaterial::class);
    }
    public function inventory()
    {
        return $this->hasOne(Inventory::class);
    }

    public function inventoryTransactions()
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function inventoryParam()
    {
        return $this->hasOne(InventoryParam::class);
    }

    public function reorderAlerts()
    {
        return $this->hasMany(ReorderAlert::class);
    }
}