<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->unique()->constrained('materials')->onDelete('restrict');
            $table->decimal('qty_on_hand', 15, 4)->default(0);
            $table->decimal('qty_on_order', 15, 4)->default(0);
            $table->timestamp('last_updated')->useCurrent();
        });
    }

    public function down(): void {
        Schema::dropIfExists('inventory');
    }
};