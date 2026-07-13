<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    public $timestamps = false; // immutable, hanya created_at

    protected $fillable = [
        'algorithm', 'makespan_minutes', 'total_tardiness_minutes',
        'late_wo_count', 'mean_flow_time_minutes', 'scheduled_from', 'created_by',
    ];

    protected $casts = [
        'scheduled_from' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function assignments()
    {
        return $this->hasMany(ScheduleAssignment::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}