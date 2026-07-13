<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('oee_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_center_id')->constrained('work_centers')->onDelete('restrict');
            $table->date('log_date');
            $table->foreignId('shift_id')->constrained('shifts')->onDelete('restrict');
            $table->decimal('availability', 8, 6);
            $table->decimal('performance', 8, 6);
            $table->decimal('quality', 8, 6);
            $table->decimal('oee', 8, 6);
            $table->timestamp('computed_at');

            $table->unique(['work_center_id', 'log_date', 'shift_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('oee_snapshots');
    }
};