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
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->foreignId('payroll_period_id')->nullable()->after('id')->constrained('payroll_periods')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payroll_period_id');
        });
    }
};
