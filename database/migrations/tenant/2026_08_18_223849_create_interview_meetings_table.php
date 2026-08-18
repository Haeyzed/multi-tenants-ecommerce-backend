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
        Schema::create('interview_meetings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('interview_id')->constrained('interviews')->cascadeOnDelete();
            $table->string('provider', 64)->index();
            $table->string('external_id')->nullable()->index();
            $table->string('join_url', 2048)->nullable();
            $table->string('host_url', 2048)->nullable();
            $table->text('password')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 32)->default('created')->index();
            $table->boolean('is_current')->default(true)->index();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['interview_id', 'is_current']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interview_meetings');
    }
};
