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
        Schema::create('sync_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('syncable_type'); // App\Models\Product, App\Models\Transaction, etc.
            $table->uuid('syncable_id');
            $table->uuid('node_id');
            $table->string('status')->default('pending'); // pending | synced | failed
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['syncable_type', 'syncable_id', 'node_id']);
            $table->index(['syncable_type', 'syncable_id']);
            $table->foreign('node_id')->references('id')->on('nodes')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_statuses');
    }
};
