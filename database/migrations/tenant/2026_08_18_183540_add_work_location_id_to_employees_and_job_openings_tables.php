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
        Schema::table('employees', function (Blueprint $table): void {
            $table->foreignId('work_location_id')->nullable()->after('work_schedule_id')->constrained('work_locations')->nullOnDelete();
        });

        Schema::table('job_openings', function (Blueprint $table): void {
            $table->foreignId('work_location_id')->nullable()->after('designation_id')->constrained('work_locations')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_openings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('work_location_id');
        });

        Schema::table('employees', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('work_location_id');
        });
    }
};
