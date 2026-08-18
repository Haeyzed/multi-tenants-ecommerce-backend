<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('job_openings')) {
            return;
        }

        DB::table('job_openings')->where('status', 'open')->update(['status' => 'published']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('job_openings')) {
            return;
        }

        DB::table('job_openings')->where('status', 'published')->update(['status' => 'open']);
    }
};
