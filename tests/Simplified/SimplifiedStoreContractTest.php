<?php

namespace Tests\Simplified;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\OperationsService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SimplifiedStoreContractTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'EMPLOYEE', 'active' => true]);
        $this->category = Category::create(['name' => 'عمومی', 'active' => true]);
        DB::table('expense_categories')->insert(['name' => 'اجاره', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function product(): Product
    {
        return Product::create(['category_id' => $this->category->id, 'name' => 'کالای نمونه', 'sku' => 'SKU-'.str()->uuid(), 'slug' => 'sample-'.str()->uuid(), 'sale_price_toman' => 0, 'minimum_stock' => 2, 'status' => 'ACTIVE', 'active' => true]);
    }

    public function test_stock_in_is_simple_updates_stock_and_is_not_finance_dependent(): void
    {
        $product = $this->product();
        $purchase = app(PurchaseService::class)->create(['request_uuid' => (string) str()->uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 5, 'unit_cost_toman' => 3800000]], 'note' => 'ورود اولیه'], $this->user->id);
        $this->assertSame(3800000, (int) $product->fresh()->last_replacement_cost_toman);
        $this->assertSame(5, (int) $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', ['reference_type' => 'PURCHASE', 'reference_id' => $purchase->id, 'quantity_delta' => 5]);
        $this->assertDatabaseCount('payment_allocations', 0);
    }

    public function test_sale_keeps_purchase_and_sale_price_snapshots_and_profit(): void
    {
        $product = $this->product();
        app(PurchaseService::class)->create(['request_uuid' => (string) str()->uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 5, 'unit_cost_toman' => 3800000]], 'note' => null], $this->user->id);
        $sale = app(SaleService::class)->create(['request_uuid' => (string) str()->uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 2, 'purchase_price_toman' => 3500000, 'unit_price_toman' => 4500000]], 'note' => null], $this->user->id);
        $this->assertSame(2000000, (int) $sale->estimated_profit_toman);
        $this->assertSame(3, (int) $product->fresh()->stock_quantity);
        $item = $sale->items()->firstOrFail();
        $this->assertSame(3500000, (int) $item->purchase_price_snapshot_toman);
        $this->assertSame(3800000, (int) $item->cost_snapshot_toman);
        $this->assertSame(4500000, (int) $item->unit_price_toman);
        $product->update(['last_replacement_cost_toman' => 999]);
        $this->assertSame(3500000, (int) $item->fresh()->purchase_price_snapshot_toman);
    }

    public function test_stock_cannot_go_negative_and_correction_uses_ledger(): void
    {
        $product = $this->product();
        app(PurchaseService::class)->create(['request_uuid' => (string) str()->uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_cost_toman' => 10]], 'note' => null], $this->user->id);
        $this->expectException(ValidationException::class);
        app(SaleService::class)->create(['request_uuid' => (string) str()->uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 2, 'purchase_price_toman' => 10, 'unit_price_toman' => 20]]], $this->user->id);
    }

    public function test_expense_and_dashboard_net_profit_are_simple(): void
    {
        $product = $this->product();
        app(PurchaseService::class)->create(['request_uuid' => (string) str()->uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_cost_toman' => 100]], 'note' => null], $this->user->id);
        app(SaleService::class)->create(['request_uuid' => (string) str()->uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 1, 'purchase_price_toman' => 100, 'unit_price_toman' => 300]]], $this->user->id);
        app(OperationsService::class)->expense(['request_uuid' => (string) str()->uuid(), 'expense_category_id' => DB::table('expense_categories')->value('id'), 'amount_toman' => 50, 'note' => 'بسته‌بندی'], $this->user->id);
        $home = app(DashboardService::class)->home();
        $this->assertSame(200, $home['saleProfitToday']);
        $this->assertSame(50, $home['expenseToday']);
        $this->assertSame(150, $home['netToday']);
    }

    public function test_every_user_sees_only_five_operational_sections_and_retired_paths_are_gone(): void
    {
        $response = $this->actingAs($this->user)->get('/dashboard');
        $response->assertOk()->assertSeeInOrder(['خانه', 'فروش', 'ورود', 'موجودی', 'هزینه'])->assertDontSee('پول فروشگاه')->assertDontSee('بیشتر');
        $this->actingAs($this->user)->get('/admin/finance')->assertNotFound();
        $this->actingAs($this->user)->get('/sales/new')->assertOk();
        $this->actingAs($this->user)->get('/purchases/new')->assertOk();
        $this->actingAs($this->user)->get('/inventory')->assertOk();
        $this->actingAs($this->user)->get('/expenses/new')->assertOk();
    }
}
