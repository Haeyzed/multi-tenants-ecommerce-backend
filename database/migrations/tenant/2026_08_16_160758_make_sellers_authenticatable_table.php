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
        Schema::table('sellers', function (Blueprint $table): void {
            $table->string('password')->nullable()->after('phone');
            $table->timestamp('email_verified_at')->nullable()->after('password');
            $table->timestamp('last_login_at')->nullable()->after('email_verified_at');
            $table->rememberToken()->after('last_login_at');
        });

        $sellersWithoutEmail = DB::table('sellers')
            ->where(function ($query): void {
                $query->whereNull('email')->orWhere('email', '');
            })
            ->get(['id']);

        foreach ($sellersWithoutEmail as $seller) {
            DB::table('sellers')->where('id', $seller->id)->update([
                'email' => 'seller-'.$seller->id.'@pending.local',
            ]);
        }

        Schema::table('sellers', function (Blueprint $table): void {
            $table->string('email')->nullable(false)->change();
            $table->unique('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table): void {
            $table->dropUnique(['email']);
        });

        Schema::table('sellers', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
            $table->dropColumn(['password', 'email_verified_at', 'last_login_at', 'remember_token']);
        });
    }
};
