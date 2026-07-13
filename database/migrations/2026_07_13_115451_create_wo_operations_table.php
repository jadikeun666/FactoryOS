<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('wo_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->onDelete('cascade');
            $table->foreignId('routing_id')->constrained('routings')->onDelete('restrict');
            $table->foreignId('work_center_id')->constrained('work_centers')->onDelete('restrict');
            $table->integer('sequence');
            $table->timestamp('planned_start')->nullable();
            $table->timestamp('planned_end')->nullable();
            $table->timestamp('actual_start')->nullable();
            $table->timestamp('actual_end')->nullable();
            $table->string('status', 20)->default('pending');
            // ENUM: pending, running, done, skipped
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('wo_operations');
    }
};