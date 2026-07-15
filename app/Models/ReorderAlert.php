<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReorderAlert extends Model
{
    use HasFactory;

    protected $fillable = ['material_id', 'current_qty', 'rop_qty', 'eoq_qty', 'status'];

    protected $casts = [
        'current_qty' => 'decimal:4',
        'rop_qty' => 'decimal:4',
        'eoq_qty' => 'decimal:4',
    ];

    public function material()
    {
        return $this->belongsTo(Material::class);
    }
}