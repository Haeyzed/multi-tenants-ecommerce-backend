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
            $table->foreignId('manager_id')->nullable()->after('designation_id')->constrained('employees')->nullOnDelete();
            $table->string('employment_type')->nullable()->after('employment_status')->index();
            $table->string('work_location')->nullable()->after('employment_type');
            $table->date('terminated_at')->nullable()->after('hired_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('manager_id');
            $table->dropColumn(['employment_type', 'work_location', 'terminated_at']);
        });
    }
};
