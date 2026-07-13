<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('bill_of_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('material_id')->constrained('materials')->onDelete('restrict');
            $table->decimal('qty_per_unit', 15, 6);
            $table->string('unit', 20);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'material_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('bill_of_materials');
    }
};