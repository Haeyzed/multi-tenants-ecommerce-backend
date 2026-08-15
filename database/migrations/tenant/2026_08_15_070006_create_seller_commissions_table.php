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
        Schema::create('seller_commissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_order_id')
                ->constrained('seller_orders')
                ->cascadeOnDelete();
            $table->foreignId('seller_id')
                ->constrained('sellers')
                ->restrictOnDelete();
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();
            $table->string('commission_type');
            $table->decimal('commission_rate', 8, 4)->nullable();
            $table->decimal('commission_fixed_amount', 14, 2)->nullable();
            $table->decimal('order_subtotal', 14, 2);
            $table->decimal('commission_amount', 14, 2);
            $table->decimal('seller_amount', 14, 2);
            $table->string('status')->default('pending');
            $table->timestamp('earned_at')->nullable();
            $table->timestamps();

            $table->unique('seller_order_id');
            $table->index(['seller_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_commissions');
    }
};
