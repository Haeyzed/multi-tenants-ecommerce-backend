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
        Schema::create('tax_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tax_id')
                ->constrained('taxes')
                ->cascadeOnDelete();
            $table->foreignId('tax_zone_id')
                ->constrained('tax_zones')
                ->cascadeOnDelete();
            $table->string('applies_to');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tax_id', 'tax_zone_id', 'applies_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_rules');
    }
};
