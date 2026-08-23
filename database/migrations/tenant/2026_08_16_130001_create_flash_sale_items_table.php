<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * MySQL cannot combine a foreign key on product_variant_id with a generated
     * column that references that same column (error 1215). Use a functional
     * unique index instead; MySQL requires the expression wrapped in parentheses.
     */
    public function up(): void
    {
        Schema::create('flash_sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('flash_sale_id')
                ->constrained('flash_sales')
                ->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->nullOnDelete();
            $table->decimal('sale_price', 14, 2);
            $table->unsignedInteger('qty_limit')->nullable();
            $table->unsignedInteger('sold_qty')->default(0);
            $table->unsignedInteger('per_customer_limit')->nullable();

            if (Schema::hasTable('customer_groups')) {
                $table->foreignId('customer_group_id')
                    ->nullable()
                    ->constrained('customer_groups')
                    ->nullOnDelete();
            } else {
                $table->unsignedBigInteger('customer_group_id')->nullable();
            }

            $table->timestamps();

            $table->index(['flash_sale_id', 'product_id']);
            $table->index(['product_id', 'product_variant_id']);
        });

        $coalesce = Schema::getConnection()->getDriverName() === 'sqlite'
            ? 'COALESCE(product_variant_id, 0)'
            : '(COALESCE(product_variant_id, 0))';

        DB::statement(
            "CREATE UNIQUE INDEX flash_sale_items_sale_product_variant_unique ON flash_sale_items (flash_sale_id, product_id, {$coalesce})"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flash_sale_items');
    }
};
