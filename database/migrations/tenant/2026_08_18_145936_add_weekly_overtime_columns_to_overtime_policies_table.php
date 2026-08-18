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
        if (! Schema::hasTable('overtime_policies')) {
            return;
        }

        Schema::table('overtime_policies', function (Blueprint $table): void {
            if (! Schema::hasColumn('overtime_policies', 'weekly_threshold_minutes')) {
                $table->unsignedSmallInteger('weekly_threshold_minutes')->default(0)->after('max_daily_minutes');
            }

            if (! Schema::hasColumn('overtime_policies', 'weekly_rate_percent')) {
                $table->unsignedSmallInteger('weekly_rate_percent')->default(150)->after('weekly_threshold_minutes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('overtime_policies')) {
            return;
        }

        Schema::table('overtime_policies', function (Blueprint $table): void {
            foreach (['weekly_rate_percent', 'weekly_threshold_minutes'] as $column) {
                if (Schema::hasColumn('overtime_policies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
