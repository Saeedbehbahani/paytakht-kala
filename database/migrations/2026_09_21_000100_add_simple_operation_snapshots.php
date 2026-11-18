<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_price_snapshot_toman')->nullable();
        });
        foreach (['sales', 'purchases', 'expenses'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dateTime('business_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        // These fields contain operational history. Do not discard it on rollback.
        throw new RuntimeException('Snapshot and operation dates must be preserved.');
    }
};
