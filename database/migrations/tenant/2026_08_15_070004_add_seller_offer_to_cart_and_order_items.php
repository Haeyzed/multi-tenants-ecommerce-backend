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
        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropUnique(['cart_id', 'product_id', 'product_variant_id']);

            $table->foreignId('seller_offer_id')
                ->nullable()
                ->after('product_variant_id')
                ->constrained('seller_offers')
                ->nullOnDelete();

            $table->unique(['cart_id', 'product_id', 'product_variant_id', 'seller_offer_id']);
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreignId('seller_offer_id')
                ->nullable()
                ->after('product_variant_id')
                ->constrained('seller_offers')
                ->nullOnDelete();

            $table->foreignId('seller_id')
                ->nullable()
                ->after('seller_offer_id')
                ->constrained('sellers')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('seller_id');
            $table->dropConstrainedForeignId('seller_offer_id');
        });

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropUnique(['cart_id', 'product_id', 'product_variant_id', 'seller_offer_id']);
            $table->dropConstrainedForeignId('seller_offer_id');
            $table->unique(['cart_id', 'product_id', 'product_variant_id']);
        });
    }
};
