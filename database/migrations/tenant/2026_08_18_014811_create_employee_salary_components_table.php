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
        Schema::create('employee_salary_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_salary_id')->constrained('employee_salaries')->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('calculation')->default('fixed');
            $table->string('code');
            $table->string('label');
            $table->decimal('amount', 15, 2);
            $table->boolean('is_tax')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['employee_salary_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_salary_components');
    }
};
