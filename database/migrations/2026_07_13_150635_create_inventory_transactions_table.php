<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('materials')->onDelete('restrict');
            $table->string('type', 10); // ENUM: in, out, adjust
            $table->decimal('qty', 15, 4);
            $table->decimal('unit_cost', 15, 4)->nullable();
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('created_at')->useCurrent();
            // immutable, tidak ada updated_at
        });
    }

    public function down(): void {
        Schema::dropIfExists('inventory_transactions');
    }
};