<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MrpRun extends Model
{
    use HasFactory;

    public $timestamps = false; // hanya created_at + computed_at

    protected $fillable = ['schedule_id', 'computed_at', 'created_by'];

    protected $casts = [
        'computed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function requirements()
    {
        return $this->hasMany(MrpRequirement::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}