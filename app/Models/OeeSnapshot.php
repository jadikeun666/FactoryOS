<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OeeSnapshot extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'work_center_id', 'log_date', 'shift_id',
        'availability', 'performance', 'quality', 'oee', 'computed_at',
    ];

    protected $casts = [
        'log_date' => 'date',
        'availability' => 'decimal:6',
        'performance' => 'decimal:6',
        'quality' => 'decimal:6',
        'oee' => 'decimal:6',
        'computed_at' => 'datetime',
    ];

    public function workCenter()
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
}