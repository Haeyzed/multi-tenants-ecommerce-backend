<?php

declare(strict_types=1);

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
        Schema::create('seller_offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('sku')->nullable();
            $table->string('currency', 3);
            $table->decimal('price', 14, 2);
            $table->decimal('compare_at_price', 14, 2)->nullable();
            $table->decimal('cost', 14, 2)->nullable();
            $table->string('status')->default('inactive');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['seller_id', 'product_id', 'product_variant_id'],
                'seller_offers_seller_product_variant_unique',
            );
            $table->index(['status', 'seller_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_offers');
    }
};
