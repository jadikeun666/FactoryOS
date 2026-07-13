<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduleAssignment extends Model
{
    use HasFactory;

    public $timestamps = false; // immutable, hanya created_at

    protected $fillable = [
        'schedule_id', 'wo_operation_id', 'work_center_id',
        'start_at', 'end_at', 'slot_index',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function woOperation()
    {
        return $this->belongsTo(WoOperation::class);
    }

    public function workCenter()
    {
        return $this->belongsTo(WorkCenter::class);
    }
}