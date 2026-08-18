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
        if (! Schema::hasTable('payroll_runs')) {
            return;
        }

        Schema::table('payroll_runs', function (Blueprint $table): void {
            if (! Schema::hasColumn('payroll_runs', 'nibss_reference')) {
                $table->string('nibss_reference')->nullable()->after('notes');
            }

            if (! Schema::hasColumn('payroll_runs', 'nibss_status')) {
                $table->string('nibss_status', 32)->nullable()->index()->after('nibss_reference');
            }

            if (! Schema::hasColumn('payroll_runs', 'nibss_submitted_at')) {
                $table->timestamp('nibss_submitted_at')->nullable()->after('nibss_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('payroll_runs')) {
            return;
        }

        Schema::table('payroll_runs', function (Blueprint $table): void {
            foreach (['nibss_submitted_at', 'nibss_status', 'nibss_reference'] as $column) {
                if (Schema::hasColumn('payroll_runs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
