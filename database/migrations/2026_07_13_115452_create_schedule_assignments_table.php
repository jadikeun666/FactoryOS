<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('schedules')->onDelete('cascade');
            $table->foreignId('wo_operation_id')->constrained('wo_operations')->onDelete('cascade');
            $table->foreignId('work_center_id')->constrained('work_centers')->onDelete('restrict');
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->integer('slot_index');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void {
        Schema::dropIfExists('schedule_assignments');
    }
};