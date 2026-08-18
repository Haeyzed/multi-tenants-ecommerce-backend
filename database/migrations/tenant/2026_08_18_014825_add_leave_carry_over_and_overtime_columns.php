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
        if (Schema::hasTable('leave_types') && ! Schema::hasColumn('leave_types', 'allow_carry_over')) {
            Schema::table('leave_types', function (Blueprint $table): void {
                $table->boolean('allow_carry_over')->default(false)->after('default_days');
            });
        }

        if (Schema::hasTable('leave_balances') && ! Schema::hasColumn('leave_balances', 'carried_over')) {
            Schema::table('leave_balances', function (Blueprint $table): void {
                $table->unsignedSmallInteger('carried_over')->default(0)->after('entitled');
            });
        }

        if (Schema::hasTable('attendances') && ! Schema::hasColumn('attendances', 'overtime_minutes')) {
            Schema::table('attendances', function (Blueprint $table): void {
                $table->unsignedSmallInteger('overtime_minutes')->default(0)->after('checked_out_at');
            });
        }

        if (Schema::hasTable('payroll_items') && ! Schema::hasColumn('payroll_items', 'overtime_minutes')) {
            Schema::table('payroll_items', function (Blueprint $table): void {
                $table->unsignedSmallInteger('overtime_minutes')->default(0)->after('unpaid_leave_days');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('leave_types') && Schema::hasColumn('leave_types', 'allow_carry_over')) {
            Schema::table('leave_types', function (Blueprint $table): void {
                $table->dropColumn('allow_carry_over');
            });
        }

        if (Schema::hasTable('leave_balances') && Schema::hasColumn('leave_balances', 'carried_over')) {
            Schema::table('leave_balances', function (Blueprint $table): void {
                $table->dropColumn('carried_over');
            });
        }

        if (Schema::hasTable('attendances') && Schema::hasColumn('attendances', 'overtime_minutes')) {
            Schema::table('attendances', function (Blueprint $table): void {
                $table->dropColumn('overtime_minutes');
            });
        }

        if (Schema::hasTable('payroll_items') && Schema::hasColumn('payroll_items', 'overtime_minutes')) {
            Schema::table('payroll_items', function (Blueprint $table): void {
                $table->dropColumn('overtime_minutes');
            });
        }
    }
};
