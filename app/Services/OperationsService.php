<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OperationsService
{
    public function __construct(private InventoryService $inventory, private AuditService $audit) {}

    public function expense(array $data, int $userId): Expense
    {
        return DB::transaction(function () use ($data, $userId) {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            if ($old = Expense::where('request_uuid', $data['request_uuid'])->lockForUpdate()->first()) {
                abort_unless($old->created_by_user_id === $userId, 403);

                return $old;
            }
            if (! empty($data['title'])) {
                $title = trim($data['title']);
                DB::table('expense_categories')->insertOrIgnore(['name' => $title, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
                $categoryId = DB::table('expense_categories')->where('name', $title)->value('id');
            } else {
                $categoryId = $data['expense_category_id'];
                $title = (string) DB::table('expense_categories')->where('id', $categoryId)->value('name');
            }
            $e = Expense::create(['request_uuid' => $data['request_uuid'], 'expense_category_id' => $categoryId, 'financial_account_id' => null, 'amount_toman' => $data['amount_toman'], 'note' => $data['note'] ?? null, 'receipt_path' => $data['receipt_path'] ?? null, 'sensitive_flag' => false, 'business_at' => $data['business_at'] ?? now(), 'created_by_user_id' => $userId]);
            $this->audit->record('expense', 'EXPENSE', $e->id, [], ['amount' => $e->amount_toman], 'هزینه '.$title.' · '.number_format($e->amount_toman).' تومان', $userId);

            return $e;
        });
    }

    public function voidExpense(int $id, string $reason, int $userId): void
    {
        DB::transaction(function () use ($id, $reason, $userId) {
            $e = Expense::lockForUpdate()->findOrFail($id);
            if ($e->status === 'VOIDED') {
                return;
            }$e->update(['status' => 'VOIDED', 'voided_by_user_id' => $userId, 'voided_at' => now(), 'void_reason' => $reason]);
            $this->audit->record('expense_void', 'EXPENSE', $id, [], ['reason' => $reason], 'هزینه باطل شد', $userId);
        });
    }

    public function adjustInventory(array $data, int $userId): void
    {
        $this->inventory->adjust($data['request_uuid'], (int) $data['product_id'], (int) $data['actual_quantity'], $data['reason'], $userId);
    }

    public function finalizeOpening(int $sessionId, int $userId): void
    {
        DB::transaction(function () use ($sessionId, $userId) {
            $s = DB::table('opening_inventory_sessions')->where('id', $sessionId)->lockForUpdate()->first();
            if (! $s || $s->status !== 'DRAFT') {
                return;
            }foreach (DB::table('opening_inventory_items')->where('opening_inventory_session_id', $sessionId)->get() as $i) {
                $p = Product::lockForUpdate()->find($i->product_id);
                if ($p && $p->stock_quantity === 0) {
                    $p->forceFill(['average_cost_toman' => $i->unit_cost_toman, 'sale_price_toman' => $i->sale_price_toman])->save();
                    $this->inventory->moveLocked($p, $i->quantity, 'OPENING', 'OPENING_SESSION', $sessionId, $userId, $i->unit_cost_toman);
                }
            }DB::table('opening_inventory_sessions')->where('id', $sessionId)->update(['status' => 'FINALIZED', 'finalized_by_user_id' => $userId, 'finalized_at' => now(), 'updated_at' => now()]);
        });
    }
}
