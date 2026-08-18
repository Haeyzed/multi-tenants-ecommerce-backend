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
        Schema::create('payroll_periods', function (Blueprint $table): void {
            $table->id();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('payment_date');
            $table->string('frequency')->index();
            $table->string('status')->default('open')->index();
            $table->timestamps();

            $table->unique(['period_start', 'period_end']);
            $table->index(['payment_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
