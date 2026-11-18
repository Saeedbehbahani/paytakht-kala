<?php

namespace Tests\Simplified;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use App\Services\SimpleReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OperationalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'ADMIN', 'active' => true]);
        // This suite exercises privileged stock, purchase, expense, and reporting
        // workflows. Employee authorization boundaries are covered separately.
        $this->employee = User::factory()->create(['role' => 'ADMIN', 'active' => true]);
        $this->category = Category::create(['name' => 'عمومی', 'active' => true]);
        DB::table('expense_categories')->insert(['name' => 'اجاره', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function product(string $sku = 'SAMPLE'): Product
    {
        return Product::create(['category_id' => $this->category->id, 'name' => 'کالای آزمایشی', 'sku' => $sku, 'slug' => strtolower($sku), 'sale_price_toman' => 4500000, 'minimum_stock' => 3, 'status' => 'ACTIVE', 'active' => true]);
    }

    public function test_admin_operational_pages_exist_and_retired_routes_are_absent(): void
    {
        foreach ([$this->admin, $this->employee] as $user) {
            foreach (['/dashboard', '/sales/new', '/purchases/new', '/inventory', '/expenses/new', '/reports', '/products'] as $path) {
                $this->actingAs($user)->get($path)->assertOk();
            }
        }
        foreach (['/admin/finance', '/admin/reports', '/closing', '/cheques', '/installments', '/payroll', '/__retired-admin/finance'] as $path) {
            $this->actingAs($this->admin)->get($path)->assertNotFound();
        }
        $this->actingAs($this->employee)->get('/purchases/new')->assertDontSee('funding_source')->assertDontSee('payment_method')->assertDontSee('shipping_cost_toman');
        $this->actingAs($this->employee)->get('/expenses/new')->assertDontSee('financial_account_id')->assertDontSee('payment_method');
        $this->assertSame(5, substr_count(file_get_contents(resource_path('views/layouts/app.blade.php')), 'request()->routeIs'));
    }

    public function test_end_to_end_create_stock_in_sale_expense_and_histories(): void
    {
        $this->actingAs($this->employee)->post('/products', ['name' => 'جاروبرقی', 'sku' => 'VAC-1', 'category_id' => $this->category->id, 'minimum_stock' => 3])->assertRedirect();
        $product = Product::where('sku', 'VAC-1')->firstOrFail();
        $this->assertSame('ACTIVE', $product->status);
        $this->actingAs($this->employee)->patch("/products/{$product->id}", ['name' => 'جاروبرقی جدید', 'sku' => 'VAC-1', 'category_id' => $this->category->id, 'minimum_stock' => 3])->assertRedirect();
        $this->actingAs($this->employee)->post('/purchases', [
            'request_uuid' => (string) str()->uuid(), 'business_at' => now()->format('Y-m-d\TH:i'),
            'items' => [['product_id' => $product->id, 'quantity' => 5, 'unit_cost_toman' => 3800000]],
        ])->assertRedirect();
        $this->assertSame(5, (int) $product->fresh()->stock_quantity);
        $this->assertSame(3800000, (int) $product->fresh()->last_replacement_cost_toman);
        $this->actingAs($this->employee)->get('/sales/new')->assertSee('data-product-search', false)->assertDontSee('data-cost=', false)->assertSee('قیمت خرید را برای این تراکنش دستی وارد کن');
        $this->actingAs($this->employee)->post('/sales', [
            'request_uuid' => (string) str()->uuid(), 'business_at' => now()->format('Y-m-d\TH:i'),
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'purchase_price_toman' => 3800000, 'unit_price_toman' => 4500000]],
        ])->assertRedirect();
        $this->assertSame(3, (int) $product->fresh()->stock_quantity);
        $this->assertSame(1400000, (int) DB::table('sales')->value('estimated_profit_toman'));
        $this->actingAs($this->employee)->post('/expenses', [
            'request_uuid' => (string) str()->uuid(), 'business_at' => now()->format('Y-m-d\TH:i'),
            'expense_category_id' => DB::table('expense_categories')->value('id'), 'amount_toman' => 100000,
        ])->assertRedirect();
        $this->actingAs($this->employee)->get('/dashboard')->assertSee('1,300,000');
        $this->actingAs($this->employee)->get('/reports')->assertSee('1,300,000');
        $this->actingAs($this->employee)->get('/purchases')->assertSee('جاروبرقی جدید');
        $this->actingAs($this->employee)->get('/sales')->assertSee('جاروبرقی جدید');
        $this->actingAs($this->employee)->get('/expenses/new')->assertSee('اجاره');
        $this->actingAs($this->employee)->get("/products/{$product->id}")->assertSee('تاریخچه کالا')->assertSee('ورود کالا')->assertSee('فروش');
        $this->assertDatabaseCount('payment_allocations', 0);
        $this->assertSame(0, DB::table('financial_movements')->count());
        $this->artisan('paytakht:check-integrity')->assertExitCode(0);
    }

    public function test_stock_in_new_product_is_active_idempotent_and_independent_of_liquidity(): void
    {
        $uuid = (string) str()->uuid();
        $request = ['request_uuid' => $uuid, 'business_at' => now()->format('Y-m-d\TH:i'), 'new_product' => ['name' => 'کالای تازه', 'sku' => 'NEW-1', 'quantity' => 5, 'unit_cost_toman' => 3800000]];
        $this->actingAs($this->employee)->post('/purchases', $request)->assertRedirect();
        $this->actingAs($this->employee)->post('/purchases', $request)->assertRedirect();
        $this->assertSame(1, Product::where('sku', 'NEW-1')->count());
        $this->assertSame(5, (int) Product::where('sku', 'NEW-1')->value('stock_quantity'));
        $this->assertDatabaseCount('purchases', 1);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_unknown_legacy_stock_sells_without_fabricating_accounting_cost(): void
    {
        $product = $this->product();
        $product->forceFill(['stock_quantity' => 2, 'unknown_cost_quantity' => 2, 'average_cost_toman' => null, 'last_replacement_cost_toman' => 100])->save();
        $sale = app(SaleService::class)->create(['request_uuid' => (string) str()->uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 1, 'purchase_price_toman' => 100, 'unit_price_toman' => 150]]], $this->employee->id);
        $this->assertSame(50, (int) $sale->estimated_profit_toman);
        $this->assertNull($sale->items()->first()->cost_snapshot_toman);
        $this->assertSame(1, (int) $product->fresh()->unknown_cost_quantity);
    }

    public function test_stock_correction_is_ledger_safe_and_low_stock_is_visible(): void
    {
        $product = $this->product();
        app(PurchaseService::class)->create(['request_uuid' => (string) str()->uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 5, 'unit_cost_toman' => 100]]], $this->employee->id);
        $uuid = (string) str()->uuid();
        app(InventoryService::class)->adjust($uuid, $product->id, 2, 'شمارش فیزیکی', $this->employee->id);
        app(InventoryService::class)->adjust($uuid, $product->id, 2, 'شمارش فیزیکی', $this->employee->id);
        $this->assertSame(2, (int) $product->fresh()->stock_quantity);
        $this->assertSame(2, DB::table('stock_movements')->count());
        $this->actingAs($this->employee)->get('/inventory')->assertSee('کم‌موجود');
        $this->actingAs($this->employee)->get("/products/{$product->id}")->assertSee('شمارش فیزیکی');
    }

    public function test_reports_use_sale_snapshots_and_date_range(): void
    {
        $product = $this->product();
        app(PurchaseService::class)->create(['request_uuid' => (string) str()->uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 5, 'unit_cost_toman' => 100]]], $this->employee->id);
        app(SaleService::class)->create(['request_uuid' => (string) str()->uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 2, 'purchase_price_toman' => 100, 'unit_price_toman' => 150]]], $this->employee->id);
        $product->update(['last_replacement_cost_toman' => 999]);
        $metrics = app(SimpleReportingService::class)->summarize(now()->startOfDay(), now()->startOfDay()->addDay());
        $this->assertSame(100, $metrics['salesProfit']);
        $this->assertSame(2, (int) $metrics['topSelling']->first()->quantity);
        $this->assertSame(100, (int) $metrics['mostProfitable']->first()->profit);
        $this->actingAs($this->employee)->get('/reports?period=range&from='.today()->toDateString().'&to='.today()->toDateString())->assertOk();
    }

    public function test_login_logout_and_inactive_account_protection_remain(): void
    {
        $this->employee->update(['password' => Hash::make('local-test-password')]);
        $this->post('/login', ['email' => $this->employee->email, 'password' => 'local-test-password'])->assertRedirect('/dashboard');
        $this->post('/logout')->assertRedirect('/login');
        $this->get('/dashboard')->assertRedirect('/login');
        $this->employee->update(['active' => false]);
        $this->post('/login', ['email' => $this->employee->email, 'password' => 'local-test-password'])->assertSessionHasErrors('email');
    }

    public function test_sale_snapshot_is_idempotent_and_a_loss_is_reported(): void
    {
        $product = $this->product();
        app(PurchaseService::class)->create(['request_uuid' => (string) str()->uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 5, 'unit_cost_toman' => 100]]], $this->employee->id);
        $data = ['request_uuid' => (string) str()->uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 2, 'purchase_price_toman' => 100, 'unit_price_toman' => 80]]];
        app(SaleService::class)->create($data, $this->employee->id);
        app(SaleService::class)->create($data, $this->employee->id);
        $this->assertDatabaseCount('sales', 1);
        $this->assertSame(3, (int) $product->fresh()->stock_quantity);
        $this->assertSame(-40, (int) DB::table('sales')->value('estimated_profit_toman'));
        $this->assertSame(-40, app(SimpleReportingService::class)->summarize(now()->startOfDay(), now()->startOfDay()->addDay())['netProfit']);
    }

    public function test_expense_title_is_created_without_account_and_void_excludes_it_from_profit(): void
    {
        $request = ['request_uuid' => (string) str()->uuid(), 'business_at' => now()->format('Y-m-d\TH:i'), 'title' => 'بسته‌بندی', 'amount_toman' => 20];
        $this->actingAs($this->employee)->post('/expenses', $request)->assertRedirect();
        $this->actingAs($this->employee)->post('/expenses', $request)->assertRedirect();
        $this->assertDatabaseCount('expenses', 1);
        $this->assertNull(DB::table('expenses')->value('financial_account_id'));
        $this->assertSame(20, app(SimpleReportingService::class)->summarize(now()->startOfDay(), now()->startOfDay()->addDay())['expenses']);
        $id = DB::table('expenses')->value('id');
        $this->actingAs($this->employee)->post("/expenses/{$id}/void", ['reason' => 'ثبت تکراری'])->assertRedirect();
        $this->assertSame(0, app(SimpleReportingService::class)->summarize(now()->startOfDay(), now()->startOfDay()->addDay())['expenses']);
    }

    public function test_live_lookup_returns_stock_and_hides_internal_bootstrap_identifier(): void
    {
        $product = $this->product('BOOTSTRAP-20260919-1946');
        $product->update(['name' => 'اتو تفال 5718', 'brand' => 'تفال', 'model' => 'FV5718']);
        app(PurchaseService::class)->create(['request_uuid' => (string) str()->uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 3, 'unit_cost_toman' => 100]]], $this->employee->id);

        $response = $this->actingAs($this->employee)->getJson('/products-lookup?q=FV5718&context=sale')->assertOk();
        $response->assertJsonPath('data.0.name', 'اتو تفال 5718')->assertJsonPath('data.0.stock_quantity', 3)->assertJsonPath('data.0.display_sku', null);
        $this->actingAs($this->employee)->get('/inventory')->assertSee('اتو تفال 5718')->assertDontSee('BOOTSTRAP-20260919-1946');
    }

    public function test_primary_flows_are_wizards_without_manual_time_or_prefilled_purchase_price(): void
    {
        $sale = $this->actingAs($this->employee)->get('/sales/new')->assertOk();
        $sale->assertSee('مرحله ۱ از ۴')->assertSee('data-product-search', false)->assertDontSee('datetime-local')->assertDontSee('data-cost=', false);
        $this->actingAs($this->employee)->get('/purchases/new')->assertSee('مرحله ۱ از ۴')->assertDontSee('<select class="input" name="items[0][product_id]"', false)->assertDontSee('datetime-local');
        $this->actingAs($this->employee)->get('/expenses/new')->assertSee('مرحله ۱ از ۳')->assertDontSee('datetime-local');
    }

    public function test_success_receipts_use_persisted_records_and_user_attribution(): void
    {
        $product = $this->product('RECEIPT-1');
        app(PurchaseService::class)->create(['request_uuid' => (string) str()->uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 4, 'unit_cost_toman' => 100]]], $this->employee->id);
        $sale = app(SaleService::class)->create(['request_uuid' => (string) str()->uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 2, 'purchase_price_toman' => 100, 'unit_price_toman' => 150]]], $this->employee->id);

        $this->actingAs($this->employee)->get(route('sales.success', $sale))->assertOk()->assertSee('فروش با موفقیت ثبت شد')->assertSee($this->employee->name)->assertSee('موجودی باقی‌مانده')->assertSee('2');
        $this->actingAs($this->employee)->get('/sales')->assertSee($this->employee->name)->assertSee($product->name);
    }
}
