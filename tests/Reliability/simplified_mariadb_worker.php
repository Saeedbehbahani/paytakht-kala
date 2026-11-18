<?php

declare(strict_types=1);

use App\Services\PurchaseService;
use App\Services\SaleService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $operation, $userId, $productId, $uuid, $startAt] = $argv;
while (microtime(true) < (float) $startAt) {
    usleep(1000);
}

try {
    if ($operation === 'sale') {
        $record = app(SaleService::class)->create([
            'request_uuid' => $uuid,
            'items' => [[
                'product_id' => (int) $productId,
                'quantity' => 1,
                'purchase_price_toman' => 100,
                'unit_price_toman' => 150,
            ]]], (int) $userId);
    } elseif ($operation === 'stock-in-100' || $operation === 'stock-in-200') {
        $record = app(PurchaseService::class)->create([
            'request_uuid' => $uuid,
            'items' => [[
                'product_id' => (int) $productId,
                'quantity' => 5,
                'unit_cost_toman' => $operation === 'stock-in-100' ? 100 : 200,
            ]]], (int) $userId);
    } else {
        throw new RuntimeException('Unknown operation.');
    }
    echo json_encode(['status' => 'success', 'id' => $record->id], JSON_THROW_ON_ERROR);
} catch (ValidationException $exception) {
    echo json_encode(['status' => 'validation'], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage().PHP_EOL);
    exit(1);
}
