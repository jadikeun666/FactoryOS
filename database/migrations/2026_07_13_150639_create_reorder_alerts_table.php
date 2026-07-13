<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('reorder_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('materials')->onDelete('restrict');
            $table->decimal('current_qty', 15, 4);
            $table->decimal('rop_qty', 15, 4);
            $table->decimal('eoq_qty', 15, 4);
            $table->string('status', 20)->default('open');
            // ENUM: open, acknowledged, ordered
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('reorder_alerts');
    }
};