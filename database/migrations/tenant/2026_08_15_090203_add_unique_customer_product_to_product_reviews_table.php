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
        Schema::table('product_reviews', function (Blueprint $table): void {
            $table->unique(['customer_id', 'product_id'], 'product_reviews_customer_product_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_reviews', function (Blueprint $table): void {
            ForeignKeyIndexHelper::dropForeignKeys($table, [
                'customer_id',
                'product_id',
            ]);

            $table->dropUnique('product_reviews_customer_product_unique');

            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->cascadeOnDelete();
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();
        });
    }
};
