<?php

declare(strict_types=1);

use Database\Migrations\Concerns\ForeignKeyIndexHelper;
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
            ForeignKeyIndexHelper::dropForeignKeys($table, [
                'cart_id',
                'product_id',
                'product_variant_id',
            ]);

            $table->dropUnique('cart_items_line_unique');

            $table->foreignId('seller_offer_id')
                ->nullable()
                ->after('product_variant_id')
                ->constrained('seller_offers')
                ->nullOnDelete();

            $table->unique(
                ['cart_id', 'product_id', 'product_variant_id', 'seller_offer_id'],
                'cart_items_offer_unique',
            );

            $table->foreign('cart_id')
                ->references('id')
                ->on('carts')
                ->cascadeOnDelete();
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->restrictOnDelete();
            $table->foreign('product_variant_id')
                ->references('id')
                ->on('product_variants')
                ->restrictOnDelete();
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
            ForeignKeyIndexHelper::dropForeignKeys($table, [
                'cart_id',
                'product_id',
                'product_variant_id',
            ]);

            $table->dropUnique('cart_items_offer_unique');
            $table->dropConstrainedForeignId('seller_offer_id');

            $table->unique(
                ['cart_id', 'product_id', 'product_variant_id'],
                'cart_items_line_unique',
            );

            $table->foreign('cart_id')
                ->references('id')
                ->on('carts')
                ->cascadeOnDelete();
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->restrictOnDelete();
            $table->foreign('product_variant_id')
                ->references('id')
                ->on('product_variants')
                ->restrictOnDelete();
        });
    }
};
