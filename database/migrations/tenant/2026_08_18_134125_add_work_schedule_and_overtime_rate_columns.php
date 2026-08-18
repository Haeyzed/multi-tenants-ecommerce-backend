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
        if (Schema::hasTable('work_schedules') && ! Schema::hasColumn('work_schedules', 'overtime_policy_id')) {
            Schema::table('work_schedules', function (Blueprint $table): void {
                $table->foreignId('overtime_policy_id')->nullable()->after('is_active')->constrained('overtime_policies')->nullOnDelete();
            });
        }

        if (Schema::hasTable('employees') && ! Schema::hasColumn('employees', 'work_schedule_id')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->foreignId('work_schedule_id')->nullable()->after('manager_id')->constrained('work_schedules')->nullOnDelete();
            });
        }

        if (Schema::hasTable('attendances') && ! Schema::hasColumn('attendances', 'overtime_rate_percent')) {
            Schema::table('attendances', function (Blueprint $table): void {
                $table->unsignedSmallInteger('overtime_rate_percent')->default(0)->after('overtime_minutes');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('attendances') && Schema::hasColumn('attendances', 'overtime_rate_percent')) {
            Schema::table('attendances', function (Blueprint $table): void {
                $table->dropColumn('overtime_rate_percent');
            });
        }

        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'work_schedule_id')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('work_schedule_id');
            });
        }

        if (Schema::hasTable('work_schedules') && Schema::hasColumn('work_schedules', 'overtime_policy_id')) {
            Schema::table('work_schedules', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('overtime_policy_id');
            });
        }
    }
};
