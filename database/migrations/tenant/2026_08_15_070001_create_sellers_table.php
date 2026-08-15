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
        Schema::create('sellers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('inactive');
            $table->string('verification_status')->default('pending');
            $table->string('commission_type')->nullable();
            $table->decimal('commission_rate', 8, 4)->nullable();
            $table->decimal('commission_fixed_amount', 14, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'verification_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sellers');
    }
};
