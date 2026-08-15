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
        Schema::create('tax_zone_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tax_zone_id')
                ->constrained('tax_zones')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('state_id')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->timestamps();

            $table->index(['tax_zone_id', 'country_id', 'state_id', 'city_id'], 'tax_zone_locations_geo_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_zone_locations');
    }
};
