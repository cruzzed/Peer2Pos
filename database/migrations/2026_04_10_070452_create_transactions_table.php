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
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('origin_node_id');
            $table->string('cashier_name')->nullable();
            $table->decimal('total_amount', 12, 2);
            $table->string('payment_method')->default('cash');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('origin_node_id')->references('id')->on('nodes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
