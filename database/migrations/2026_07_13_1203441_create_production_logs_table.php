<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('production_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_center_id')->constrained('work_centers')->onDelete('restrict');
            $table->foreignId('shift_id')->constrained('shifts')->onDelete('restrict');
            $table->date('log_date');
            $table->decimal('planned_minutes', 10, 2);
            $table->decimal('downtime_minutes', 10, 2)->default(0);
            $table->decimal('actual_output', 15, 4);
            $table->decimal('good_output', 15, 4);
            $table->decimal('ideal_cycle_time_minutes', 10, 6);
            $table->boolean('is_validated')->default(false);
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['work_center_id', 'shift_id', 'log_date']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('production_logs');
    }
};