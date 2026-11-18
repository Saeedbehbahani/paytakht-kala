<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\IntegerMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(private InventoryService $inventory, private AuditService $audit) {}

    public function create(array $data, int $userId): Sale
    {
        return DB::transaction(function () use ($data, $userId) {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            if ($old = Sale::where('request_uuid', $data['request_uuid'])->lockForUpdate()->first()) {
                abort_unless($old->seller_user_id === $userId, 403);

                return $old->load('items');
            }
            $items = collect($data['items'])->sortBy('product_id')->values();
            if ($items->isEmpty() || $items->pluck('product_id')->unique()->count() !== $items->count()) {
                throw ValidationException::withMessages(['items' => 'یک کالای معتبر برای هر ردیف وارد کنید.']);
            }
            $products = Product::whereIn('id', $items->pluck('product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $total = 0;
            $profit = 0;
            $lines = [];
            foreach ($items as $i) {
                $p = $products[(int) $i['product_id']] ?? throw ValidationException::withMessages(['items' => 'کالای نامعتبر است.']);
                $q = IntegerMath::positive((int) $i['quantity'], IntegerMath::MAX_QUANTITY, 'items');
                if (! $p->active || $p->status !== 'ACTIVE' || $p->stock_quantity < $q) {
                    throw ValidationException::withMessages(['items' => "موجودی {$p->name} کافی نیست."]);
                }
                $sp = IntegerMath::nonNegative((int) $i['unit_price_toman'], IntegerMath::MAX_MONEY, 'items');
                $pp = IntegerMath::nonNegative((int) $i['purchase_price_toman'], IntegerMath::MAX_MONEY, 'items');
                $line = IntegerMath::multiply($sp, $q, IntegerMath::MAX_MONEY, 'items');
                $lineProfit = $sp >= $pp
                    ? IntegerMath::multiply($sp - $pp, $q, IntegerMath::MAX_MONEY, 'items')
                    : -IntegerMath::multiply($pp - $sp, $q, IntegerMath::MAX_MONEY, 'items');
                $total = IntegerMath::add($total, $line, IntegerMath::MAX_MONEY, 'items');
                $profit += $lineProfit;
                if (abs($profit) > IntegerMath::MAX_MONEY) {
                    throw ValidationException::withMessages(['items' => 'سود از محدوده امن بیشتر است.']);
                }
                $lines[] = compact('p', 'q', 'sp', 'pp', 'line');
            }
            $sale = Sale::create(['request_uuid' => $data['request_uuid'], 'invoice_number' => 'PK-'.now()->format('Ymd-His').'-'.strtoupper(substr((string) str()->uuid(), 0, 6)), 'seller_user_id' => $userId, 'created_by_user_id' => $userId, 'subtotal_toman' => $total, 'total_toman' => $total, 'paid_amount_toman' => 0, 'receivable_amount_toman' => 0, 'estimated_profit_toman' => $profit, 'sensitive_flag' => false, 'sensitive_reason' => $data['note'] ?? null, 'business_at' => $data['business_at'] ?? now()]);
            foreach ($lines as $x) {
                $unknownSold = min((int) $x['p']->unknown_cost_quantity, $x['q']);
                $sale->items()->create(['product_id' => $x['p']->id, 'quantity' => $x['q'], 'unit_price_toman' => $x['sp'], 'purchase_price_snapshot_toman' => $x['pp'], 'cost_snapshot_toman' => $unknownSold === $x['q'] ? null : $x['p']->average_cost_toman, 'replacement_cost_snapshot_toman' => $x['pp'], 'unknown_cost_quantity_snapshot' => $unknownSold, 'line_discount_toman' => 0, 'line_total_toman' => $x['line']]);
                $this->inventory->moveLocked($x['p'], -$x['q'], 'SALE', 'SALE', $sale->id, $userId, $unknownSold === $x['q'] ? null : $x['p']->average_cost_toman, null, null, -$unknownSold);
                $x['p']->update(['last_sale_at' => now(), 'last_sale_price_toman' => $x['sp']]);
            }
            $first = $lines[0];
            $this->audit->record('sale', 'SALE', $sale->id, [], ['total' => $total, 'profit' => $profit], "فروش {$first['q']} عدد {$first['p']->name} · سود ".number_format($profit).' تومان', $userId);

            return $sale->load('items.product');
        }, 5);
    }
}
