<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('downtime_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_log_id')->constrained('production_logs')->onDelete('cascade');
            $table->string('reason_category', 20);
            // ENUM: breakdown, setup, material, operator, other
            $table->string('reason_detail', 255)->nullable();
            $table->decimal('duration_minutes', 10, 2);
            $table->timestamp('started_at');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('downtime_events');
    }
};