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
        Schema::create('seller_payouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_id')
                ->constrained('sellers')
                ->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3);
            $table->string('status')->default('pending');
            $table->string('idempotency_key')->unique();
            $table->string('reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'status']);
        });

        Schema::create('seller_payout_commission', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_payout_id')
                ->constrained('seller_payouts')
                ->cascadeOnDelete();
            $table->foreignId('seller_commission_id')
                ->constrained('seller_commissions')
                ->cascadeOnDelete();

            $table->unique('seller_commission_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_payout_commission');
        Schema::dropIfExists('seller_payouts');
    }
};
