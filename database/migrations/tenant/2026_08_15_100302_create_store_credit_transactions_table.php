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
        Schema::create('store_credit_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_credit_account_id')
                ->constrained('store_credit_accounts')
                ->cascadeOnDelete();
            $table->string('type');
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->nullableMorphs('reference');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['store_credit_account_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_credit_transactions');
    }
};
