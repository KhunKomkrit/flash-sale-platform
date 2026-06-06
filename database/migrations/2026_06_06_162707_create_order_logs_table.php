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
        Schema::create('order_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('action');
            $table->json('payload')->nullable();

            $table->timestamps();

            // Composite index for audit trail lookup:
            // WHERE order_id = ? ORDER BY created_at ASC/DESC.
            // Trade-off: order_logs is append-heavy, so every log insert pays index maintenance cost,
            // but audit lookup by order is a critical support/debugging path.
            $table->index(['order_id', 'created_at'], 'idx_order_logs_order_created');

            // Composite index for investigating user activity:
            // WHERE user_id = ? ORDER BY created_at DESC.
            // Trade-off: adds write overhead on immutable logs, but useful for fraud/support investigations.
            $table->index(['user_id', 'created_at'], 'idx_order_logs_user_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_logs');
    }
};
