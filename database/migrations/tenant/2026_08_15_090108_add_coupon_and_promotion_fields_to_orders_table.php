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
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('coupon_id')
                ->nullable()
                ->after('discount_total')
                ->constrained('coupons')
                ->nullOnDelete();
            $table->string('coupon_code')->nullable()->after('coupon_id');
            $table->json('promotion_snapshot')->nullable()->after('coupon_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['coupon_code', 'promotion_snapshot']);
        });
    }
};
