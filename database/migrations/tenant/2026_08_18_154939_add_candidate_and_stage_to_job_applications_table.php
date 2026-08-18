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
        Schema::table('job_applications', function (Blueprint $table): void {
            $table->foreignId('candidate_id')->nullable()->after('job_opening_id')->constrained('candidates')->restrictOnDelete();
            $table->foreignId('recruitment_stage_id')->nullable()->after('candidate_id')->constrained('recruitment_stages')->nullOnDelete();
            $table->string('source', 32)->nullable()->after('phone');
            $table->timestamp('applied_at')->nullable()->after('source');

            $table->index('recruitment_stage_id');
            $table->unique(['job_opening_id', 'candidate_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table): void {
            $table->dropUnique(['job_opening_id', 'candidate_id']);
            $table->dropConstrainedForeignId('candidate_id');
            $table->dropConstrainedForeignId('recruitment_stage_id');
            $table->dropColumn(['source', 'applied_at']);
        });
    }
};
