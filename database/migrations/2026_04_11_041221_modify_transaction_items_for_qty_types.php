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
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->uuid('qty_type_id')->nullable()->after('product_id');
            $table->string('qty_type_name')->nullable()->after('product_name'); // snapshot

            $table->foreign('qty_type_id')->references('id')->on('qty_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropForeign(['qty_type_id']);
            $table->dropColumn(['qty_type_id', 'qty_type_name']);
        });
    }
};
