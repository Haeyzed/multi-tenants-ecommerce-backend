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
        Schema::create('customer_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique('name');
            $table->index(['is_active', 'sort_order']);
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignId('customer_group_id')
                ->nullable()
                ->after('status')
                ->constrained('customer_groups')
                ->nullOnDelete();
        });

        Schema::table('coupons', function (Blueprint $table): void {
            $table->foreignId('customer_group_id')
                ->nullable()
                ->after('is_active')
                ->constrained('customer_groups')
                ->nullOnDelete();
        });

        Schema::table('customer_segments', function (Blueprint $table): void {
            $table->unsignedInteger('customers_count')->default(0)->after('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_segments', function (Blueprint $table): void {
            $table->dropColumn('customers_count');
        });

        Schema::table('coupons', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_group_id');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_group_id');
        });

        Schema::dropIfExists('customer_groups');
    }
};
