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
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('name')->after('id');
            $table->string('slug')->unique()->after('name');
            $table->string('email')->nullable()->after('slug');
            $table->string('phone', 50)->nullable()->after('email');
            $table->string('status')->default('pending')->index()->after('phone');
            $table->boolean('is_active')->default(false)->index()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['name', 'slug', 'email', 'phone', 'status', 'is_active']);
        });
    }
};
