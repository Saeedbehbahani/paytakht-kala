<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\FinancialAccount;
use App\Models\Product;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DashboardService;
use App\Services\InventoryService;
use App\Services\OperationsService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$assert(in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true), 'MySQL/MariaDB is required.');
$databaseTimezone = DB::selectOne('select @@session.time_zone as timezone')->timezone;
$assert($databaseTimezone === '+03:30', 'MySQL/MariaDB session timezone must be +03:30.');
$assert(DB::table('users')->count() === 0, 'Financial smoke database must start empty.');

DB::table('settings')->insert([
    ['key' => 'large_expense_threshold', 'value' => '10000000', 'created_at' => now(), 'updated_at' => now()],
    ['key' => 'employee_discount_flag_threshold_percent', 'value' => '5', 'created_at' => now(), 'updated_at' => now()],
]);
$admin = User::create(['name' => 'Admin Smoke', 'email' => 'admin-smoke@paytakht.local', 'password' => 'SmokePass!1405', 'role' => 'ADMIN', 'active' => true]);
$employee = User::create(['name' => 'Ali Smoke', 'email' => 'ali-smoke@paytakht.local', 'password' => 'SmokePass!1405', 'role' => 'EMPLOYEE', 'active' => true]);
$assert(Auth::attempt(['email' => $admin->email, 'password' => 'SmokePass!1405']), 'Admin login failed.');
$assert(Auth::id() === $admin->id, 'Authenticated Admin identity mismatch.');
app(AuditService::class)->record('login', 'USER', $admin->id, [], [], 'Smoke Admin login', $admin->id);
Auth::logout();

$category = Category::create(['name' => 'Smoke Appliances', 'active' => true]);
$productA = Product::create(['category_id' => $category->id, 'name' => 'Smoke Product A', 'sku' => 'SMOKE-A', 'slug' => 'smoke-a', 'minimum_stock' => 1]);
$productB = Product::create(['category_id' => $category->id, 'name' => 'Smoke Product B', 'sku' => 'SMOKE-B', 'slug' => 'smoke-b', 'minimum_stock' => 1]);
$supplierId = DB::table('suppliers')->insertGetId(['name' => 'Smoke Supplier', 'created_at' => now(), 'updated_at' => now()]);
$customerId = DB::table('customers')->insertGetId(['name' => 'Smoke Customer', 'phone' => '09120000000', 'created_at' => now(), 'updated_at' => now()]);
$expenseCategoryId = DB::table('expense_categories')->insertGetId(['name' => 'Smoke Expense', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

$cash = FinancialAccount::create(['name' => 'Smoke Cash', 'type' => 'CASH']);
$pos = FinancialAccount::create(['name' => 'Smoke POS', 'type' => 'POS']);
$bank = FinancialAccount::create(['name' => 'Smoke Bank', 'type' => 'BANK']);
DB::table('settings')->insert([
    ['key' => 'employee_cash_account_id', 'value' => (string) $cash->id, 'created_at' => now(), 'updated_at' => now()],
    ['key' => 'employee_pos_account_id', 'value' => (string) $pos->id, 'created_at' => now(), 'updated_at' => now()],
    ['key' => 'employee_card_transfer_account_id', 'value' => (string) $bank->id, 'created_at' => now(), 'updated_at' => now()],
]);
$operations = app(OperationsService::class);
$operations->financeMutation((string) Str::uuid(), 'OPENING_BALANCE', $cash->id, 2_000_000, $admin->id, 'Smoke opening');
$operations->financeMutation((string) Str::uuid(), 'OPENING_BALANCE', $pos->id, 2_000_000, $admin->id, 'Smoke opening');
$operations->financeMutation((string) Str::uuid(), 'OPENING_BALANCE', $bank->id, 2_000_000, $admin->id, 'Smoke opening');

$openingId = DB::table('opening_inventory_sessions')->insertGetId(['status' => 'DRAFT', 'created_by_user_id' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);
DB::table('opening_inventory_items')->insert([
    ['opening_inventory_session_id' => $openingId, 'product_id' => $productA->id, 'quantity' => 10, 'unit_cost_toman' => 1_000, 'sale_price_toman' => 1_600, 'created_at' => now(), 'updated_at' => now()],
    ['opening_inventory_session_id' => $openingId, 'product_id' => $productB->id, 'quantity' => 5, 'unit_cost_toman' => 2_000, 'sale_price_toman' => 3_000, 'created_at' => now(), 'updated_at' => now()],
]);
$operations->finalizeOpening($openingId, $admin->id);
$assert(DB::table('opening_inventory_sessions')->where('id', $openingId)->value('status') === 'FINALIZED', 'Opening inventory did not finalize.');

$purchase = app(PurchaseService::class)->create([
    'request_uuid' => (string) Str::uuid(),
    'supplier_id' => $supplierId,
    'items' => [
        ['product_id' => $productA->id, 'quantity' => 2, 'unit_cost_toman' => 1_400],
        ['product_id' => $productB->id, 'quantity' => 1, 'unit_cost_toman' => 2_400],
    ],
    'payments' => [['method' => 'BANK', 'financial_account_id' => $bank->id, 'amount_toman' => 3_000]],
], $employee->id);
$assert($productA->fresh()->average_cost_toman === 1_067, 'Product A weighted average cost mismatch.');
$assert($productB->fresh()->average_cost_toman === 2_067, 'Product B weighted average cost mismatch.');
$assert((int) DB::table('payables')->where('purchase_id', $purchase->id)->value('remaining_amount_toman') === 2_200, 'Supplier payable mismatch.');

$sale = app(SaleService::class)->create([
    'request_uuid' => (string) Str::uuid(),
    'items' => [
        ['product_id' => $productA->id, 'quantity' => 2],
        ['product_id' => $productB->id, 'quantity' => 1],
    ],
    'payments' => [
        ['method' => 'POS', 'financial_account_id' => $pos->id, 'amount_toman' => 2_200],
        ['method' => 'INSTALLMENT', 'amount_toman' => 4_000],
    ],
    'customer_id' => $customerId,
    'installment_count' => 2,
], $employee->id);
$assert($sale->total_toman === 6_200, 'Sale total mismatch.');
$assert($sale->estimated_profit_toman === 1_999, 'Initial estimated profit mismatch.');
$assert($productA->fresh()->stock_quantity === 10 && $productB->fresh()->stock_quantity === 5, 'Post-sale inventory mismatch.');
$assert((int) DB::table('receivables')->where('sale_id', $sale->id)->value('remaining_amount_toman') === 4_000, 'Initial receivable mismatch.');

$installmentId = DB::table('installments')->where('sale_id', $sale->id)->orderBy('sequence')->value('id');
$operations->payInstallment((string) Str::uuid(), $installmentId, 1_000, $cash->id, $admin->id);
$assert((int) DB::table('receivables')->where('sale_id', $sale->id)->value('remaining_amount_toman') === 3_000, 'Partial installment receivable mismatch.');

$cheque = $operations->createCheque([
    'request_uuid' => (string) Str::uuid(),
    'direction' => 'INCOMING',
    'customer_id' => $customerId,
    'bank_name' => 'Smoke Bank Cheque',
    'cheque_number' => 'SMOKE-CH-1',
    'amount_toman' => 500,
    'due_date' => now()->addDays(3)->toDateString(),
    'note' => 'Standalone incoming cheque smoke',
], $employee->id);
$cashBeforeCheque = $cash->fresh()->balance_toman;
$assert($cheque->status === 'PENDING' && $cash->fresh()->balance_toman === $cashBeforeCheque, 'Pending cheque affected cash.');
$operations->changeChequeStatus($cheque->id, 'CLEARED', $admin->id, $cash->id);
$assert($cash->fresh()->balance_toman === $cashBeforeCheque + 500, 'Cleared cheque did not affect cash exactly once.');

$expense = $operations->expense([
    'request_uuid' => (string) Str::uuid(),
    'expense_category_id' => $expenseCategoryId,
    'financial_account_id' => $cash->id,
    'amount_toman' => 300,
    'note' => 'Smoke expense',
], $employee->id);
$assert($expense->fresh()->status === 'COMPLETED', 'Expense failed.');

$saleItemA = DB::table('sale_items')->where('sale_id', $sale->id)->where('product_id', $productA->id)->first();
$operations->saleReturn([
    'request_uuid' => (string) Str::uuid(),
    'sale_id' => $sale->id,
    'reason' => 'Smoke partial return',
    'items' => [['sale_item_id' => $saleItemA->id, 'quantity' => 1]],
], $employee->id);
$assert((int) DB::table('receivables')->where('sale_id', $sale->id)->value('remaining_amount_toman') === 1_400, 'Return did not reduce receivable correctly.');
$assert($sale->fresh()->estimated_profit_toman === 1_466, 'Return did not reduce profit correctly.');

app(InventoryService::class)->adjust((string) Str::uuid(), $productB->id, $productB->fresh()->stock_quantity - 1, 'Smoke physical adjustment', $employee->id);
$countId = DB::table('stock_count_sessions')->insertGetId(['status' => 'DRAFT', 'created_by_user_id' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);
$countedA = $productA->fresh()->stock_quantity - 1;
DB::table('stock_count_items')->insert(['stock_count_session_id' => $countId, 'product_id' => $productA->id, 'system_quantity' => $productA->fresh()->stock_quantity, 'counted_quantity' => $countedA, 'difference' => -1, 'reason' => 'Smoke stock count', 'created_at' => now(), 'updated_at' => now()]);
$operations->finalizeStockCount($countId, $admin->id);
$assert($productA->fresh()->stock_quantity === $countedA, 'Stock count finalization mismatch.');

$voidSale = app(SaleService::class)->create([
    'request_uuid' => (string) Str::uuid(),
    'items' => [['product_id' => $productB->id, 'quantity' => 1]],
    'payments' => [['method' => 'POS', 'financial_account_id' => $pos->id, 'amount_toman' => $productB->fresh()->sale_price_toman]],
], $employee->id);
$stockBeforeVoid = $productB->fresh()->stock_quantity + 1;
$posBeforeVoid = $pos->fresh()->balance_toman - $voidSale->total_toman;
$operations->voidSale($voidSale->id, 'Smoke eligible void', $admin->id);
$assert($productB->fresh()->stock_quantity === $stockBeforeVoid, 'Void did not restore stock.');
$assert($pos->fresh()->balance_toman === $posBeforeVoid, 'Void did not restore account balance.');

$dayStart = now()->startOfDay();
$dayEnd = $dayStart->copy()->addDay();
$actual = FinancialAccount::query()->whereIn('type', ['CASH', 'POS'])->get()->mapWithKeys(function ($account) use ($dayStart, $dayEnd) {
    if ($account->type === 'CASH') {
        return [$account->id => (int) (DB::table('financial_movements')->where('financial_account_id', $account->id)->where('created_at', '<', $dayEnd)->latest('id')->value('balance_after_toman') ?? 0)];
    }
    $base = DB::table('financial_movements')->where('financial_account_id', $account->id)->where('created_at', '>=', $dayStart)->where('created_at', '<', $dayEnd)->whereIn('type', ['SALE_PAYMENT', 'INSTALLMENT_RECEIPT', 'CHEQUE_RECEIPT', 'REFUND']);

    return [$account->id => (int) (clone $base)->where('direction', 'IN')->sum('amount_toman') - (int) (clone $base)->where('direction', 'OUT')->sum('amount_toman')];
})->all();
$closingId = $operations->closeDay([
    'request_uuid' => (string) Str::uuid(),
    'business_date' => $dayStart->toDateString(),
    'actual_values' => $actual,
    'operations_confirmed' => true,
    'reason' => null,
], $employee->id);
$differences = json_decode(DB::table('day_closings')->where('id', $closingId)->value('differences'), true, flags: JSON_THROW_ON_ERROR);
$assert(collect($differences)->every(fn ($difference) => $difference === 0), 'Day closing did not reconcile to zero.');

foreach (Product::query()->get() as $product) {
    $ledgerStock = (int) DB::table('stock_movements')->where('product_id', $product->id)->sum('quantity_delta');
    $assert($product->stock_quantity === $ledgerStock, "Stock projection mismatch for product {$product->id}.");
}
foreach (FinancialAccount::query()->get() as $account) {
    $ledgerBalance = (int) DB::table('financial_movements')->where('financial_account_id', $account->id)
        ->selectRaw("COALESCE(SUM(CASE WHEN direction='IN' THEN amount_toman ELSE -amount_toman END),0) balance")
        ->value('balance');
    $lastBalance = (int) (DB::table('financial_movements')->where('financial_account_id', $account->id)->latest('id')->value('balance_after_toman') ?? 0);
    $assert($account->balance_toman === $ledgerBalance, "Financial projection mismatch for account {$account->id}.");
    $assert($account->balance_toman === $lastBalance, "Last movement balance mismatch for account {$account->id}.");
}

$installmentOutstanding = (int) DB::table('installments')->where('sale_id', $sale->id)->selectRaw('COALESCE(SUM(amount_toman-paid_amount_toman),0) outstanding')->value('outstanding');
$receivable = (int) DB::table('receivables')->where('sale_id', $sale->id)->value('remaining_amount_toman');
$assert($receivable === $installmentOutstanding, 'Receivable does not reconcile with installments.');
$assert((int) DB::table('payables')->where('purchase_id', $purchase->id)->value('remaining_amount_toman') === 2_200, 'Payable changed unexpectedly.');

$dashboard = app(DashboardService::class)->admin();
$assert($dashboard['salesMonth'] === 4_600, 'Net sales mismatch.');
$assert($dashboard['profit'] === 1_466, 'Estimated gross profit mismatch.');
$assert($dashboard['expenses'] === 300, 'Operating expense mismatch.');
$assert($dashboard['managerialNet'] === 99, 'Estimated managerial net profit mismatch.');
$assert($dashboard['missingCost'] === 0, 'Unexpected missing-cost warning.');

$requiredAuditActions = ['login', 'opening_balance', 'opening_inventory_finalize', 'purchase', 'sale', 'installment_payment', 'cheque_create', 'cheque_status', 'expense', 'sale_return', 'stock_adjustment', 'stock_count_finalize', 'void', 'day_closing'];
$presentActions = DB::table('audit_logs')->pluck('action')->unique();
foreach ($requiredAuditActions as $action) {
    $assert($presentActions->contains($action), "Missing Audit action: {$action}.");
}

$summary = [
    'database' => DB::selectOne('select version() as version')->version,
    'database_session_timezone' => $databaseTimezone,
    'laravel_timezone' => config('app.timezone'),
    'admin_login' => 'PASS',
    'opening_inventory' => 'FINALIZED',
    'weighted_average' => ['product_a' => $productA->fresh()->average_cost_toman, 'product_b' => $productB->fresh()->average_cost_toman],
    'stock' => Product::query()->pluck('stock_quantity', 'sku')->all(),
    'account_balances' => FinancialAccount::query()->pluck('balance_toman', 'name')->all(),
    'receivable' => $receivable,
    'installment_outstanding' => $installmentOutstanding,
    'payable' => (int) DB::table('payables')->where('purchase_id', $purchase->id)->value('remaining_amount_toman'),
    'net_sales' => $dashboard['salesMonth'],
    'estimated_gross_profit' => $dashboard['profit'],
    'operating_expenses' => $dashboard['expenses'],
    'stock_discrepancy_loss' => $dashboard['stockDiscrepancyLoss'],
    'estimated_managerial_net' => $dashboard['managerialNet'],
    'day_closing_differences' => $differences,
    'audit_entries' => DB::table('audit_logs')->count(),
    'status' => 'PASS',
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
