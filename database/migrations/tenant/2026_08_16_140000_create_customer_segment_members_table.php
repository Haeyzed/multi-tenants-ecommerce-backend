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
        Schema::create('customer_segment_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_segment_id')->constrained('customer_segments')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->timestamp('entered_at')->useCurrent();
            $table->timestamps();

            $table->unique(['customer_segment_id', 'customer_id'], 'customer_segment_members_unique');
            $table->index(['customer_id', 'customer_segment_id']);
        });

        Schema::table('customer_segments', function (Blueprint $table): void {
            $table->timestamp('membership_refreshed_at')->nullable()->after('customers_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_segments', function (Blueprint $table): void {
            $table->dropColumn('membership_refreshed_at');
        });

        Schema::dropIfExists('customer_segment_members');
    }
};
