<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DowntimeEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_log_id', 'reason_category', 'reason_detail',
        'duration_minutes', 'started_at',
    ];

    protected $casts = [
        'duration_minutes' => 'decimal:2',
        'started_at' => 'datetime',
    ];

    public function productionLog()
    {
        return $this->belongsTo(ProductionLog::class);
    }
}