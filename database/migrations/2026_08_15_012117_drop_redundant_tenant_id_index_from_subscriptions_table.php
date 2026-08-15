<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Drop the standalone tenant_id index when present; (tenant_id, status) already covers it.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $sm = Schema::getConnection()->getSchemaBuilder();
            $indexes = $sm->getIndexes('subscriptions');

            foreach ($indexes as $index) {
                if (($index['name'] ?? null) === 'subscriptions_tenant_id_index') {
                    $table->dropIndex('subscriptions_tenant_id_index');

                    break;
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $sm = Schema::getConnection()->getSchemaBuilder();
            $indexes = $sm->getIndexes('subscriptions');
            $names = array_column($indexes, 'name');

            if (! in_array('subscriptions_tenant_id_index', $names, true)) {
                $table->index('tenant_id');
            }
        });
    }
};
