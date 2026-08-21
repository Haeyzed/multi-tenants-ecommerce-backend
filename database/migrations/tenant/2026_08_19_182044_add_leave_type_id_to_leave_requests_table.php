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
        if (! Schema::hasTable('leave_requests') || ! Schema::hasTable('leave_types')) {
            return;
        }

        if (! Schema::hasColumn('leave_requests', 'leave_type_id')) {
            Schema::table('leave_requests', function (Blueprint $table): void {
                $table->foreignId('leave_type_id')
                    ->nullable()
                    ->after('employee_id')
                    ->constrained('leave_types')
                    ->restrictOnDelete();
            });
        }

        if (Schema::hasColumn('leave_requests', 'type')) {
            DB::table('leave_requests')
                ->orderBy('id')
                ->lazyById()
                ->each(function (object $row): void {
                    if ($row->leave_type_id !== null) {
                        return;
                    }

                    $leaveTypeId = DB::table('leave_types')
                        ->where('code', $row->type)
                        ->value('id');

                    if ($leaveTypeId === null) {
                        return;
                    }

                    DB::table('leave_requests')
                        ->where('id', $row->id)
                        ->update(['leave_type_id' => $leaveTypeId]);
                });

            Schema::table('leave_requests', function (Blueprint $table): void {
                $table->dropIndex(['type']);
                $table->dropColumn('type');
            });
        }

        Schema::table('leave_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('leave_requests', 'leave_type_id')) {
                $table->index('leave_type_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('leave_requests') || ! Schema::hasTable('leave_types')) {
            return;
        }

        if (! Schema::hasColumn('leave_requests', 'type')) {
            Schema::table('leave_requests', function (Blueprint $table): void {
                $table->string('type')->nullable()->after('employee_id');
            });

            DB::table('leave_requests')
                ->orderBy('id')
                ->lazyById()
                ->each(function (object $row): void {
                    $code = DB::table('leave_types')
                        ->where('id', $row->leave_type_id)
                        ->value('code');

                    if ($code === null) {
                        return;
                    }

                    DB::table('leave_requests')
                        ->where('id', $row->id)
                        ->update(['type' => $code]);
                });

            Schema::table('leave_requests', function (Blueprint $table): void {
                $table->string('type')->nullable(false)->change();
                $table->index('type');
            });
        }

        if (Schema::hasColumn('leave_requests', 'leave_type_id')) {
            Schema::table('leave_requests', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('leave_type_id');
            });
        }
    }
};
