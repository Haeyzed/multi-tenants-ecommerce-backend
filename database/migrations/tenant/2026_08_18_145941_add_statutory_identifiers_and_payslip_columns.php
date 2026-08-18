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
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table): void {
                if (! Schema::hasColumn('employees', 'pension_pin')) {
                    $table->string('pension_pin', 50)->nullable()->after('tax_id');
                }

                if (! Schema::hasColumn('employees', 'nhf_number')) {
                    $table->string('nhf_number', 50)->nullable()->after('pension_pin');
                }

                if (! Schema::hasColumn('employees', 'nsitf_number')) {
                    $table->string('nsitf_number', 50)->nullable()->after('nhf_number');
                }
            });
        }

        if (Schema::hasTable('payroll_items')) {
            Schema::table('payroll_items', function (Blueprint $table): void {
                if (! Schema::hasColumn('payroll_items', 'ytd_gross')) {
                    $table->decimal('ytd_gross', 15, 2)->default('0.00')->after('net_pay');
                }

                if (! Schema::hasColumn('payroll_items', 'ytd_paye')) {
                    $table->decimal('ytd_paye', 15, 2)->default('0.00')->after('ytd_gross');
                }

                if (! Schema::hasColumn('payroll_items', 'employer_pension')) {
                    $table->decimal('employer_pension', 15, 2)->default('0.00')->after('ytd_paye');
                }

                if (! Schema::hasColumn('payroll_items', 'employer_nsitf')) {
                    $table->decimal('employer_nsitf', 15, 2)->default('0.00')->after('employer_pension');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table): void {
                foreach (['nsitf_number', 'nhf_number', 'pension_pin'] as $column) {
                    if (Schema::hasColumn('employees', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('payroll_items')) {
            Schema::table('payroll_items', function (Blueprint $table): void {
                foreach (['employer_nsitf', 'employer_pension', 'ytd_paye', 'ytd_gross'] as $column) {
                    if (Schema::hasColumn('payroll_items', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
