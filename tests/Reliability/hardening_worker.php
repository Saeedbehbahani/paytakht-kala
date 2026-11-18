<?php

declare(strict_types=1);

use App\Services\InventoryService;
use App\Services\MoneyService;
use App\Services\OperationsService;
use App\Services\SaleService;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$config = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$output = $argv[2];
while (microtime(true) < $config['start_at']) {
    usleep(1_000);
}

try {
    $ops = app(OperationsService::class);
    $result = match ($config['operation']) {
        'installment' => $ops->payInstallment($config['uuid'], $config['installment_id'], $config['amount'], $config['account_id'], $config['user_id']),
        'cheque' => $ops->changeChequeStatus($config['cheque_id'], 'CLEARED', $config['user_id'], $config['account_id']),
        'transfer' => app(MoneyService::class)->transfer($config['uuid'], $config['from_id'], $config['to_id'], $config['amount'], $config['user_id']),
        'adjust' => app(InventoryService::class)->adjust($config['uuid'], $config['product_id'], $config['actual'], 'آزمون هم‌زمانی', $config['user_id']),
        'sale' => app(SaleService::class)->create($config['payload'], $config['user_id'])->id,
        'closing' => $ops->closeDay($config['payload'], $config['user_id']),
    };
    file_put_contents($output, json_encode(['status' => 'success', 'result' => $result], JSON_THROW_ON_ERROR));
} catch (Throwable $e) {
    file_put_contents($output, json_encode(['status' => 'error', 'class' => $e::class, 'message' => $e->getMessage()], JSON_THROW_ON_ERROR));
}
