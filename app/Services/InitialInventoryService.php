<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class InitialInventoryService
{
    public const BATCH = 'paytakht-initial-inventory-2026-09-19-v1';

    public const CHECKSUM = 'c566e0a3c4c621cebaa09a57f0cc60f3125f9d84eda152a1cf4ea671a2010f3a';

    public function isApplied(): bool
    {
        return DB::table('initial_inventory_batches')->where('identifier', self::BATCH)->where('status', 'APPLIED')->exists();
    }

    public function dataset(): array
    {
        $text = file_get_contents(database_path('data/paytakht_initial_inventory_2026_09_19.tsv'));
        if (hash('sha256', $text) !== self::CHECKSUM) {
            throw new RuntimeException('Authoritative dataset checksum mismatch.');
        }
        $rows = [];
        foreach (explode("\n", rtrim($text, "\n")) as $line) {
            [$page, $name, $quantity] = explode("\t", $line);
            $rows[] = ['page' => (int) $page, 'name' => $name, 'quantity' => (int) $quantity];
        }
        if (count($rows) !== 235 || array_sum(array_column($rows, 'quantity')) !== 396 || count(array_unique(array_column($rows, 'name'))) !== 235) {
            throw new RuntimeException('Expected 235 distinct rows and 396 units.');
        }

        return $rows;
    }

    public function preview(): array
    {
        $products = Product::all();

        return array_map(function ($row) use ($products) {
            // Exact Unicode identity only. Never rewrite the supplied display name.
            $matches = $products->filter(fn ($product) => trim($product->name) === trim($row['name']));
            $status = 'CREATE';
            if ($matches->isNotEmpty()) {
                $status = $matches->count() === 1 && ! app(ProductLifecycleService::class)->hasHistory($matches->first()) ? 'SAFE_MATCH' : 'CONFLICT';
            }

            return $row + ['status' => $status, 'product_id' => $matches->count() === 1 ? $matches->first()->id : null, 'apply_status' => $status === 'CONFLICT' ? 'BLOCKED' : 'READY'];
        }, $this->dataset());
    }

    public function apply(int $userId): array
    {
        return DB::transaction(function () use ($userId) {
            $existing = DB::table('initial_inventory_batches')->where('identifier', self::BATCH)->lockForUpdate()->first();
            if ($existing?->status === 'APPLIED') {
                return ['already_applied' => true, 'created' => 0, 'matched' => 0, 'quantity' => 0];
            }
            $preview = $this->preview();
            if (collect($preview)->contains('status', 'CONFLICT')) {
                throw new RuntimeException('Import has existing-product conflicts; apply was not started.');
            }
            $batchId = $existing?->id ?? DB::table('initial_inventory_batches')->insertGetId([
                'identifier' => self::BATCH, 'checksum' => self::CHECKSUM, 'status' => 'PENDING',
                'source_rows' => 235, 'source_quantity' => 396, 'created_by_user_id' => $userId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $created = $matched = $quantity = 0;
            foreach ($preview as $index => $row) {
                $product = $row['product_id'] ? Product::query()->whereKey($row['product_id'])->lockForUpdate()->first() : null;
                if ($product) {
                    $matched++;
                } else {
                    $sku = sprintf('BOOTSTRAP-20260919-%03d', $index + 1);
                    $product = Product::create([
                        'category_id' => null, 'name' => $row['name'], 'sku' => $sku,
                        'barcode' => null, 'brand' => null, 'model' => null,
                        'sale_price_toman' => 0, 'minimum_stock' => null,
                        'slug' => Str::slug($row['name'].'-'.$sku), 'status' => 'ACTIVE', 'active' => true,
                        'is_public' => false, 'online_sale_enabled' => false, 'created_by_user_id' => $userId,
                        'approved_by_user_id' => $userId, 'approved_at' => now(),
                    ]);
                    $product->forceFill(['average_cost_toman' => null, 'unknown_cost_quantity' => 0])->save();
                    $created++;
                }
                if ((int) $product->stock_quantity !== 0 || DB::table('stock_movements')->where('product_id', $product->id)->exists()) {
                    throw new RuntimeException("Product {$product->name} changed after preview; transaction rolled back.");
                }
                app(InventoryService::class)->moveLocked($product, $row['quantity'], 'OPENING', 'INITIAL_INVENTORY_BATCH', $batchId, $userId, null, 'موجودی اولیه با بهای تاریخی نامعلوم', null, $row['quantity']);
                DB::table('initial_inventory_entries')->insert([
                    'initial_inventory_batch_id' => $batchId, 'product_id' => $product->id,
                    'source_row' => $index + 1, 'page' => $row['page'], 'source_name' => $row['name'],
                    'quantity' => $row['quantity'], 'created_product' => $row['product_id'] === null,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $quantity += $row['quantity'];
            }
            DB::table('initial_inventory_batches')->where('id', $batchId)->update(['status' => 'APPLIED', 'applied_at' => now(), 'updated_at' => now()]);
            app(AuditService::class)->record('initial_inventory_apply', 'INITIAL_INVENTORY_BATCH', $batchId, [], ['rows' => 235, 'quantity' => 396, 'checksum' => self::CHECKSUM], 'موجودی اولیه با بهای تاریخی نامعلوم ثبت شد', $userId);

            return compact('created', 'matched', 'quantity') + ['already_applied' => false];
        }, 5);
    }
}
