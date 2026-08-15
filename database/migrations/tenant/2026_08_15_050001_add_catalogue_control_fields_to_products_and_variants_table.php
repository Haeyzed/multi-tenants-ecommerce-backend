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
        Schema::table('products', function (Blueprint $table): void {
            $table->timestamp('unpublished_at')->nullable()->after('published_at');
            $table->boolean('allow_backorder')->default(false)->after('is_featured');
            $table->boolean('is_preorder')->default(false)->after('allow_backorder');
            $table->timestamp('preorder_start_at')->nullable()->after('is_preorder');
            $table->timestamp('preorder_end_at')->nullable()->after('preorder_start_at');
            $table->unsignedInteger('minimum_purchase_quantity')->nullable()->after('preorder_end_at');
            $table->unsignedInteger('maximum_purchase_quantity')->nullable()->after('minimum_purchase_quantity');
            $table->decimal('average_rating', 3, 2)->nullable()->after('maximum_purchase_quantity');
            $table->unsignedInteger('reviews_count')->default(0)->after('average_rating');
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->boolean('allow_backorder')->default(false)->after('is_active');
            $table->boolean('is_preorder')->default(false)->after('allow_backorder');
            $table->timestamp('preorder_start_at')->nullable()->after('is_preorder');
            $table->timestamp('preorder_end_at')->nullable()->after('preorder_start_at');
            $table->unsignedInteger('minimum_purchase_quantity')->nullable()->after('preorder_end_at');
            $table->unsignedInteger('maximum_purchase_quantity')->nullable()->after('minimum_purchase_quantity');
            $table->unsignedInteger('low_stock_threshold')->nullable()->after('maximum_purchase_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'unpublished_at',
                'allow_backorder',
                'is_preorder',
                'preorder_start_at',
                'preorder_end_at',
                'minimum_purchase_quantity',
                'maximum_purchase_quantity',
                'average_rating',
                'reviews_count',
            ]);
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropColumn([
                'allow_backorder',
                'is_preorder',
                'preorder_start_at',
                'preorder_end_at',
                'minimum_purchase_quantity',
                'maximum_purchase_quantity',
                'low_stock_threshold',
            ]);
        });
    }
};
