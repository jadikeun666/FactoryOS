<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('mrp_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mrp_run_id')->constrained('mrp_runs')->onDelete('cascade');
            $table->foreignId('material_id')->constrained('materials')->onDelete('restrict');
            $table->date('period_date');
            $table->decimal('gross_requirement', 15, 4)->default(0);
            $table->decimal('scheduled_receipts', 15, 4)->default(0);
            $table->decimal('projected_on_hand', 15, 4)->default(0);
            $table->decimal('net_requirement', 15, 4)->default(0);
            $table->decimal('planned_order_release', 15, 4)->default(0);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void {
        Schema::dropIfExists('mrp_requirements');
    }
};