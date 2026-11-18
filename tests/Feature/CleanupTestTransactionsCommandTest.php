<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\OperationsService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CleanupTestTransactionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_lists_every_user_with_sales_and_changes_nothing(): void
    {
        $seller = User::factory()->create(['name' => 'Discovery seller', 'role' => 'EMPLOYEE', 'active' => true]);
        User::factory()->create(['name' => 'No sales user', 'role' => 'EMPLOYEE', 'active' => true]);
        $category = Category::create(['name' => 'Discovery category', 'active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Discovery product',
            'sku' => 'DISCOVERY-'.str()->uuid(),
            'slug' => 'discovery-'.str()->uuid(),
            'status' => 'ACTIVE',
            'active' => true,
        ]);
        app(PurchaseService::class)->create([
            'request_uuid' => (string) str()->uuid(),
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_cost_toman' => 100]],
        ], $seller->id);
        app(SaleService::class)->create([
            'request_uuid' => (string) str()->uuid(),
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'purchase_price_toman' => 100, 'unit_price_toman' => 175]],
        ], $seller->id);

        $this->artisan('paytakht:cleanup-test-transactions', ['--discover' => true])
            ->expectsOutputToContain('Discovery seller')
            ->doesntExpectOutputToContain('No sales user')
            ->expectsOutputToContain('DISCOVERY ONLY')
            ->assertSuccessful();

        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 1]);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_dry_run_is_read_only_and_confirmation_deletes_only_the_explicit_sale(): void
    {
        $operator = User::factory()->create(['name' => 'Mohsen', 'role' => 'EMPLOYEE', 'active' => true]);
        $realUser = User::factory()->create(['name' => 'Real operator', 'role' => 'EMPLOYEE', 'active' => true]);
        $category = Category::create(['name' => 'Cleanup test category', 'active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Cleanup test product',
            'sku' => 'CLEANUP-'.str()->uuid(),
            'slug' => 'cleanup-'.str()->uuid(),
            'status' => 'ACTIVE',
            'active' => true,
        ]);
        app(PurchaseService::class)->create([
            'request_uuid' => (string) str()->uuid(),
            'items' => [['product_id' => $product->id, 'quantity' => 4, 'unit_cost_toman' => 100]],
        ], $realUser->id);
        $testPurchase = app(PurchaseService::class)->create([
            'request_uuid' => (string) str()->uuid(),
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_cost_toman' => 100]],
        ], $operator->id);
        $sale = app(SaleService::class)->create([
            'request_uuid' => (string) str()->uuid(),
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'purchase_price_toman' => 100, 'unit_price_toman' => 150]],
        ], $operator->id);
        DB::table('expense_categories')->insert(['name' => 'Cleanup expense', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $expense = app(OperationsService::class)->expense([
            'request_uuid' => (string) str()->uuid(),
            'expense_category_id' => DB::table('expense_categories')->where('name', 'Cleanup expense')->value('id'),
            'amount_toman' => 25,
        ], $operator->id);

        $this->artisan('paytakht:cleanup-test-transactions')
            ->expectsOutputToContain((string) $sale->id)
            ->expectsOutputToContain('DRY RUN ONLY')
            ->assertSuccessful();
        $this->assertDatabaseHas('sales', ['id' => $sale->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 5]);

        $this->artisan('paytakht:cleanup-test-transactions', [
            '--confirm' => true,
            '--users' => [(string) $operator->id],
            '--sales' => [(string) $sale->id],
            '--purchases' => [(string) $testPurchase->id],
            '--expenses' => [(string) $expense->id],
        ])
            ->expectsOutputToContain('INTEGRITY CHECK PASSED')
            ->assertSuccessful();
        $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
        $this->assertDatabaseMissing('stock_movements', ['reference_type' => 'SALE', 'reference_id' => $sale->id]);
        $this->assertDatabaseMissing('purchases', ['id' => $testPurchase->id]);
        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 4]);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('products', 1);
    }
}
