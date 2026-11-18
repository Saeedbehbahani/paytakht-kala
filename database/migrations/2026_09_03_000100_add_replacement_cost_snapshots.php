<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('last_replacement_cost_toman')->nullable()->after('last_sale_price_toman');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->unsignedBigInteger('replacement_cost_snapshot_toman')->nullable()->after('cost_snapshot_toman');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('replacement_cost_snapshot_toman');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('last_replacement_cost_toman');
        });
    }
};
