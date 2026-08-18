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
        if (! Schema::hasTable('attendances')) {
            return;
        }

        Schema::table('attendances', function (Blueprint $table): void {
            if (! Schema::hasColumn('attendances', 'clock_source')) {
                $table->string('clock_source', 32)->default('web')->after('overtime_rate_percent');
            }

            if (! Schema::hasColumn('attendances', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('clock_source');
            }

            if (! Schema::hasColumn('attendances', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }

            if (! Schema::hasColumn('attendances', 'accuracy_meters')) {
                $table->unsignedInteger('accuracy_meters')->nullable()->after('longitude');
            }

            if (! Schema::hasColumn('attendances', 'device_id')) {
                $table->string('device_id', 100)->nullable()->after('accuracy_meters');
            }

            if (! Schema::hasColumn('attendances', 'biometric_hash')) {
                $table->string('biometric_hash', 64)->nullable()->after('device_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('attendances')) {
            return;
        }

        Schema::table('attendances', function (Blueprint $table): void {
            foreach (['biometric_hash', 'device_id', 'accuracy_meters', 'longitude', 'latitude', 'clock_source'] as $column) {
                if (Schema::hasColumn('attendances', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
