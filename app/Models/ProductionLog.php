<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_center_id', 'shift_id', 'log_date', 'planned_minutes',
        'downtime_minutes', 'actual_output', 'good_output',
        'ideal_cycle_time_minutes', 'is_validated', 'validated_at', 'created_by',
    ];

    protected $casts = [
        'log_date' => 'date',
        'planned_minutes' => 'decimal:2',
        'downtime_minutes' => 'decimal:2',
        'actual_output' => 'decimal:4',
        'good_output' => 'decimal:4',
        'ideal_cycle_time_minutes' => 'decimal:6',
        'is_validated' => 'boolean',
        'validated_at' => 'datetime',
    ];

    public function workCenter()
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function downtimeEvents()
    {
        return $this->hasMany(DowntimeEvent::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}