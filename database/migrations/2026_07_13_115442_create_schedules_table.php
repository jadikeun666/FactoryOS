<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->string('algorithm', 10); // spt, edd, cr, fifo
            $table->decimal('makespan_minutes', 12, 2)->nullable();
            $table->decimal('total_tardiness_minutes', 12, 2)->nullable();
            $table->integer('late_wo_count')->nullable();
            $table->decimal('mean_flow_time_minutes', 12, 2)->nullable();
            $table->timestamp('scheduled_from');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('created_at')->useCurrent();
            // immutable: tidak ada updated_at
        });
    }

    public function down(): void {
        Schema::dropIfExists('schedules');
    }
};