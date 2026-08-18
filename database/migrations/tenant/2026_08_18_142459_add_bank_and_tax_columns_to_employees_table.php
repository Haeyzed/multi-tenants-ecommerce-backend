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
        if (! Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table): void {
            if (! Schema::hasColumn('employees', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('notes');
            }

            if (! Schema::hasColumn('employees', 'bank_code')) {
                $table->string('bank_code', 20)->nullable()->after('bank_name');
            }

            if (! Schema::hasColumn('employees', 'account_number')) {
                $table->string('account_number', 32)->nullable()->after('bank_code');
            }

            if (! Schema::hasColumn('employees', 'account_name')) {
                $table->string('account_name')->nullable()->after('account_number');
            }

            if (! Schema::hasColumn('employees', 'tax_id')) {
                $table->string('tax_id', 50)->nullable()->after('account_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table): void {
            foreach (['tax_id', 'account_name', 'account_number', 'bank_code', 'bank_name'] as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
