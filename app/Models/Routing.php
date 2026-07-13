<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Routing extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'sequence', 'work_center_id',
        'std_process_time_minutes', 'setup_time_minutes', 'notes',
    ];

    protected $casts = [
        'std_process_time_minutes' => 'decimal:4',
        'setup_time_minutes' => 'decimal:4',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function workCenter()
    {
        return $this->belongsTo(WorkCenter::class);
    }
    public function woOperations()
{
    return $this->hasMany(WoOperation::class);
}
}