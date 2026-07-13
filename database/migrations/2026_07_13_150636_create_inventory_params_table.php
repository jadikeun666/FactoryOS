<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('inventory_params', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->unique()->constrained('materials')->onDelete('restrict');
            $table->decimal('annual_demand', 15, 4);
            $table->decimal('ordering_cost', 15, 4);
            $table->decimal('holding_cost_per_unit_year', 15, 4);
            $table->integer('lead_time_days');
            $table->decimal('demand_std_dev', 10, 4)->default(0);
            $table->decimal('service_level_z', 6, 4)->default(1.6450); // 95%
            $table->decimal('eoq', 15, 4)->nullable();
            $table->decimal('safety_stock', 15, 4)->nullable();
            $table->decimal('rop', 15, 4)->nullable();
            $table->timestamp('last_computed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('inventory_params');
    }
};