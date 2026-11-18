<?php

namespace App\Services;

use App\Models\Cheque;
use App\Support\IntegerMath;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpeningObligationService
{
    public function __construct(private AuditService $audit) {}

    public function createBatch(string $requestUuid, int $userId, ?string $note = null): int
    {
        return DB::transaction(function () use ($requestUuid, $userId, $note) {
            if ($id = DB::table('opening_obligation_batches')->where('request_uuid', $requestUuid)->value('id')) {
                return $id;
            }

            $id = DB::table('opening_obligation_batches')->insertGetId(['request_uuid' => $requestUuid, 'status' => 'DRAFT', 'note' => $note, 'created_by_user_id' => $userId, 'created_at' => now(), 'updated_at' => now()]);
            $this->audit->record('opening_obligation_batch_create', 'OPENING_OBLIGATION_BATCH', $id, [], ['status' => 'DRAFT'], 'بسته تعهدات افتتاحیه ایجاد شد', $userId);

            return $id;
        }, 5);
    }

    public function addItem(int $batchId, array $data, int $userId): int
    {
        return DB::transaction(function () use ($batchId, $data) {
            $batch = DB::table('opening_obligation_batches')->where('id', $batchId)->lockForUpdate()->first();
            if (! $batch || $batch->status !== 'DRAFT') {
                throw ValidationException::withMessages(['batch' => 'بسته افتتاحیه قابل ویرایش نیست.']);
            }
            $type = (string) $data['obligation_type'];
            $amount = IntegerMath::positive((int) $data['amount_toman'], IntegerMath::MAX_MONEY, 'amount_toman');
            if (in_array($type, ['RECEIVABLE', 'INCOMING_CHEQUE'], true) && empty($data['customer_id'])) {
                throw ValidationException::withMessages(['customer_id' => 'برای طلب یا چک ورودی، مشتری الزامی است.']);
            }
            if (in_array($type, ['PAYABLE', 'OUTGOING_CHEQUE'], true) && empty($data['supplier_id'])) {
                throw ValidationException::withMessages(['supplier_id' => 'برای بدهی یا چک خروجی، تأمین‌کننده الزامی است.']);
            }
            if (str_contains($type, 'CHEQUE') && (blank($data['bank_name'] ?? null) || blank($data['cheque_number'] ?? null) || blank($data['due_date'] ?? null))) {
                throw ValidationException::withMessages(['cheque_number' => 'نام بانک، شماره چک و سررسید برای چک افتتاحیه الزامی است.']);
            }

            return DB::table('opening_obligation_items')->insertGetId([
                'opening_obligation_batch_id' => $batchId, 'obligation_type' => $type,
                'customer_id' => $data['customer_id'] ?? null, 'supplier_id' => $data['supplier_id'] ?? null,
                'amount_toman' => $amount, 'due_date' => $data['due_date'] ?? null,
                'installment_count' => $data['installment_count'] ?? null, 'bank_name' => $data['bank_name'] ?? null,
                'cheque_number' => $data['cheque_number'] ?? null, 'note' => $data['note'] ?? null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }, 5);
    }

    public function finalize(int $batchId, int $userId): void
    {
        DB::transaction(function () use ($batchId, $userId) {
            $batch = DB::table('opening_obligation_batches')->where('id', $batchId)->lockForUpdate()->first();
            if (! $batch || $batch->status !== 'DRAFT') {
                throw ValidationException::withMessages(['batch' => 'این بسته قبلاً نهایی شده است.']);
            }
            $items = DB::table('opening_obligation_items')->where('opening_obligation_batch_id', $batchId)->orderBy('id')->lockForUpdate()->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'حداقل یک تعهد افتتاحیه لازم است.']);
            }

            foreach ($items as $item) {
                if (in_array($item->obligation_type, ['RECEIVABLE', 'INCOMING_CHEQUE'], true)) {
                    $receivableId = DB::table('receivables')->insertGetId(['customer_id' => $item->customer_id, 'sale_id' => null, 'source_type' => 'OPENING', 'original_amount_toman' => $item->amount_toman, 'remaining_amount_toman' => $item->amount_toman, 'due_date' => $item->due_date, 'opening_batch_id' => $batchId, 'created_by_user_id' => $userId, 'status' => 'OPEN', 'created_at' => now(), 'updated_at' => now()]);
                    if ($item->obligation_type === 'INCOMING_CHEQUE') {
                        Cheque::create(['request_uuid' => (string) str()->uuid(), 'direction' => 'INCOMING', 'customer_id' => $item->customer_id, 'receivable_id' => $receivableId, 'opening_batch_id' => $batchId, 'bank_name' => $item->bank_name, 'cheque_number' => $item->cheque_number, 'amount_toman' => $item->amount_toman, 'due_date' => $item->due_date, 'status' => 'PENDING', 'note' => $item->note, 'created_by_user_id' => $userId]);
                    } elseif ((int) $item->installment_count > 0) {
                        $this->createInstallments($receivableId, (int) $item->customer_id, (int) $item->amount_toman, (int) $item->installment_count, $item->due_date);
                    }
                } else {
                    $payableId = DB::table('payables')->insertGetId(['supplier_id' => $item->supplier_id, 'purchase_id' => null, 'source_type' => 'OPENING', 'original_amount_toman' => $item->amount_toman, 'remaining_amount_toman' => $item->amount_toman, 'due_date' => $item->due_date, 'opening_batch_id' => $batchId, 'created_by_user_id' => $userId, 'status' => 'OPEN', 'created_at' => now(), 'updated_at' => now()]);
                    if ($item->obligation_type === 'OUTGOING_CHEQUE') {
                        Cheque::create(['request_uuid' => (string) str()->uuid(), 'direction' => 'OUTGOING', 'supplier_id' => $item->supplier_id, 'payable_id' => $payableId, 'opening_batch_id' => $batchId, 'bank_name' => $item->bank_name, 'cheque_number' => $item->cheque_number, 'amount_toman' => $item->amount_toman, 'due_date' => $item->due_date, 'status' => 'PENDING', 'note' => $item->note, 'created_by_user_id' => $userId]);
                    }
                }
            }

            DB::table('opening_obligation_batches')->where('id', $batchId)->update(['status' => 'FINALIZED', 'finalized_by_user_id' => $userId, 'finalized_at' => now(), 'updated_at' => now()]);
            $this->audit->record('opening_obligation_finalize', 'OPENING_OBLIGATION_BATCH', $batchId, [], ['items' => $items->count()], 'تعهدات افتتاحیه نهایی شد', $userId);
        }, 5);
    }

    private function createInstallments(int $receivableId, int $customerId, int $amount, int $count, ?string $firstDueDate): void
    {
        $count = max(1, min($count, 120));
        $base = intdiv($amount, $count);
        $remainder = $amount % $count;
        $due = $firstDueDate ? Carbon::parse($firstDueDate) : now()->addMonth();
        for ($sequence = 1; $sequence <= $count; $sequence++) {
            DB::table('installments')->insert(['receivable_id' => $receivableId, 'sale_id' => null, 'customer_id' => $customerId, 'sequence' => $sequence, 'amount_toman' => $base + ($sequence === $count ? $remainder : 0), 'due_date' => $due->copy()->addMonths($sequence - 1)->toDateString(), 'paid_amount_toman' => 0, 'status' => 'PENDING', 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
