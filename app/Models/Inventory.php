<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

    public $timestamps = false; // hanya last_updated

    protected $fillable = ['material_id', 'qty_on_hand', 'qty_on_order', 'last_updated'];

    protected $casts = [
        'qty_on_hand' => 'decimal:4',
        'qty_on_order' => 'decimal:4',
        'last_updated' => 'datetime',
    ];

    public function material()
    {
        return $this->belongsTo(Material::class);
    }
}