<?php

namespace App\Services;

use App\Models\Product;
use App\Support\IntegerMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function availableStock(Product $product): int
    {
        // Future reservations subtract here without changing sale controllers.
        return $product->stock_quantity;
    }

    public function moveLocked(Product $product, int $delta, string $type, string $referenceType, int $referenceId, int $userId, ?int $unitCost = null, ?string $reason = null, ?string $requestUuid = null, ?int $unknownDelta = null): void
    {
        if (abs($delta) > IntegerMath::MAX_QUANTITY) {
            throw ValidationException::withMessages(['items' => 'تغییر موجودی از محدوده امن بیشتر است.']);
        }
        if ($delta > 0) {
            $next = IntegerMath::add((int) ($product->stock_quantity ?? 0), $delta, IntegerMath::MAX_QUANTITY, 'items');
        } else {
            $next = (int) ($product->stock_quantity ?? 0) + $delta;
            if ($next < 0) {
                throw ValidationException::withMessages(['items' => "موجودی {$product->name} کافی نیست."]);
            }
        }
        $beforeUnknown = (int) $product->unknown_cost_quantity;
        $unknownDelta ??= $delta > 0
            ? (($unitCost === null || $type === 'ADJUSTMENT') ? $delta : 0)
            : -min($beforeUnknown, -$delta);
        $nextUnknown = $beforeUnknown + $unknownDelta;
        if ($nextUnknown < 0 || $nextUnknown > $next || abs($unknownDelta) > abs($delta)
            || ($delta > 0 && $unknownDelta < 0) || ($delta < 0 && $unknownDelta > 0)) {
            throw ValidationException::withMessages(['items' => 'گردش موجودی دارای بهای نامعلوم ناسازگار است.']);
        }
        $updates = ['stock_quantity' => $next, 'unknown_cost_quantity' => $nextUnknown];
        if ($next > 0 && $next === $nextUnknown) {
            $updates['average_cost_toman'] = null;
        } elseif ($next - $nextUnknown > 0 && $product->average_cost_toman === null) {
            throw ValidationException::withMessages(['items' => 'بهای موجودی مشخص نشده است.']);
        }
        $product->forceFill($updates)->save();
        DB::table('stock_movements')->insert([
            'request_uuid' => $requestUuid, 'product_id' => $product->id, 'quantity_delta' => $delta, 'stock_after' => $next, 'movement_type' => $type,
            'reference_type' => $referenceType, 'reference_id' => $referenceId, 'unit_cost_snapshot_toman' => $unitCost,
            'created_by_user_id' => $userId, 'reason' => $reason, 'created_at' => now(),
            'unknown_cost_quantity_delta' => $unknownDelta, 'unknown_cost_quantity_after' => $nextUnknown,
        ]);
    }

    public function adjust(string $uuid, int $productId, int $actualQuantity, string $reason, int $userId): void
    {
        IntegerMath::nonNegative($actualQuantity, IntegerMath::MAX_QUANTITY, 'actual_quantity');
        DB::transaction(function () use ($uuid, $productId, $actualQuantity, $reason, $userId) {
            if (DB::table('stock_movements')->where('request_uuid', $uuid)->exists()) {
                return;
            }
            $product = Product::query()->lockForUpdate()->findOrFail($productId);
            $before = $product->stock_quantity;
            $delta = $actualQuantity - $before;
            if (DB::table('stock_movements')->where('request_uuid', $uuid)->exists()) {
                return;
            }
            if ($delta === 0) {
                return;
            }
            $ref = (int) sprintf('%u', crc32($uuid));
            $unknownDelta = $delta > 0 ? $delta : -min((int) $product->unknown_cost_quantity, -$delta);
            $cost = $delta > 0 || -$unknownDelta === -$delta ? null : $product->average_cost_toman;
            $this->moveLocked($product, $delta, 'ADJUSTMENT', 'ADJUSTMENT', $ref, $userId, $cost, $reason, $uuid, $unknownDelta);
            app(AuditService::class)->record('stock_adjustment', 'PRODUCT', $product->id, ['stock_quantity' => $before], ['stock_quantity' => $actualQuantity, 'reason' => $reason], "اصلاح موجودی {$product->name}", $userId);
        });
    }
}
