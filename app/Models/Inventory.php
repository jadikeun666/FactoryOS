<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

    /**
     * Nama tabel migration adalah 'inventory' (singular), bukan
     * 'inventories' -- Eloquent secara default menebak nama tabel plural
     * dari nama model. Tanpa override ini, query ke model Inventory akan
     * gagal dengan "no such table: inventories" (ditemukan saat menulis
     * MrpServiceTest, sesi 2026-07-20).
     */
    protected $table = 'inventory';

    public $timestamps = false; // hanya last_updated

    protected $fillable = ['material_id', 'qty_on_hand', 'qty_on_order', 'last_updated'];

    protected $casts = [
        'qty_on_hand' => 'decimal:4',
        'qty_on_order' => 'decimal:4',
        'last_updated' => 'datetime',
    ];

    public function material()
    {
        return $this->belongsTo(Material::class);
    }
}