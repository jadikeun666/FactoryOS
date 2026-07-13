<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'sku', 'unit', 'description'];

    public function billOfMaterials()
    {
        return $this->hasMany(BillOfMaterial::class);
    }

    public function routings()
    {
        return $this->hasMany(Routing::class)->orderBy('sequence');
    }
    public function workOrders()
{
    return $this->hasMany(WorkOrder::class);
}
}