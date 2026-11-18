<?php

declare(strict_types=1);

use App\Models\Product;
use App\Services\SaleService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$config = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$outputPath = $argv[2];
$startedAt = microtime(true);

try {
    if (! empty($config['wait_until'])) {
        while (microtime(true) < (float) $config['wait_until']) {
            usleep(1_000);
        }
    }

    if (! empty($config['prelock_product'])) {
        DB::beginTransaction();
        Product::query()->whereKey($config['product_id'])->lockForUpdate()->firstOrFail();
        file_put_contents($config['lock_signal_path'], 'locked');
        usleep(((int) $config['hold_lock_ms']) * 1_000);
    }

    $sale = app(SaleService::class)->create([
        'request_uuid' => $config['request_uuid'],
        'items' => [['product_id' => $config['product_id'], 'quantity' => 1]],
        'payments' => [[
            'method' => 'POS',
            'financial_account_id' => $config['account_id'],
            'amount_toman' => $config['sale_price_toman'],
        ]],
    ], $config['user_id']);

    if (DB::transactionLevel() > 0) {
        DB::commit();
    }

    $result = ['status' => 'success', 'sale_id' => $sale->id];
} catch (ValidationException $exception) {
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    $result = ['status' => 'validation', 'errors' => $exception->errors()];
} catch (Throwable $exception) {
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    $result = [
        'status' => 'error',
        'class' => $exception::class,
        'message' => $exception->getMessage(),
        'code' => $exception->getCode(),
    ];
}

$result['started_at'] = $startedAt;
$result['finished_at'] = microtime(true);
$result['duration_ms'] = (int) round(($result['finished_at'] - $startedAt) * 1000);
file_put_contents($outputPath, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
