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
        Schema::table('flash_sale_items', function (Blueprint $table): void {
            if (Schema::hasTable('customer_segments')) {
                $table->foreignId('customer_segment_id')
                    ->nullable()
                    ->after('customer_group_id')
                    ->constrained('customer_segments')
                    ->nullOnDelete();
            } else {
                $table->unsignedBigInteger('customer_segment_id')->nullable()->after('customer_group_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flash_sale_items', function (Blueprint $table): void {
            if (Schema::hasTable('customer_segments')) {
                $table->dropConstrainedForeignId('customer_segment_id');
            } else {
                $table->dropColumn('customer_segment_id');
            }
        });
    }
};
