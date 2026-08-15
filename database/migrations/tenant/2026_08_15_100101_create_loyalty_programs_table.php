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
        Schema::create('loyalty_programs', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->decimal('points_per_currency_unit', 12, 2)->default(1);
            $table->unsignedInteger('redemption_points_per_currency')->default(100);
            $table->unsignedInteger('min_redemption_points')->default(100);
            $table->decimal('max_redemption_percent', 5, 2)->default(100);
            $table->boolean('earn_on_order_paid')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_programs');
    }
};
