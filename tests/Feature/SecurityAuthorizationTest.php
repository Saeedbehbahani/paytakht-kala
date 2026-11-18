<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_operational_routes(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/sales')->assertRedirect('/login');
        $this->post('/sales', [])->assertRedirect('/login');
    }

    public function test_employee_is_denied_admin_inventory_financial_and_purchase_operations(): void
    {
        $employee = User::factory()->create(['role' => 'EMPLOYEE', 'active' => true]);

        foreach (['/reports', '/purchases', '/purchases/new', '/inventory', '/expenses/new', '/admin/settings', '/admin/employees', '/admin/audit', '/parties', '/uploads'] as $uri) {
            $this->actingAs($employee)->get($uri)->assertForbidden();
        }

        $this->actingAs($employee)->post('/inventory/adjust', [])->assertForbidden();
        $this->actingAs($employee)->post('/expenses', [])->assertForbidden();
        $this->actingAs($employee)->post('/expenses/1/void', [])->assertForbidden();
        $this->actingAs($employee)->post('/purchases', [])->assertForbidden();
        $this->actingAs($employee)->post('/products', [])->assertForbidden();
    }

    public function test_employee_sales_index_is_scoped_and_cross_user_success_is_forbidden(): void
    {
        $owner = User::factory()->create(['role' => 'EMPLOYEE', 'active' => true]);
        $other = User::factory()->create(['role' => 'EMPLOYEE', 'active' => true]);
        $ownedSale = $this->saleFor($owner, 'OWN-1001');
        $otherSale = $this->saleFor($other, 'OTHER-2002');

        $this->actingAs($owner)
            ->get('/sales')
            ->assertOk()
            ->assertSee('OWN-1001')
            ->assertDontSee('OTHER-2002');

        $this->actingAs($owner)->get("/sales/{$ownedSale->id}/success")->assertOk();
        $this->actingAs($owner)->get("/sales/{$otherSale->id}/success")->assertForbidden();
    }

    public function test_admin_can_view_any_sale_but_employee_cannot_view_product_cost_history(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN', 'active' => true]);
        $employee = User::factory()->create(['role' => 'EMPLOYEE', 'active' => true]);
        $sale = $this->saleFor($employee, 'ADMIN-3003');
        $category = Category::create(['name' => 'Security test', 'active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Protected product',
            'sku' => 'SEC-001',
            'slug' => 'protected-product',
            'sale_price_toman' => 1000,
            'active' => true,
            'status' => 'ACTIVE',
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->get("/sales/{$sale->id}/success")->assertOk();
        $this->actingAs($employee)->get("/products/{$product->id}")->assertForbidden();
    }

    public function test_invalid_sale_input_does_not_create_a_financial_record(): void
    {
        $employee = User::factory()->create(['role' => 'EMPLOYEE', 'active' => true]);

        $this->actingAs($employee)
            ->from('/sales/new')
            ->post('/sales', ['request_uuid' => 'not-a-uuid', 'items' => []])
            ->assertRedirect('/sales/new')
            ->assertSessionHasErrors(['request_uuid', 'items']);

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_web_mutations_reject_requests_without_a_csrf_token(): void
    {
        $employee = User::factory()->create(['role' => 'EMPLOYEE', 'active' => true]);

        $this->withMiddleware(ValidateCsrfToken::class)
            ->actingAs($employee)
            ->post('/sales', [
                'request_uuid' => (string) Str::uuid(),
                'items' => [['product_id' => 1, 'quantity' => 1, 'purchase_price_toman' => 1, 'unit_price_toman' => 1]],
            ])
            ->assertStatus(419);
    }

    private function saleFor(User $user, string $invoice): Sale
    {
        return Sale::create([
            'request_uuid' => (string) Str::uuid(),
            'invoice_number' => $invoice,
            'seller_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'subtotal_toman' => 1000,
            'total_toman' => 1000,
            'paid_amount_toman' => 1000,
            'receivable_amount_toman' => 0,
            'estimated_profit_toman' => 100,
            'status' => 'COMPLETED',
        ]);
    }
}
