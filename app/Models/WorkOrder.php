<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'qty', 'due_date', 'priority',
        'release_date', 'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'qty' => 'decimal:4',
        'due_date' => 'date',
        'release_date' => 'date',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function operations()
    {
        return $this->hasMany(WoOperation::class)->orderBy('sequence');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}