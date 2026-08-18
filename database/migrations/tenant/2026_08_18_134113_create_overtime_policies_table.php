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
        Schema::create('overtime_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('weekday_rate_percent')->default(150);
            $table->unsignedSmallInteger('weekend_rate_percent')->default(200);
            $table->unsignedSmallInteger('holiday_rate_percent')->default(200);
            $table->unsignedSmallInteger('daily_threshold_minutes')->default(0);
            $table->unsignedSmallInteger('max_daily_minutes')->default(0);
            $table->unsignedTinyInteger('round_to_minutes')->default(1);
            $table->timestamps();

            $table->unique('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overtime_policies');
    }
};
