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
        Schema::create('journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->text('description')->nullable();
            $table->date('entry_date');
            $table->string('status')->default('draft');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('entry_type')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('entry_date');
            $table->index(['source_type', 'source_id']);
            $table->unique(['source_type', 'source_id', 'entry_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
