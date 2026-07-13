<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WoOperation extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_order_id', 'routing_id', 'work_center_id', 'sequence',
        'planned_start', 'planned_end', 'actual_start', 'actual_end', 'status',
    ];

    protected $casts = [
        'planned_start' => 'datetime',
        'planned_end' => 'datetime',
        'actual_start' => 'datetime',
        'actual_end' => 'datetime',
    ];

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function routing()
    {
        return $this->belongsTo(Routing::class);
    }

    public function workCenter()
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function scheduleAssignment()
    {
        return $this->hasOne(ScheduleAssignment::class);
    }
}