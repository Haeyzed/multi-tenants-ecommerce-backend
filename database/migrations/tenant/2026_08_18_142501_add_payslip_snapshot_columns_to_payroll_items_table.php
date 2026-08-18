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
        if (! Schema::hasTable('payroll_items')) {
            return;
        }

        Schema::table('payroll_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('payroll_items', 'scheduled_days')) {
                $table->unsignedSmallInteger('scheduled_days')->default(0)->after('working_days');
            }

            if (! Schema::hasColumn('payroll_items', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('notes');
            }

            if (! Schema::hasColumn('payroll_items', 'bank_code')) {
                $table->string('bank_code', 20)->nullable()->after('bank_name');
            }

            if (! Schema::hasColumn('payroll_items', 'account_number')) {
                $table->string('account_number', 32)->nullable()->after('bank_code');
            }

            if (! Schema::hasColumn('payroll_items', 'account_name')) {
                $table->string('account_name')->nullable()->after('account_number');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('payroll_items')) {
            return;
        }

        Schema::table('payroll_items', function (Blueprint $table): void {
            foreach (['account_name', 'account_number', 'bank_code', 'bank_name', 'scheduled_days'] as $column) {
                if (Schema::hasColumn('payroll_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
