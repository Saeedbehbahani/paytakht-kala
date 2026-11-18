<?php

declare(strict_types=1);

use App\Models\FinancialAccount;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\OperationsService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
    fwrite(STDERR, "This reliability test requires MySQL/MariaDB.\n");
    exit(2);
}

$runId = strtolower((string) Str::ulid());
$workDir = storage_path('framework/testing/mysql-concurrency-'.$runId);
if (! is_dir($workDir) && ! mkdir($workDir, 0777, true) && ! is_dir($workDir)) {
    throw new RuntimeException('Could not create reliability work directory.');
}

$categoryId = DB::table('categories')->value('id');
$user = User::create([
    'name' => 'Concurrency Worker',
    'email' => "concurrency-{$runId}@paytakht.local",
    'password' => 'local-test-only',
    'role' => 'EMPLOYEE',
    'active' => true,
]);
$account = FinancialAccount::create(['name' => "Concurrency POS {$runId}", 'type' => 'POS']);
app(OperationsService::class)->financeMutation((string) Str::uuid(), 'OPENING_BALANCE', $account->id, 10_000_000, $user->id, 'MySQL reliability setup');

$spawn = static function (array $config, string $name) use ($workDir): array {
    $configPath = $workDir."/{$name}.config.json";
    $outputPath = $workDir."/{$name}.result.json";
    $stdoutPath = $workDir."/{$name}.stdout.log";
    $stderrPath = $workDir."/{$name}.stderr.log";
    file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    $process = proc_open(
        [PHP_BINARY, __DIR__.'/concurrent_sale_worker.php', $configPath, $outputPath],
        [0 => ['pipe', 'r'], 1 => ['file', $stdoutPath, 'a'], 2 => ['file', $stderrPath, 'a']],
        $pipes,
        dirname(__DIR__, 2),
    );
    if (! is_resource($process)) {
        throw new RuntimeException("Could not start {$name}.");
    }
    fclose($pipes[0]);

    return [$process, $outputPath, $stderrPath];
};

$finish = static function (array $worker): array {
    [$process, $outputPath, $stderrPath] = $worker;
    $exitCode = proc_close($process);
    if (! is_file($outputPath)) {
        throw new RuntimeException('Worker produced no result. '.$exitCode.' '.@file_get_contents($stderrPath));
    }

    return json_decode(file_get_contents($outputPath), true, flags: JSON_THROW_ON_ERROR);
};

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

// Real last-stock contention: worker A holds the Product row lock before worker B starts.
$lastStockProduct = Product::create([
    'category_id' => $categoryId,
    'name' => 'MySQL Last Stock '.$runId,
    'sku' => 'MYSQL-LAST-'.$runId,
    'slug' => 'mysql-last-'.$runId,
    'sale_price_toman' => 1_500_000,
]);
$lastStockProduct->forceFill(['average_cost_toman' => 1_000_000])->save();
app(InventoryService::class)->adjust((string) Str::uuid(), $lastStockProduct->id, 1, 'MySQL last-stock setup', $user->id);
$assert($lastStockProduct->fresh()->unknown_cost_quantity === 1, 'Last-stock setup must contain one unknown-cost unit.');
$lockSignal = $workDir.'/last-stock.locked';
$lastUuids = [(string) Str::uuid(), (string) Str::uuid()];
$base = [
    'product_id' => $lastStockProduct->id,
    'account_id' => $account->id,
    'user_id' => $user->id,
    'sale_price_toman' => $lastStockProduct->sale_price_toman,
];
$workerA = $spawn($base + [
    'request_uuid' => $lastUuids[0],
    'prelock_product' => true,
    'hold_lock_ms' => 1400,
    'lock_signal_path' => $lockSignal,
], 'last-stock-a');
$deadline = microtime(true) + 10;
while (! is_file($lockSignal) && microtime(true) < $deadline) {
    usleep(10_000);
}
$assert(is_file($lockSignal), 'Worker A did not acquire the Product row lock.');
$workerB = $spawn($base + ['request_uuid' => $lastUuids[1]], 'last-stock-b');
$lastResults = [$finish($workerA), $finish($workerB)];
$lastSaleIds = DB::table('sales')->whereIn('request_uuid', $lastUuids)->pluck('id');
$lastSuccesses = array_values(array_filter($lastResults, fn (array $r) => $r['status'] === 'success'));
$lastFailures = array_values(array_filter($lastResults, fn (array $r) => $r['status'] === 'validation'));
$assert(count($lastSuccesses) === 1, 'Exactly one last-stock worker must succeed.');
$assert(count($lastFailures) === 1, 'Exactly one last-stock worker must fail with validation.');
$assert($lastStockProduct->fresh()->stock_quantity === 0, 'Final last-stock quantity must be zero.');
$assert($lastStockProduct->fresh()->unknown_cost_quantity === 0, 'Final last-stock unknown quantity must be zero.');
$assert($lastSaleIds->count() === 1, 'Only one last-stock Sale may exist.');
$assert(DB::table('sale_items')->whereIn('sale_id', $lastSaleIds)->count() === 1, 'Only one last-stock SaleItem may exist.');
$lastSaleItem = DB::table('sale_items')->whereIn('sale_id', $lastSaleIds)->first();
$assert((int) $lastSaleItem->unknown_cost_quantity_snapshot === 1, 'Committed last-stock Sale must snapshot one unknown-cost unit.');
$assert($lastSaleItem->cost_snapshot_toman === null, 'Committed unknown-cost Sale must keep historical cost unknown.');
$assert(DB::table('stock_movements')->where('product_id', $lastStockProduct->id)->where('movement_type', 'SALE')->count() === 1, 'Only one SALE stock movement may exist.');
$assert(DB::table('financial_movements')->whereIn('reference_id', $lastSaleIds)->where('reference_type', 'SALE')->where('type', 'SALE_PAYMENT')->count() === 1, 'Only one SALE_PAYMENT may exist.');
$assert(! DB::table('products')->whereColumn('unknown_cost_quantity', '>', 'stock_quantity')->exists(), 'Unknown quantity may never exceed physical stock.');

// Concurrent idempotency: same UUID and payload enter from two independent processes.
$idempotentProduct = Product::create([
    'category_id' => $categoryId,
    'name' => 'MySQL Idempotency '.$runId,
    'sku' => 'MYSQL-IDEM-'.$runId,
    'slug' => 'mysql-idem-'.$runId,
    'sale_price_toman' => 1_200_000,
]);
$idempotentProduct->forceFill(['average_cost_toman' => 800_000])->save();
app(InventoryService::class)->adjust((string) Str::uuid(), $idempotentProduct->id, 2, 'MySQL idempotency setup', $user->id);
$sameUuid = (string) Str::uuid();
$startAt = microtime(true) + 1.0;
$idemConfig = [
    'product_id' => $idempotentProduct->id,
    'account_id' => $account->id,
    'user_id' => $user->id,
    'sale_price_toman' => $idempotentProduct->sale_price_toman,
    'request_uuid' => $sameUuid,
    'wait_until' => $startAt,
];
$idemA = $spawn($idemConfig, 'idempotency-a');
$idemB = $spawn($idemConfig, 'idempotency-b');
$idemResults = [$finish($idemA), $finish($idemB)];
$idemSaleIds = DB::table('sales')->where('request_uuid', $sameUuid)->pluck('id');
$assert(count(array_filter($idemResults, fn (array $r) => $r['status'] === 'success')) === 2, 'Both idempotent requests must return successfully.');
$assert(count(array_unique(array_column($idemResults, 'sale_id'))) === 1, 'Both idempotent requests must return the same Sale.');
$assert($idemSaleIds->count() === 1, 'Exactly one idempotent Sale may exist.');
$assert($idempotentProduct->fresh()->stock_quantity === 1, 'Idempotent Sale must decrement stock once.');
$assert(DB::table('sale_items')->whereIn('sale_id', $idemSaleIds)->count() === 1, 'Idempotent Sale must create one SaleItem.');
$assert(DB::table('stock_movements')->where('product_id', $idempotentProduct->id)->where('movement_type', 'SALE')->count() === 1, 'Idempotent Sale must create one stock effect.');
$assert(DB::table('financial_movements')->whereIn('reference_id', $idemSaleIds)->where('reference_type', 'SALE')->where('type', 'SALE_PAYMENT')->count() === 1, 'Idempotent Sale must create one financial effect.');

$databaseVersion = DB::selectOne('select version() as version')->version;
$isolationVariable = str_contains(strtolower($databaseVersion), 'mariadb') ? 'tx_isolation' : 'transaction_isolation';
$summary = [
    'database' => $databaseVersion,
    'isolation' => DB::selectOne("select @@{$isolationVariable} as level")->level,
    'last_stock' => [
        'results' => $lastResults,
        'final_stock' => $lastStockProduct->fresh()->stock_quantity,
        'final_unknown_stock' => $lastStockProduct->fresh()->unknown_cost_quantity,
        'unknown_snapshot' => (int) $lastSaleItem->unknown_cost_quantity_snapshot,
        'sales' => $lastSaleIds->count(),
        'sale_stock_movements' => DB::table('stock_movements')->where('product_id', $lastStockProduct->id)->where('movement_type', 'SALE')->count(),
        'sale_financial_movements' => DB::table('financial_movements')->whereIn('reference_id', $lastSaleIds)->where('reference_type', 'SALE')->where('type', 'SALE_PAYMENT')->count(),
    ],
    'idempotency' => [
        'results' => $idemResults,
        'sale_id' => $idemSaleIds->first(),
        'final_stock' => $idempotentProduct->fresh()->stock_quantity,
        'sales' => $idemSaleIds->count(),
        'sale_stock_movements' => DB::table('stock_movements')->where('product_id', $idempotentProduct->id)->where('movement_type', 'SALE')->count(),
        'sale_financial_movements' => DB::table('financial_movements')->whereIn('reference_id', $idemSaleIds)->where('reference_type', 'SALE')->where('type', 'SALE_PAYMENT')->count(),
    ],
    'work_dir' => $workDir,
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
