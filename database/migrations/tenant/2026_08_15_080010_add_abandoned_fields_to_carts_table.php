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
        Schema::table('carts', function (Blueprint $table): void {
            $table->timestamp('abandoned_at')->nullable()->after('expires_at');
            $table->timestamp('abandoned_notified_at')->nullable()->after('abandoned_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            $table->dropColumn(['abandoned_at', 'abandoned_notified_at']);
        });
    }
};
