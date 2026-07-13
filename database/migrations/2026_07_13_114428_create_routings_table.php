<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('routings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->integer('sequence');
            $table->foreignId('work_center_id')->constrained('work_centers')->onDelete('restrict');
            $table->decimal('std_process_time_minutes', 10, 4);
            $table->decimal('setup_time_minutes', 10, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'sequence']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('routings');
    }
};