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
        Schema::create('tax_table_bands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tax_table_id')->constrained('tax_tables')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->decimal('min_amount', 15, 2)->default(0);
            $table->decimal('max_amount', 15, 2)->nullable();
            $table->decimal('rate_percent', 8, 2);
            $table->timestamps();

            $table->index(['tax_table_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_table_bands');
    }
};
