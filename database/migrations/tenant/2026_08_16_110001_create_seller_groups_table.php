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
        Schema::create('seller_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('commission_type')->nullable();
            $table->decimal('commission_rate', 8, 4)->nullable();
            $table->decimal('commission_fixed_amount', 14, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique('name');
            $table->index(['is_active', 'sort_order']);
        });

        Schema::table('sellers', function (Blueprint $table): void {
            $table->foreignId('seller_group_id')
                ->nullable()
                ->after('commission_fixed_amount')
                ->constrained('seller_groups')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('seller_group_id');
        });

        Schema::dropIfExists('seller_groups');
    }
};
