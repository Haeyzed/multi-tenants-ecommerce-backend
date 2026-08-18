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
        Schema::create('interview_meeting_provider_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 64);
            $table->boolean('enabled')->default(false)->index();
            $table->text('credentials')->nullable();
            $table->timestamps();

            $table->unique('provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interview_meeting_provider_settings');
    }
};
