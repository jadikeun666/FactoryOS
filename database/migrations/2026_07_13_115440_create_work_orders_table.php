<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->decimal('qty', 15, 4);
            $table->date('due_date');
            $table->integer('priority')->default(5);
            $table->date('release_date')->default(now());
            $table->string('status', 20)->default('draft');
            // ENUM: draft, scheduled, in_progress, done, late
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('work_orders');
    }
};