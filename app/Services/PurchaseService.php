<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\User;
use App\Support\IntegerMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(private InventoryService $inventory, private AuditService $audit, private CostingService $costing) {}

    public function create(array $data, int $userId): Purchase
    {
        return DB::transaction(function () use ($data, $userId) {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            if ($old = Purchase::where('request_uuid', $data['request_uuid'])->lockForUpdate()->first()) {
                abort_unless($old->created_by_user_id === $userId, 403);

                return $old->load('items');
            }
            $items = collect($data['items'])->sortBy('product_id')->values();
            if ($items->isEmpty() || $items->pluck('product_id')->unique()->count() !== $items->count()) {
                throw ValidationException::withMessages(['items' => 'یک کالای معتبر برای هر ردیف وارد کنید.']);
            }
            $products = Product::whereIn('id', $items->pluck('product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $total = 0;
            foreach ($items as $i) {
                $q = IntegerMath::positive((int) $i['quantity'], IntegerMath::MAX_QUANTITY, 'items');
                $c = IntegerMath::positive((int) $i['unit_cost_toman'], IntegerMath::MAX_MONEY, 'items');
                $total = IntegerMath::add($total, IntegerMath::multiply($q, $c, IntegerMath::MAX_MONEY, 'items'), IntegerMath::MAX_MONEY, 'items');
            }
            $purchase = Purchase::create(['request_uuid' => $data['request_uuid'], 'subtotal_toman' => $total, 'total_toman' => $total, 'paid_amount_toman' => 0, 'remaining_amount_toman' => 0, 'note' => $data['note'] ?? null, 'created_by_user_id' => $userId]);
            foreach ($items as $i) {
                $p = $products[(int) $i['product_id']] ?? throw ValidationException::withMessages(['items' => 'کالا نامعتبر است.']);
                if (! $p->active || $p->status !== 'ACTIVE') {
                    throw ValidationException::withMessages(['items' => 'کالای غیرفعال قابل ورود نیست.']);
                }
                $q = (int) $i['quantity'];
                $c = (int) $i['unit_cost_toman'];
                $purchase->items()->create(['product_id' => $p->id, 'quantity' => $q, 'unit_cost_toman' => $c, 'line_total_toman' => $q * $c, 'allocated_cost_toman' => $q * $c]);
                $this->costing->applyInboundLocked($p, $q, $q * $c);
                $this->inventory->moveLocked($p, $q, 'PURCHASE', 'PURCHASE', $purchase->id, $userId, $c, null, null, 0);
                $p->update(['last_replacement_cost_toman' => $c]);
            }
            $purchase->update(['business_at' => $data['business_at'] ?? now()]);
            $first = $items->first();
            $firstProduct = $products[(int) $first['product_id']];
            $this->audit->record('stock_in', 'PURCHASE', $purchase->id, [], ['total' => $total], "ورود {$first['quantity']} عدد {$firstProduct->name}", $userId);

            return $purchase->load('items.product');
        }, 5);
    }
}
