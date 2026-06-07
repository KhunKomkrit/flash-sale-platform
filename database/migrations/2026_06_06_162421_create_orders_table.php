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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('sale_event_id')
                ->constrained('sales_events')
                ->cascadeOnDelete();

            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);

            $table->string('status')->default('pending');

            $table->softDeletes();
            $table->timestamps();

            // Composite index for common dashboard/user history query:
            // WHERE user_id = ? AND status = ? ORDER BY created_at DESC.
            // Composite is better than single user_id because it supports filtering by user + status
            // and may reduce filesort for latest orders. Trade-off: extra write/storage overhead on every order insert/update.
            $table->index(['user_id', 'status', 'created_at'], 'idx_orders_user_status_created');

            // Composite index for event dashboard pagination:
            // WHERE sale_event_id = ? ORDER BY created_at DESC.
            // Trade-off: improves read-heavy dashboard queries but adds index maintenance during flash-sale writes.
            $table->index(['sale_event_id', 'created_at'], 'idx_orders_event_created');

            // Composite index for product sales aggregation:
            // WHERE product_id = ? AND status = ?.
            // Trade-off: useful for reporting/top-selling queries, but status has low cardinality alone,
            // so it is combined with product_id instead of indexed separately.
            $table->index(['product_id', 'status'], 'idx_orders_product_status');

            // Unique index to prevent the same user from buying the same product in the same sale event more than once.
            // This protects correctness at DB level under concurrent requests.
            // Trade-off: can increase lock contention during burst writes, but correctness is more important here.
            $table->unique(
                ['user_id', 'product_id', 'sale_event_id'],
                'uniq_orders_user_product_event'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
