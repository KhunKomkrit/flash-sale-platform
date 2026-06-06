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
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('sku')->unique();
            $table->unsignedInteger('stock');
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            // Single-column index for product listing/filtering by active products.
            // Trade-off: adds small write/storage overhead, but useful for frequent product filtering.
            $table->index('is_active');

            // Composite index for sorting active products by latest created date.
            // Trade-off: improves read performance for product listing but adds overhead on insert/update.
            $table->index(['is_active', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
