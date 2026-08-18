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
        Schema::create('tax_tables', function (Blueprint $table): void {
            $table->id();
            $table->char('country_code', 2)->index();
            $table->string('name');
            $table->unsignedSmallInteger('year')->index();
            $table->char('currency', 3)->default('NGN');
            $table->boolean('is_active')->default(true)->index();
            $table->decimal('relief_percent', 8, 2)->default(0);
            $table->decimal('relief_fixed', 15, 2)->default(0);
            $table->decimal('relief_minimum_percent', 8, 2)->default(0);
            $table->decimal('personal_allowance', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['country_code', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_tables');
    }
};
