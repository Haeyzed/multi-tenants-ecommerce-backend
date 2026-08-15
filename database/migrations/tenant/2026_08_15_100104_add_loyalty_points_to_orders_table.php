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
            $table->unsignedInteger('loyalty_points_earned')->nullable()->after('promotion_snapshot');
            $table->unsignedInteger('loyalty_points_redeemed')->nullable()->after('loyalty_points_earned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['loyalty_points_earned', 'loyalty_points_redeemed']);
        });
    }
};
