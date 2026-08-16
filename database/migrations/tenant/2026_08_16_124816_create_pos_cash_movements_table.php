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
        Schema::create('pos_cash_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pos_session_id')->constrained('pos_sessions')->cascadeOnDelete();
            $table->string('type');
            $table->decimal('amount', 15, 2);
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['pos_session_id', 'type']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_cash_movements');
    }
};
