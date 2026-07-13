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
}