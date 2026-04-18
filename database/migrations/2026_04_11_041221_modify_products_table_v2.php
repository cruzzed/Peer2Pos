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
        Schema::table('products', function (Blueprint $table) {
            // Drop old columns no longer on the model
            $table->dropColumn(['sku', 'price', 'stock_qty']);

            // Add new columns
            $table->char('item_code', 50)->unique()->after('id');
            $table->uuid('supplier_id')->nullable()->after('category_id');
            $table->uuid('location_id')->nullable()->after('supplier_id');
            $table->unsignedBigInteger('created_by')->nullable()->after('location_id');
            $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
            // stock_qty reserved for future inventory tracking (computed/cached)
            $table->integer('stock_qty')->default(0)->after('updated_by');

            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
            $table->foreign('location_id')->references('id')->on('locations')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['supplier_id', 'location_id', 'created_by', 'updated_by']);
            $table->dropColumn(['item_code', 'supplier_id', 'location_id', 'created_by', 'updated_by', 'stock_qty']);
            $table->string('sku')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->integer('stock_qty')->default(0);
        });
    }
};
