<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryParam extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_id', 'annual_demand', 'ordering_cost',
        'holding_cost_per_unit_year', 'lead_time_days', 'demand_std_dev',
        'service_level_z', 'eoq', 'safety_stock', 'rop', 'last_computed_at',
    ];

    protected $casts = [
        'annual_demand' => 'decimal:4',
        'ordering_cost' => 'decimal:4',
        'holding_cost_per_unit_year' => 'decimal:4',
        'demand_std_dev' => 'decimal:4',
        'service_level_z' => 'decimal:4',
        'eoq' => 'decimal:4',
        'safety_stock' => 'decimal:4',
        'rop' => 'decimal:4',
        'last_computed_at' => 'datetime',
    ];

    public function material()
    {
        return $this->belongsTo(Material::class);
    }
}