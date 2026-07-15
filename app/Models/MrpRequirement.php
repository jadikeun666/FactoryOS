<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MrpRequirement extends Model
{
    use HasFactory;

    public $timestamps = false; // immutable, hanya created_at

    protected $fillable = [
        'mrp_run_id', 'material_id', 'period_date', 'gross_requirement',
        'scheduled_receipts', 'projected_on_hand', 'net_requirement',
        'planned_order_release',
    ];

    protected $casts = [
        'period_date' => 'date',
        'gross_requirement' => 'decimal:4',
        'scheduled_receipts' => 'decimal:4',
        'projected_on_hand' => 'decimal:4',
        'net_requirement' => 'decimal:4',
        'planned_order_release' => 'decimal:4',
        'created_at' => 'datetime',
    ];

    public function mrpRun()
    {
        return $this->belongsTo(MrpRun::class);
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }
}