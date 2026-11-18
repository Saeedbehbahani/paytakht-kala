<?php

namespace App\Services;

use App\Models\Product;
use App\Support\IntegerMath;
use Illuminate\Validation\ValidationException;

class CostingService
{
    /**
     * @param  array<int, array{quantity:int,unit_cost_toman:int}>  $items
     * @return array<int, int>
     */
    public function allocate(array $items, int $discount, int $shipping): array
    {
        $rawLines = [];
        $subtotal = 0;
        foreach ($items as $key => $item) {
            $rawLines[$key] = IntegerMath::multiply((int) $item['quantity'], (int) $item['unit_cost_toman'], IntegerMath::MAX_MONEY, 'items');
            $subtotal = IntegerMath::add($subtotal, $rawLines[$key], IntegerMath::MAX_MONEY, 'items');
        }
        if ($subtotal <= 0 || $discount > $subtotal) {
            throw ValidationException::withMessages(['items' => 'جمع بهای خرید برای تخصیص هزینه معتبر نیست.']);
        }

        $netLanded = IntegerMath::add($subtotal - $discount, $shipping, IntegerMath::MAX_MONEY, 'shipping_cost_toman');
        $allocated = [];
        $assigned = 0;
        $lastKey = array_key_last($rawLines);
        foreach ($rawLines as $key => $raw) {
            $remaining = $netLanded - $assigned;
            $amount = $key === $lastKey
                ? $remaining
                : min($remaining, max(0, (int) round($netLanded * ($raw / $subtotal))));
            $allocated[$key] = $amount;
            $assigned += $amount;
        }

        return $allocated;
    }

    public function applyInboundLocked(Product $product, int $quantity, int $allocatedCost): int
    {
        $oldQuantity = (int) $product->stock_quantity - (int) $product->unknown_cost_quantity;
        $newQuantity = $oldQuantity + $quantity;
        if ($oldQuantity > 0 && $product->average_cost_toman === null) {
            throw ValidationException::withMessages(['items' => 'بهای موجودی دارای قیمت مشخص نامعتبر است.']);
        }
        $oldValue = $oldQuantity * (int) $product->average_cost_toman;
        $newAverage = $newQuantity === 0 ? 0 : (int) round(($oldValue + $allocatedCost) / $newQuantity);
        $product->forceFill(['average_cost_toman' => $newAverage])->save();

        return $newAverage;
    }

    public function reversalCost(int $allocatedCost, int $purchasedQuantity, int $alreadyReturned, int $returnQuantity): int
    {
        $before = (int) round($allocatedCost * ($alreadyReturned / $purchasedQuantity));
        $after = (int) round($allocatedCost * (($alreadyReturned + $returnQuantity) / $purchasedQuantity));

        return $after - $before;
    }

    public function applyOutboundReversalLocked(Product $product, int $quantity, int $costReversal): int
    {
        $oldQuantity = (int) $product->stock_quantity - (int) $product->unknown_cost_quantity;
        $newQuantity = $oldQuantity - $quantity;
        $newValue = ($oldQuantity * (int) $product->average_cost_toman) - $costReversal;
        if ($newQuantity < 0 || $newValue < 0) {
            throw ValidationException::withMessages(['items' => "معکوس‌کردن بهای کالای {$product->name} با گردش‌های بعدی سازگار نیست."]);
        }

        $newAverage = $newQuantity === 0 ? 0 : (int) round($newValue / $newQuantity);
        $product->forceFill(['average_cost_toman' => $newAverage])->save();

        return $newAverage;
    }
}
