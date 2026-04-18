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
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('qty_type_id')->unique(); // 1:1 with qty_type
            $table->boolean('enabled')->default(false);
            $table->string('type')->default('fixed'); // fixed | percent
            $table->decimal('value', 12, 2)->default(0);
            $table->boolean('timed')->default(false);
            $table->date('date_start')->nullable();
            $table->date('date_end')->nullable();
            $table->timestamps();

            $table->foreign('qty_type_id')->references('id')->on('qty_types')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
