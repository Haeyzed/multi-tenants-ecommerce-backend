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
        Schema::create('checkout_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();
            $table->foreignId('cart_id')
                ->nullable()
                ->constrained('carts')
                ->nullOnDelete();
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('status');
            $table->char('currency', 3);
            $table->foreignId('shipping_address_id')
                ->nullable()
                ->constrained('customer_addresses')
                ->nullOnDelete();
            $table->foreignId('billing_address_id')
                ->nullable()
                ->constrained('customer_addresses')
                ->nullOnDelete();
            $table->foreignId('shipping_method_id')
                ->nullable()
                ->constrained('shipping_methods')
                ->nullOnDelete();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('shipping_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkout_sessions');
    }
};
