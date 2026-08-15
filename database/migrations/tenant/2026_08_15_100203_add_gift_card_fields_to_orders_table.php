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
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('gift_card_id')
                ->nullable()
                ->after('grand_total')
                ->constrained('gift_cards')
                ->nullOnDelete();
            $table->decimal('gift_card_amount', 14, 2)->nullable()->after('gift_card_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('gift_card_id');
            $table->dropColumn('gift_card_amount');
        });
    }
};
