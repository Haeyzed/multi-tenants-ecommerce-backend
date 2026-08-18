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
        Schema::create('employment_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->string('change_type')->index();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('job_title')->nullable();
            $table->string('employment_status');
            $table->string('employment_type')->nullable();
            $table->string('work_location')->nullable();
            $table->date('effective_on');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'effective_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employment_records');
    }
};
