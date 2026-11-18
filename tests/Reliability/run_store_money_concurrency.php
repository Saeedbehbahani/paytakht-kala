<?php

use App\Models\Product;
use App\Models\User;
use App\Services\PersonalPayableService;
use App\Services\StoreMoneyService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::connection()->getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'paytakht_store_qa') {
    throw new RuntimeException('This runner only accepts the disposable paytakht_store_qa MariaDB database.');
}

$run = strtolower((string) Str::ulid());
$user = User::create(['name' => 'Store QA', 'email' => "store-{$run}@local.test", 'role' => 'ADMIN', 'active' => true, 'password' => 'local-only']);
$supplier = DB::table('suppliers')->insertGetId(['name' => 'Store QA supplier', 'created_at' => now(), 'updated_at' => now()]);
$category = DB::table('expense_categories')->insertGetId(['name' => 'Store QA expense', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
$products = [];
foreach ([1, 2] as $number) {
    $products[] = Product::create(['name' => "Store QA {$number}", 'slug' => "store-qa-{$run}-{$number}", 'sku' => "SQA-{$run}-{$number}", 'sale_price_toman' => 100, 'active' => true, 'status' => 'ACTIVE'])->id;
}
$money = app(StoreMoneyService::class);
$money->initialize(50, "Disposable MariaDB race fixture {$run}", $user->id, (string) Str::uuid());
$directory = sys_get_temp_dir()."/paytakht-store-race-{$run}";
mkdir($directory);
$checks = 0;
$assert = function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$race = static function (array $left, array $right, string $label) use ($directory): array {
    $start = microtime(true) + 0.7;
    $workers = [];
    foreach ([$left, $right] as $index => $task) {
        $input = "{$directory}/{$label}-{$index}.json";
        $output = "{$directory}/{$label}-{$index}.out";
        file_put_contents($input, json_encode($task + ['start_at' => $start], JSON_THROW_ON_ERROR));
        $process = proc_open([PHP_BINARY, __DIR__.'/store_money_worker.php', $input, $output], [0 => ['pipe', 'r'], 1 => ['file', "{$directory}/{$label}-{$index}.stdout", 'a'], 2 => ['file', "{$directory}/{$label}-{$index}.stderr", 'a']], $pipes, dirname(__DIR__, 2));
        fclose($pipes[0]);
        $workers[] = [$process, $output];
    }
    $results = [];
    foreach ($workers as [$process, $output]) {
        proc_close($process);
        $results[] = json_decode(file_get_contents($output), true, flags: JSON_THROW_ON_ERROR);
    }

    return $results;
};
$base = ['supplier_id' => $supplier, 'category_id' => $category, 'user_id' => $user->id, 'amount' => 40];
$topUp = function (int $amount) use ($money, $user, $run): void {
    static $sequence = 0;
    $money->record('IN', $amount, 'QA_TOPUP', $user->id, $run, $sequence++, $user->id);
};
$verify = function () use ($assert, $money): void {
    $balance = $money->balance();
    $ledger = (int) DB::table('store_money_movements')->where('direction', 'IN')->sum('amount_toman')
        - (int) DB::table('store_money_movements')->where('direction', 'OUT')->sum('amount_toman');
    $assert($balance >= 0, 'Store Money became negative.');
    $assert($balance === $ledger, 'Projection does not match ledger.');
};

$pair = $race($base + ['operation' => 'purchase', 'product_id' => $products[0], 'uuid' => (string) Str::uuid()], $base + ['operation' => 'purchase', 'product_id' => $products[1], 'uuid' => (string) Str::uuid()], 'two-purchases');
$assert(count(array_filter($pair, fn ($result) => $result['status'] === 'success')) === 1, 'Two purchases spent the same 50.');
$assert($money->balance() === 10, 'Purchase race balance mismatch.');
$verify();

$topUp(40);
$pair = $race($base + ['operation' => 'expense', 'uuid' => (string) Str::uuid()], $base + ['operation' => 'expense', 'uuid' => (string) Str::uuid()], 'two-expenses');
$assert(count(array_filter($pair, fn ($result) => $result['status'] === 'success')) === 1, 'Two expenses spent the same 50.');
$assert($money->balance() === 10, 'Expense race balance mismatch.');
$verify();

$topUp(40);
$personal = app(PersonalPayableService::class)->create('QA', $user->id, 40, $user->id, $user->id);
$pair = $race($base + ['operation' => 'purchase', 'product_id' => $products[0], 'uuid' => (string) Str::uuid()], $base + ['operation' => 'reimburse', 'personal_payable_id' => $personal, 'uuid' => (string) Str::uuid()], 'purchase-reimburse');
$assert(count(array_filter($pair, fn ($result) => $result['status'] === 'success')) === 1, 'Purchase and reimbursement spent the same 50.');
$assert($money->balance() === 10, 'Mixed race balance mismatch.');
$verify();

$topUp(40);
$uuid = (string) Str::uuid();
$task = $base + ['operation' => 'purchase', 'product_id' => $products[0], 'uuid' => $uuid];
$pair = $race($task, $task, 'duplicate-purchase');
$assert(count(array_filter($pair, fn ($result) => $result['status'] === 'success')) === 2, 'Duplicate UUID did not resolve idempotently: '.json_encode($pair));
$assert($pair[0]['id'] === $pair[1]['id'], 'Duplicate UUID created two purchases.');
$assert($money->balance() === 10, 'Duplicate UUID spent twice.');
$assert(DB::table('store_money_movements')->where('event_type', 'PURCHASE')->where('event_id', $pair[0]['id'])->count() === 1, 'Duplicate economic effect.');
$verify();

$topUp(80);
$pair = $race($base + ['operation' => 'purchase', 'product_ids' => [$products[0], $products[1]], 'uuid' => (string) Str::uuid()], $base + ['operation' => 'purchase', 'product_ids' => [$products[1], $products[0]], 'uuid' => (string) Str::uuid()], 'opposite-item-order');
$assert(count(array_filter($pair, fn ($result) => $result['status'] === 'success')) === 2, 'Opposite item order failed to serialize: '.json_encode($pair));
$assert($money->balance() === 10, 'Opposite item order balance mismatch.');
$verify();

DB::statement('CREATE TABLE qa_lock_rows (id INT PRIMARY KEY) ENGINE=InnoDB');
DB::table('qa_lock_rows')->insert([['id' => 1], ['id' => 2]]);
$pair = $race(
    $base + ['operation' => 'deadlock', 'first_lock' => 1, 'second_lock' => 2, 'sequence' => 0, 'uuid' => (string) Str::uuid()],
    $base + ['operation' => 'deadlock', 'first_lock' => 2, 'second_lock' => 1, 'sequence' => 1, 'uuid' => (string) Str::uuid()],
    'deadlock-retry'
);
$assert(count(array_filter($pair, fn ($result) => $result['status'] === 'success')) === 2, 'Deadlock retry did not complete both workers: '.json_encode($pair));
$assert(max(array_column($pair, 'id')) > 1, 'A real deadlock retry did not occur.');
$assert(DB::table('store_money_movements')->where('event_type', 'QA_DEADLOCK')->count() === 2, 'Deadlock retry duplicated an economic effect.');
$assert($money->balance() === 8, 'Deadlock retry balance mismatch.');
$verify();

echo json_encode(['status' => 'passed', 'races' => 6, 'workers' => 12, 'assertions' => $checks, 'deadlock_attempts' => array_column($pair, 'id'), 'balance' => $money->balance()], JSON_THROW_ON_ERROR).PHP_EOL;
