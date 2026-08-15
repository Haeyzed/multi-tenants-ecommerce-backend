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
        Schema::create('seller_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();
            $table->foreignId('seller_id')
                ->constrained('sellers')
                ->restrictOnDelete();
            $table->string('status')->default('pending');
            $table->decimal('subtotal', 14, 2);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('shipping_total', 14, 2)->default(0);
            $table->decimal('commission_total', 14, 2)->default(0);
            $table->decimal('seller_total', 14, 2)->default(0);
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'seller_id']);
            $table->index(['seller_id', 'status']);
        });

        Schema::create('seller_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_order_id')
                ->constrained('seller_orders')
                ->cascadeOnDelete();
            $table->foreignId('order_item_id')
                ->constrained('order_items')
                ->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 14, 2);
            $table->decimal('subtotal', 14, 2);
            $table->decimal('total', 14, 2);
            $table->timestamps();

            $table->unique('order_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_order_items');
        Schema::dropIfExists('seller_orders');
    }
};
