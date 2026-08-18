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
        Schema::table('job_openings', function (Blueprint $table): void {
            $table->string('slug')->nullable()->unique()->after('title');
            $table->text('short_description')->nullable()->after('description');
            $table->string('employment_type', 32)->nullable()->after('designation_id');
            $table->string('work_location')->nullable()->after('employment_type');
            $table->string('remote_type', 32)->nullable()->after('work_location');
            $table->string('experience_level')->nullable()->after('remote_type');
            $table->decimal('salary_min', 12, 2)->nullable()->after('openings_count');
            $table->decimal('salary_max', 12, 2)->nullable()->after('salary_min');
            $table->string('salary_currency', 3)->default('NGN')->after('salary_max');
            $table->text('requirements')->nullable()->after('short_description');
            $table->text('responsibilities')->nullable()->after('requirements');
            $table->text('qualifications')->nullable()->after('responsibilities');
            $table->json('skills')->nullable()->after('qualifications');
            $table->text('benefits')->nullable()->after('skills');
            $table->timestamp('published_at')->nullable()->after('closes_at');
            $table->timestamp('closed_at')->nullable()->after('published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_openings', function (Blueprint $table): void {
            $table->dropColumn([
                'slug',
                'short_description',
                'employment_type',
                'work_location',
                'remote_type',
                'experience_level',
                'salary_min',
                'salary_max',
                'salary_currency',
                'requirements',
                'responsibilities',
                'qualifications',
                'skills',
                'benefits',
                'published_at',
                'closed_at',
            ]);
        });
    }
};
