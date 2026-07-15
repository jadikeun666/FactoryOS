<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'start_time', 'end_time', 'planned_minutes', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function productionLogs()
    {
        return $this->hasMany(ProductionLog::class);
    }
}