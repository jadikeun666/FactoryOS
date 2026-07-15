<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkCenter extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'capacity_per_shift_minutes',
        'setup_time_minutes', 'is_active', 'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity_per_shift_minutes' => 'decimal:2',
        'setup_time_minutes' => 'decimal:2',
    ];

    public function routings()
    {
        return $this->hasMany(Routing::class);
    }
    public function woOperations()
    {
        return $this->hasMany(WoOperation::class);
    }
    public function productionLogs()
    {
        return $this->hasMany(ProductionLog::class);
    }

    public function oeeSnapshots()
    {
        return $this->hasMany(OeeSnapshot::class);
    }
}