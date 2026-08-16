<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('refunds')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite cannot reliably drop/alter this FK in place; recreate.
            Schema::table('refunds', function (Blueprint $table): void {
                $table->unsignedBigInteger('order_payment_id')->nullable()->change();
            });

            return;
        }

        Schema::table('refunds', function (Blueprint $table): void {
            $table->dropForeign(['order_payment_id']);
        });

        Schema::table('refunds', function (Blueprint $table): void {
            $table->unsignedBigInteger('order_payment_id')->nullable()->change();
        });

        Schema::table('refunds', function (Blueprint $table): void {
            $table->foreign('order_payment_id')
                ->references('id')
                ->on('order_payments')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('refunds')) {
            return;
        }

        // Non-null down-migration is unsafe when prepaid refunds exist; leave nullable.
        if (DB::table('refunds')->whereNull('order_payment_id')->exists()) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('refunds', function (Blueprint $table): void {
                $table->unsignedBigInteger('order_payment_id')->nullable(false)->change();
            });

            return;
        }

        Schema::table('refunds', function (Blueprint $table): void {
            $table->dropForeign(['order_payment_id']);
        });

        Schema::table('refunds', function (Blueprint $table): void {
            $table->unsignedBigInteger('order_payment_id')->nullable(false)->change();
        });

        Schema::table('refunds', function (Blueprint $table): void {
            $table->foreign('order_payment_id')
                ->references('id')
                ->on('order_payments')
                ->restrictOnDelete();
        });
    }
};
