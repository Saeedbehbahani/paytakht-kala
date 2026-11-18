<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('average_cost_toman')->nullable()->default(null)->change();
            $table->unsignedBigInteger('category_id')->nullable()->change();
            $table->unsignedBigInteger('unknown_cost_quantity')->default(0);
        });
        Schema::table('sale_items', function (Blueprint $table) {
            $table->unsignedBigInteger('cost_snapshot_toman')->nullable()->change();
            $table->unsignedBigInteger('unknown_cost_quantity_snapshot')->default(0);
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->bigInteger('estimated_profit_toman')->nullable()->default(null)->change();
        });
        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->unsignedBigInteger('unknown_cost_quantity')->default(0);
        });
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->bigInteger('unknown_cost_quantity_delta')->default(0);
            $table->unsignedBigInteger('unknown_cost_quantity_after')->default(0);
        });
        Schema::create('initial_inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->string('identifier', 100)->unique();
            $table->string('checksum', 64);
            $table->string('status', 20)->default('PENDING');
            $table->unsignedInteger('source_rows');
            $table->unsignedInteger('source_quantity');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });
        Schema::create('initial_inventory_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('initial_inventory_batch_id')->constrained('initial_inventory_batches');
            $table->foreignId('product_id')->unique()->constrained('products');
            $table->unsignedInteger('source_row');
            $table->unsignedInteger('page');
            $table->string('source_name', 150);
            $table->unsignedBigInteger('quantity');
            $table->boolean('created_product');
            $table->timestamps();
            $table->unique(['initial_inventory_batch_id', 'source_row'], 'initial_inventory_source_unique');
        });
    }

    public function down(): void
    {
        // Restoring NOT NULL would fabricate values for unknown costs. Only permit
        // rollback while no unknown-cost state or applied bootstrap exists.
        if (DB::table('initial_inventory_batches')->exists()
            || DB::table('products')->where('unknown_cost_quantity', '>', 0)->exists()
            || DB::table('sale_items')->whereNull('cost_snapshot_toman')->exists()
            || DB::table('products')->whereNull('average_cost_toman')->exists()
            || DB::table('products')->whereNull('category_id')->exists()
            || DB::table('sales')->whereNull('estimated_profit_toman')->exists()) {
            throw new RuntimeException('Unknown-cost data exists; destructive rollback is forbidden.');
        }
        Schema::dropIfExists('initial_inventory_entries');
        Schema::dropIfExists('initial_inventory_batches');
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropColumn(['unknown_cost_quantity_delta', 'unknown_cost_quantity_after']));
        Schema::table('sale_return_items', fn (Blueprint $table) => $table->dropColumn('unknown_cost_quantity'));
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('unknown_cost_quantity_snapshot');
            $table->unsignedBigInteger('cost_snapshot_toman')->nullable(false)->change();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('unknown_cost_quantity');
            $table->unsignedBigInteger('average_cost_toman')->nullable(false)->default(0)->change();
            $table->unsignedBigInteger('category_id')->nullable(false)->change();
        });
        Schema::table('sales', fn (Blueprint $table) => $table->bigInteger('estimated_profit_toman')->nullable(false)->default(0)->change());
    }
};
