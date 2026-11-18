<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductLifecycleService
{
    public function hasHistory(Product $product): bool
    {
        if ((int) $product->stock_quantity !== 0) {
            return true;
        }
        foreach (['sale_items', 'purchase_items', 'sale_return_items', 'purchase_return_items', 'stock_movements', 'opening_inventory_items', 'stock_count_items', 'opening_inventory_import_rows'] as $table) {
            if (DB::table($table)->where('product_id', $product->id)->exists()) {
                return true;
            }
        }

        return DB::table('financial_movements')->where('reference_type', 'PRODUCT')->where('reference_id', $product->id)->exists();
    }

    public function delete(int $productId, int $userId): void
    {
        DB::transaction(function () use ($productId, $userId) {
            $product = Product::query()->lockForUpdate()->findOrFail($productId);
            $entry = DB::table('initial_inventory_entries')->where('product_id', $product->id)->lockForUpdate()->first();
            if ($entry) {
                $bootstrapMovement = DB::table('stock_movements')->where('product_id', $product->id)
                    ->where('reference_type', 'INITIAL_INVENTORY_BATCH')->where('reference_id', $entry->initial_inventory_batch_id)->first();
                $otherHistory = DB::table('stock_movements')->where('product_id', $product->id)
                    ->where(fn ($q) => $q->where('reference_type', '<>', 'INITIAL_INVENTORY_BATCH')->orWhere('reference_id', '<>', $entry->initial_inventory_batch_id))->exists();
                foreach (['sale_items', 'purchase_items', 'sale_return_items', 'purchase_return_items', 'opening_inventory_items', 'stock_count_items', 'opening_inventory_import_rows'] as $table) {
                    $otherHistory = $otherHistory || DB::table($table)->where('product_id', $product->id)->exists();
                }
                if ($entry->created_product && $bootstrapMovement && ! $otherHistory
                    && (int) $product->stock_quantity === (int) $entry->quantity
                    && (int) $product->unknown_cost_quantity === (int) $entry->quantity) {
                    app(AuditService::class)->record('bootstrap_product_delete', 'PRODUCT', $product->id, $product->toArray(), [], 'حذف کالای اشتباه موجودی اولیه و اثر دفتر آن', $userId);
                    DB::table('initial_inventory_entries')->where('id', $entry->id)->delete();
                    DB::table('stock_movements')->where('id', $bootstrapMovement->id)->delete();
                    $product->delete();

                    return;
                }
                throw ValidationException::withMessages(['product' => 'این کالا پس از موجودی اولیه دارای سابقه عملیاتی است و حذف کامل آن مجاز نیست. پیشنهاد: آرشیو کردن کالا']);
            }
            if ($this->hasHistory($product)) {
                throw ValidationException::withMessages(['product' => 'این کالا دارای سابقه فروش، خرید یا گردش موجودی است و حذف کامل آن باعث از بین رفتن سوابق می‌شود. پیشنهاد: آرشیو کردن کالا']);
            }
            app(AuditService::class)->record('product_delete', 'PRODUCT', $product->id, $product->toArray(), [], 'حذف محصول بدون سابقه', $userId);
            $product->delete();
        });
    }
}
