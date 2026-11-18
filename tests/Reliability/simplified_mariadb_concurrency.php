<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use App\Services\SimpleReportingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)
    || DB::connection()->getDatabaseName() !== 'paytakht_simplification_qa') {
    throw new RuntimeException('This suite runs only on disposable MariaDB database paytakht_simplification_qa.');
}

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$run = (string) Str::ulid();
$users = [];
foreach ([1, 2] as $number) {
    $users[] = User::create([
        'name' => "QA {$number}", 'email' => "qa-{$run}-{$number}@paytakht.test",
        'role' => 'EMPLOYEE', 'active' => true, 'password' => (string) Str::uuid(),
    ]);
}
$product = static function (string $suffix) use ($run, $users): Product {
    return Product::create([
        'name' => "QA {$suffix}", 'sku' => "QA-{$run}-{$suffix}",
        'slug' => strtolower("qa-{$run}-{$suffix}"), 'status' => 'ACTIVE',
        'active' => true, 'created_by_user_id' => $users[0]->id,
    ]);
};
$parallel = static function (array $jobs): array {
    $startAt = microtime(true) + 0.7;
    $processes = [];
    foreach ($jobs as [$operation, $userId, $productId, $uuid]) {
        $process = proc_open(
            [PHP_BINARY, __DIR__.'/simplified_mariadb_worker.php', $operation, (string) $userId, (string) $productId, $uuid, (string) $startAt],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 2),
        );
        if (! is_resource($process)) {
            throw new RuntimeException('Could not start MariaDB worker.');
        }
        fclose($pipes[0]);
        $processes[] = [$process, $pipes];
    }
    $results = [];
    foreach ($processes as [$process, $pipes]) {
        $out = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        if ($exit !== 0) {
            throw new RuntimeException("Worker failed: {$error}");
        }
        $results[] = json_decode($out, true, flags: JSON_THROW_ON_ERROR);
    }

    return $results;
};

$last = $product('LAST');
app(PurchaseService::class)->create(['request_uuid' => (string) Str::uuid(), 'items' => [['product_id' => $last->id, 'quantity' => 1, 'unit_cost_toman' => 100]]], $users[0]->id);
$lastUuids = [(string) Str::uuid(), (string) Str::uuid()];
$results = $parallel([
    ['sale', $users[0]->id, $last->id, $lastUuids[0]],
    ['sale', $users[1]->id, $last->id, $lastUuids[1]],
]);
$assert(count(array_filter($results, fn ($r) => $r['status'] === 'success')) === 1, 'Exactly one last-stock sale must succeed.');
$assert(count(array_filter($results, fn ($r) => $r['status'] === 'validation')) === 1, 'Exactly one last-stock sale must be rejected.');
$assert((int) $last->fresh()->stock_quantity === 0, 'Last-stock balance must be zero.');
$assert(DB::table('sales')->whereIn('request_uuid', $lastUuids)->count() === 1, 'Last-stock race inserted duplicate sales.');
$assert(DB::table('stock_movements')->where('product_id', $last->id)->where('movement_type', 'SALE')->count() === 1, 'Last-stock race inserted duplicate movements.');

$idem = $product('IDEM');
app(PurchaseService::class)->create(['request_uuid' => (string) Str::uuid(), 'items' => [['product_id' => $idem->id, 'quantity' => 3, 'unit_cost_toman' => 100]]], $users[0]->id);
$sameUuid = (string) Str::uuid();
$results = $parallel([
    ['sale', $users[0]->id, $idem->id, $sameUuid],
    ['sale', $users[0]->id, $idem->id, $sameUuid],
]);
$assert(count(array_filter($results, fn ($r) => $r['status'] === 'success')) === 2, 'Both duplicate requests should return success.');
$assert(count(array_unique(array_column($results, 'id'))) === 1, 'Duplicate requests returned different sales.');
$assert((int) $idem->fresh()->stock_quantity === 2, 'Duplicate requests decremented stock twice.');
$assert(DB::table('sales')->where('request_uuid', $sameUuid)->count() === 1, 'Duplicate requests inserted more than one sale.');

$inbound = $product('INBOUND');
$results = $parallel([
    ['stock-in-100', $users[0]->id, $inbound->id, (string) Str::uuid()],
    ['stock-in-200', $users[1]->id, $inbound->id, (string) Str::uuid()],
]);
$assert(count(array_filter($results, fn ($r) => $r['status'] === 'success')) === 2, 'Concurrent Stock In must both succeed without finance.');
$assert((int) $inbound->fresh()->stock_quantity === 10, 'Concurrent Stock In lost stock.');
$assert((int) $inbound->fresh()->average_cost_toman === 150, 'Concurrent Stock In corrupted average cost.');
$assert(DB::table('stock_movements')->where('product_id', $inbound->id)->where('movement_type', 'PURCHASE')->count() === 2, 'Concurrent Stock In lost a ledger movement.');
$assert(DB::table('payment_allocations')->count() === 0, 'Simple operations created finance allocations.');

$correctionUuid = (string) Str::uuid();
app(InventoryService::class)->adjust($correctionUuid, $inbound->id, 7, 'MariaDB correction integrity', $users[0]->id);
$assert((int) $inbound->fresh()->stock_quantity === 7, 'Stock correction projection mismatch.');
$assert(DB::table('stock_movements')->where('request_uuid', $correctionUuid)->where('quantity_delta', -3)->count() === 1, 'Stock correction ledger mismatch.');
$assert(DB::table('audit_logs')->where('action', 'stock_adjustment')->where('entity_id', $inbound->id)->count() === 1, 'Stock correction audit trail mismatch.');

$loss = $product('LOSS');
app(PurchaseService::class)->create(['request_uuid' => (string) Str::uuid(), 'items' => [['product_id' => $loss->id, 'quantity' => 1, 'unit_cost_toman' => 100]]], $users[0]->id);
app(SaleService::class)->create(['request_uuid' => (string) Str::uuid(), 'items' => [['product_id' => $loss->id, 'quantity' => 1, 'purchase_price_toman' => 100, 'unit_price_toman' => 80]]], $users[0]->id);
$metrics = app(SimpleReportingService::class)->summarize(now()->startOfDay(), now()->startOfDay()->addDay());
$assert($metrics['salesProfit'] === 80, 'MariaDB signed profit aggregate mismatch.');
foreach (Product::query()->get() as $checkedProduct) {
    $ledger = (int) DB::table('stock_movements')->where('product_id', $checkedProduct->id)->sum('quantity_delta');
    $assert($ledger === (int) $checkedProduct->stock_quantity, "Ledger/projection mismatch for product {$checkedProduct->id}.");
}
$assert(Artisan::call('paytakht:check-integrity') === 0, 'CheckIntegrity failed after concurrent operations.');

echo "MARIADB CONCURRENCY PASS: last-stock, duplicate sale, simultaneous Stock In, stock correction, profit, ledger/projection, CheckIntegrity\n";
