<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('qty_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->char('name', 50);
            $table->unsignedInteger('qty');          // base-unit multiplier
            $table->char('barcode', 50)->unique();
            $table->decimal('base_price', 12, 2);    // purchase/reference price
            $table->decimal('selling_price', 12, 2); // charged price; discount applies here
            $table->boolean('is_base')->default(false); // exactly one per product where qty=1
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qty_types');
    }
};
