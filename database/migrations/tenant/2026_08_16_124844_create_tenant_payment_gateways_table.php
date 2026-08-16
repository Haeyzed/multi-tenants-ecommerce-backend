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
        Schema::create('tenant_payment_gateways', function (Blueprint $table): void {
            $table->id();
            $table->string('gateway');
            $table->boolean('is_enabled')->default(false)->index();
            $table->text('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();

            $table->unique('gateway');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_payment_gateways');
    }
};
