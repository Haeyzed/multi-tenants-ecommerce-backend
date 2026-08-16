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
            $table->string('sales_channel')->default('online')->after('customer_id');
            $table->foreignId('pos_terminal_id')->nullable()->after('sales_channel')->constrained('pos_terminals')->nullOnDelete();
            $table->foreignId('pos_session_id')->nullable()->after('pos_terminal_id')->constrained('pos_sessions')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->after('pos_session_id')->constrained('warehouses')->nullOnDelete();

            $table->index('sales_channel');
            $table->index('pos_terminal_id');
            $table->index('pos_session_id');
            $table->index('warehouse_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropConstrainedForeignId('pos_session_id');
            $table->dropConstrainedForeignId('pos_terminal_id');
            $table->dropColumn('sales_channel');
        });
    }
};
