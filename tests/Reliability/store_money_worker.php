<?php

use App\Services\OperationsService;
use App\Services\PurchaseService;
use App\Services\StoreMoneyService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$task = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
while (microtime(true) < $task['start_at']) {
    usleep(1000);
}

try {
    $result = match ($task['operation']) {
        'purchase' => app(PurchaseService::class)->create([
            'request_uuid' => $task['uuid'], 'supplier_id' => $task['supplier_id'],
            'items' => isset($task['product_ids'])
                ? array_map(fn ($id) => ['product_id' => $id, 'quantity' => 1, 'unit_cost_toman' => intdiv($task['amount'], 2)], $task['product_ids'])
                : [['product_id' => $task['product_id'], 'quantity' => 1, 'unit_cost_toman' => $task['amount']]],
            'payments' => [['funding_source' => 'STORE', 'method' => 'TRANSFER', 'amount_toman' => $task['amount']]],
        ], $task['user_id'])->id,
        'expense' => app(OperationsService::class)->expense([
            'request_uuid' => $task['uuid'], 'expense_category_id' => $task['category_id'],
            'amount_toman' => $task['amount'], 'funding_source' => 'STORE', 'payment_method' => 'TRANSFER',
        ], $task['user_id'])->id,
        'reimburse' => (function () use ($task) {
            app(OperationsService::class)->reimbursePersonalPayable($task['uuid'], $task['personal_payable_id'], $task['amount'], 'TRANSFER', $task['user_id']);

            return $task['personal_payable_id'];
        })(),
        'deadlock' => (function () use ($task) {
            $attempts = 0;
            DB::transaction(function () use ($task, &$attempts) {
                $attempts++;
                DB::table('qa_lock_rows')->where('id', $task['first_lock'])->lockForUpdate()->first();
                usleep(200000);
                DB::table('qa_lock_rows')->where('id', $task['second_lock'])->lockForUpdate()->first();
                app(StoreMoneyService::class)->record('OUT', 1, 'QA_DEADLOCK', $task['user_id'], 'RACE', $task['sequence'], $task['user_id'], $task['uuid']);
            }, 5);

            return $attempts;
        })(),
    };
    $output = ['status' => 'success', 'id' => $result];
} catch (Throwable $exception) {
    $output = ['status' => 'error', 'class' => get_class($exception), 'message' => $exception->getMessage()];
}
file_put_contents($argv[2], json_encode($output, JSON_THROW_ON_ERROR));
