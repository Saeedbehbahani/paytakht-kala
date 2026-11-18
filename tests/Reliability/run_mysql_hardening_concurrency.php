<?php

declare(strict_types=1);

use App\Models\Cheque;
use App\Models\FinancialAccount;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\OperationsService;
use App\Services\SaleService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
    throw new RuntimeException('MariaDB is required.');
}

$run = strtolower((string) Str::ulid());
$dir = storage_path("framework/testing/hardening-concurrency-{$run}");
mkdir($dir, 0777, true);
$assert = fn (bool $ok, string $message) => $ok ?: throw new RuntimeException($message);
$spawn = static function (array $config, string $name) use ($dir): array {
    $configPath = "{$dir}/{$name}.json";
    $outputPath = "{$dir}/{$name}.out.json";
    file_put_contents($configPath, json_encode($config, JSON_THROW_ON_ERROR));
    $process = proc_open([PHP_BINARY, __DIR__.'/hardening_worker.php', $configPath, $outputPath], [0 => ['pipe', 'r'], 1 => ['file', "{$dir}/{$name}.stdout", 'a'], 2 => ['file', "{$dir}/{$name}.stderr", 'a']], $pipes, dirname(__DIR__, 2));
    fclose($pipes[0]);

    return [$process, $outputPath];
};
$finish = static function (array $worker): array {
    [$process, $path] = $worker;
    proc_close($process);

    return json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
};
$pair = static function (array $a, array $b, string $prefix) use ($spawn, $finish): array {
    $start = microtime(true) + 0.8;
    $workerA = $spawn($a + ['start_at' => $start], "{$prefix}-a");
    $workerB = $spawn($b + ['start_at' => $start], "{$prefix}-b");

    return [$finish($workerA), $finish($workerB)];
};

$user = User::create(['name' => 'Hardening Worker', 'email' => "hardening-{$run}@local.test", 'password' => 'local-only', 'role' => 'ADMIN']);
$category = DB::table('categories')->value('id');
$account = FinancialAccount::create(['name' => "Hardening Cash {$run}", 'type' => 'CASH']);
$toA = FinancialAccount::create(['name' => "Hardening A {$run}", 'type' => 'BANK']);
$toB = FinancialAccount::create(['name' => "Hardening B {$run}", 'type' => 'BANK']);
$ops = app(OperationsService::class);
$ops->financeMutation((string) Str::uuid(), 'OPENING_BALANCE', $account->id, 20_000_000, $user->id, 'setup');

$product = Product::create(['category_id' => $category, 'name' => "Hardening Product {$run}", 'sku' => "HC-{$run}", 'slug' => "hc-{$run}", 'sale_price_toman' => 1_000_000]);
$product->forceFill(['average_cost_toman' => 100_000])->save();
app(InventoryService::class)->adjust((string) Str::uuid(), $product->id, 10, 'setup', $user->id);
$customer = DB::table('customers')->value('id');
$sale = app(SaleService::class)->create(['request_uuid' => (string) Str::uuid(), 'customer_id' => $customer, 'items' => [['product_id' => $product->id, 'quantity' => 1]], 'payments' => [['method' => 'INSTALLMENT', 'amount_toman' => 1_000_000]], 'installment_count' => 1], $user->id);
$installment = DB::table('installments')->where('sale_id', $sale->id)->first();
$installmentResults = $pair(['operation' => 'installment', 'uuid' => (string) Str::uuid(), 'installment_id' => $installment->id, 'amount' => 1_000_000, 'account_id' => $account->id, 'user_id' => $user->id], ['operation' => 'installment', 'uuid' => (string) Str::uuid(), 'installment_id' => $installment->id, 'amount' => 1_000_000, 'account_id' => $account->id, 'user_id' => $user->id], 'installment');
$assert(count(array_filter($installmentResults, fn ($r) => $r['status'] === 'success')) === 1, 'One installment payment must succeed.');
$assert(DB::table('financial_movements')->where('reference_type', 'INSTALLMENT')->where('reference_id', $installment->id)->count() === 1, 'Installment settled more than once.');

$cheque = Cheque::create(['request_uuid' => (string) Str::uuid(), 'direction' => 'INCOMING', 'customer_id' => $customer, 'bank_name' => 'هم‌زمانی', 'cheque_number' => "HC-{$run}", 'amount_toman' => 500_000, 'due_date' => now(), 'status' => 'PENDING', 'created_by_user_id' => $user->id]);
$chequeResults = $pair(['operation' => 'cheque', 'cheque_id' => $cheque->id, 'account_id' => $account->id, 'user_id' => $user->id], ['operation' => 'cheque', 'cheque_id' => $cheque->id, 'account_id' => $account->id, 'user_id' => $user->id], 'cheque');
$assert(count(array_filter($chequeResults, fn ($r) => $r['status'] === 'success')) === 1, 'One cheque clearing must succeed.');
$assert(DB::table('financial_movements')->where('reference_type', 'CHEQUE')->where('reference_id', $cheque->id)->count() === 1, 'Cheque cleared more than once.');

$beforeSource = $account->fresh()->balance_toman;
$transferResults = $pair(['operation' => 'transfer', 'uuid' => (string) Str::uuid(), 'from_id' => $account->id, 'to_id' => $toA->id, 'amount' => 100_000, 'user_id' => $user->id], ['operation' => 'transfer', 'uuid' => (string) Str::uuid(), 'from_id' => $account->id, 'to_id' => $toB->id, 'amount' => 200_000, 'user_id' => $user->id], 'transfer');
$assert(count(array_filter($transferResults, fn ($r) => $r['status'] === 'success')) === 2, 'Both safe transfers must succeed.');
$assert($account->fresh()->balance_toman === $beforeSource - 300_000, 'Shared transfer source balance drifted.');

$startStock = $product->fresh()->stock_quantity;
$saleUuid = (string) Str::uuid();
$adjustSaleResults = $pair(['operation' => 'adjust', 'uuid' => (string) Str::uuid(), 'product_id' => $product->id, 'actual' => $startStock + 2, 'user_id' => $user->id], ['operation' => 'sale', 'payload' => ['request_uuid' => $saleUuid, 'items' => [['product_id' => $product->id, 'quantity' => 1]], 'payments' => [['method' => 'CASH', 'financial_account_id' => $account->id, 'amount_toman' => 1_000_000]]], 'user_id' => $user->id], 'adjust-sale');
$assert(count(array_filter($adjustSaleResults, fn ($r) => $r['status'] === 'success')) === 2, 'Adjustment and sale must both complete safely.');
$ledgerStock = (int) DB::table('stock_movements')->where('product_id', $product->id)->sum('quantity_delta');
$assert($product->fresh()->stock_quantity === $ledgerStock && $ledgerStock >= 0, 'Adjustment/sale stock did not reconcile.');

$closingUuid = (string) Str::uuid();
$dayStart = now()->subDay()->startOfDay();
$dayEnd = $dayStart->copy()->addDay();
$values = FinancialAccount::whereIn('type', ['CASH', 'POS'])->get()->mapWithKeys(function ($candidate) use ($dayStart, $dayEnd) {
    if ($candidate->type === 'CASH') {
        return [$candidate->id => (int) (DB::table('financial_movements')->where('financial_account_id', $candidate->id)->where('created_at', '<', $dayEnd)->latest('id')->value('balance_after_toman') ?? 0)];
    }
    $base = DB::table('financial_movements')->where('financial_account_id', $candidate->id)->where('created_at', '>=', $dayStart)->where('created_at', '<', $dayEnd)->whereIn('type', ['SALE_PAYMENT', 'INSTALLMENT_RECEIPT', 'CHEQUE_RECEIPT', 'REFUND']);

    return [$candidate->id => (int) (clone $base)->where('direction', 'IN')->sum('amount_toman') - (int) (clone $base)->where('direction', 'OUT')->sum('amount_toman')];
})->all();
$closingPayload = ['request_uuid' => $closingUuid, 'business_date' => $dayStart->toDateString(), 'actual_values' => $values, 'operations_confirmed' => true, 'reason' => 'concurrency'];
$closingResults = $pair(['operation' => 'closing', 'payload' => $closingPayload, 'user_id' => $user->id], ['operation' => 'closing', 'payload' => $closingPayload, 'user_id' => $user->id], 'closing');
$assert(count(array_filter($closingResults, fn ($r) => $r['status'] === 'success')) === 2, 'Duplicate closing requests must both resolve idempotently.');
$assert(DB::table('day_closings')->where('request_uuid', $closingUuid)->count() === 1, 'Duplicate closing row was created.');

$competingDay = now()->subDays(2)->startOfDay();
$competingValues = FinancialAccount::whereIn('type', ['CASH', 'POS'])->get()->mapWithKeys(function ($candidate) use ($competingDay) {
    $end = $competingDay->copy()->addDay();
    if ($candidate->type === 'CASH') {
        return [$candidate->id => (int) (DB::table('financial_movements')->where('financial_account_id', $candidate->id)->where('created_at', '<', $end)->latest('id')->value('balance_after_toman') ?? 0)];
    }
    $base = DB::table('financial_movements')->where('financial_account_id', $candidate->id)->where('created_at', '>=', $competingDay)->where('created_at', '<', $end)->whereIn('type', ['SALE_PAYMENT', 'INSTALLMENT_RECEIPT', 'CHEQUE_RECEIPT', 'REFUND']);

    return [$candidate->id => (int) (clone $base)->where('direction', 'IN')->sum('amount_toman') - (int) (clone $base)->where('direction', 'OUT')->sum('amount_toman')];
})->all();
$firstCompetingPayload = ['request_uuid' => (string) Str::uuid(), 'business_date' => $competingDay->toDateString(), 'actual_values' => $competingValues, 'operations_confirmed' => true];
$secondCompetingPayload = array_merge($firstCompetingPayload, ['request_uuid' => (string) Str::uuid()]);
$competingClosingResults = $pair(['operation' => 'closing', 'payload' => $firstCompetingPayload, 'user_id' => $user->id], ['operation' => 'closing', 'payload' => $secondCompetingPayload, 'user_id' => $user->id], 'competing-closing');
$assert(count(array_filter($competingClosingResults, fn ($r) => $r['status'] === 'success')) === 1, 'Only one competing closing request may succeed.');
$assert(DB::table('day_closings')->where('business_date', $competingDay->toDateString())->count() === 1, 'Competing closing requests created more than one row.');

echo json_encode(['database' => DB::selectOne('select version() version')->version, 'installment' => $installmentResults, 'cheque' => $chequeResults, 'transfer' => $transferResults, 'adjustment_sale' => $adjustSaleResults, 'closing' => $closingResults, 'competing_closing' => $competingClosingResults, 'asserted_scenarios' => 6], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
