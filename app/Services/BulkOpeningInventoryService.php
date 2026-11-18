<?php

namespace App\Services;

use App\Models\Product;
use App\Support\IntegerMath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BulkOpeningInventoryService
{
    private const HEADERS = ['barcode', 'sku', 'name', 'brand', 'model', 'quantity', 'purchase_cost_toman'];

    public function __construct(private OperationsService $operations, private AuditService $audit, private ExceptionService $exceptions) {}

    public function preview(UploadedFile $file, string $requestUuid, int $sessionId, int $categoryId, int $userId): int
    {
        if ($id = DB::table('opening_inventory_imports')->where('request_uuid', $requestUuid)->value('id')) {
            return $id;
        }
        $hash = hash_file('sha256', $file->getRealPath());
        if ($id = DB::table('opening_inventory_imports')->where('file_hash', $hash)->where('opening_inventory_session_id', $sessionId)->where('status', 'PREVIEW')->value('id')) {
            return $id;
        }

        return DB::transaction(function () use ($file, $requestUuid, $sessionId, $categoryId, $userId, $hash) {
            $session = DB::table('opening_inventory_sessions')->where('id', $sessionId)->where('status', 'DRAFT')->lockForUpdate()->first();
            if (! $session) {
                throw ValidationException::withMessages(['session' => 'جلسه موجودی افتتاحیه فعال نیست.']);
            }
            $importId = DB::table('opening_inventory_imports')->insertGetId(['request_uuid' => $requestUuid, 'file_hash' => $hash, 'status' => 'PREVIEW', 'opening_inventory_session_id' => $sessionId, 'default_category_id' => $categoryId, 'created_by_user_id' => $userId, 'created_at' => now(), 'updated_at' => now()]);
            $handle = fopen($file->getRealPath(), 'rb');
            $header = fgetcsv($handle);
            $header = array_map(fn ($value) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $value)), $header ?: []);
            if ($header !== self::HEADERS) {
                fclose($handle);
                throw ValidationException::withMessages(['csv' => 'ستون‌های فایل موجودی افتتاحیه باید دقیقاً مطابق فایل نمونه باشند.']);
            }

            $seen = [];
            $line = 1;
            $counts = ['VALID' => 0, 'INVALID' => 0, 'DUPLICATE' => 0];
            $buffer = [];
            while (($row = fgetcsv($handle)) !== false) {
                $line++;
                if (count($row) === 1 && trim((string) $row[0]) === '') {
                    continue;
                }
                $payload = count($row) === count(self::HEADERS) ? array_combine(self::HEADERS, array_map('trim', $row)) : [];
                [$status, $error, $productId] = $this->classifyRow($payload, $seen);
                $counts[$status]++;
                $buffer[] = ['opening_inventory_import_id' => $importId, 'line_number' => $line, 'barcode' => $payload['barcode'] ?? null, 'sku' => $payload['sku'] ?? null, 'name' => $payload['name'] ?? null, 'brand' => $payload['brand'] ?? null, 'model' => $payload['model'] ?? null, 'quantity' => isset($payload['quantity']) && ctype_digit($payload['quantity']) ? (int) $payload['quantity'] : null, 'purchase_cost_toman' => isset($payload['purchase_cost_toman']) && ctype_digit($payload['purchase_cost_toman']) ? (int) $payload['purchase_cost_toman'] : null, 'product_id' => $productId, 'row_status' => $status, 'error_message' => $error, 'raw_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now()];
                if (count($buffer) >= 250) {
                    DB::table('opening_inventory_import_rows')->insert($buffer);
                    $buffer = [];
                }
            }
            fclose($handle);
            if ($buffer) {
                DB::table('opening_inventory_import_rows')->insert($buffer);
            }
            if (array_sum($counts) === 0) {
                throw ValidationException::withMessages(['csv' => 'فایل هیچ ردیف کالایی ندارد.']);
            }
            DB::table('opening_inventory_imports')->where('id', $importId)->update(['valid_rows' => $counts['VALID'], 'invalid_rows' => $counts['INVALID'], 'duplicate_rows' => $counts['DUPLICATE'], 'updated_at' => now()]);

            return $importId;
        }, 5);
    }

    public function finalize(int $importId, int $userId): void
    {
        DB::transaction(function () use ($importId, $userId) {
            $import = DB::table('opening_inventory_imports')->where('id', $importId)->lockForUpdate()->first();
            if (! $import || $import->status !== 'PREVIEW') {
                throw ValidationException::withMessages(['import' => 'این فایل قبلاً نهایی شده یا معتبر نیست.']);
            }
            if ($import->invalid_rows > 0 || $import->duplicate_rows > 0) {
                throw ValidationException::withMessages(['import' => 'پیش از نهایی‌سازی باید ردیف‌های نامعتبر یا تکراری اصلاح و فایل دوباره بارگذاری شود.']);
            }

            DB::table('opening_inventory_import_rows')->where('opening_inventory_import_id', $importId)->orderBy('id')->chunkById(250, function ($rows) use ($import, $userId) {
                foreach ($rows as $row) {
                    $product = $row->product_id ? Product::query()->whereKey($row->product_id)->lockForUpdate()->first() : null;
                    if (! $product) {
                        $sku = filled($row->sku) ? $row->sku : 'DRAFT-'.strtoupper(substr((string) Str::uuid(), 0, 12));
                        if (Product::query()->where('sku', $sku)->orWhere(fn ($query) => filled($row->barcode) && $query->where('barcode', $row->barcode))->exists()) {
                            throw ValidationException::withMessages(['import' => "کد ردیف {$row->line_number} هم‌زمان با Preview استفاده شده است؛ فایل را دوباره بررسی کنید."]);
                        }
                        $product = Product::create(['category_id' => $import->default_category_id, 'name' => $row->name, 'sku' => $sku, 'barcode' => $row->barcode ?: null, 'brand' => $row->brand ?: null, 'model' => $row->model ?: null, 'sale_price_toman' => 0, 'slug' => Str::slug($row->name.'-'.$sku), 'status' => 'DRAFT', 'active' => false, 'is_public' => false, 'online_sale_enabled' => false, 'created_by_user_id' => $userId]);
                        $this->exceptions->detect('PRODUCT', 'MEDIUM', 'PRODUCT', $product->id, 'DRAFT_PRODUCT', ['source' => 'BULK_OPENING', 'import_id' => $import->id], "bulk:{$import->id}:{$row->line_number}");
                    }
                    if (DB::table('stock_movements')->where('product_id', $product->id)->exists()) {
                        throw ValidationException::withMessages(['import' => "کالای ردیف {$row->line_number} قبلاً گردش موجودی دارد."]);
                    }
                    DB::table('opening_inventory_items')->updateOrInsert(['opening_inventory_session_id' => $import->opening_inventory_session_id, 'product_id' => $product->id], ['quantity' => $row->quantity, 'unit_cost_toman' => $row->purchase_cost_toman, 'sale_price_toman' => $product->sale_price_toman, 'created_at' => now(), 'updated_at' => now()]);
                    DB::table('opening_inventory_import_rows')->where('id', $row->id)->update(['product_id' => $product->id, 'updated_at' => now()]);
                }
            }, 'id');

            $this->operations->finalizeOpening($import->opening_inventory_session_id, $userId);
            DB::table('opening_inventory_imports')->where('id', $importId)->update(['status' => 'FINALIZED', 'finalized_by_user_id' => $userId, 'finalized_at' => now(), 'updated_at' => now()]);
            $this->audit->record('bulk_opening_finalize', 'OPENING_INVENTORY_IMPORT', $importId, [], ['rows' => $import->valid_rows], 'ورود گروهی موجودی افتتاحیه نهایی شد', $userId);
        }, 5);
    }

    private function classifyRow(array $payload, array &$seen): array
    {
        if (! $payload || blank($payload['name'] ?? null) || (blank($payload['sku'] ?? null) && blank($payload['barcode'] ?? null))) {
            return ['INVALID', 'نام و حداقل یکی از SKU یا بارکد الزامی است.', null];
        }
        if (! ctype_digit((string) ($payload['quantity'] ?? '')) || (int) $payload['quantity'] < 1 || (int) $payload['quantity'] > IntegerMath::MAX_QUANTITY || ! ctype_digit((string) ($payload['purchase_cost_toman'] ?? '')) || (int) $payload['purchase_cost_toman'] < 0 || (int) $payload['purchase_cost_toman'] > IntegerMath::MAX_MONEY) {
            return ['INVALID', 'تعداد یا بهای خرید معتبر نیست.', null];
        }
        foreach (array_filter([$payload['sku'] ?? null, $payload['barcode'] ?? null]) as $code) {
            if (isset($seen[$code])) {
                return ['DUPLICATE', "کد در ردیف {$seen[$code]} تکرار شده است.", null];
            }
            $seen[$code] = true;
        }
        $matches = Product::query()->where(fn ($query) => $query->where('sku', $payload['sku'] ?: '__none__')->orWhere('barcode', $payload['barcode'] ?: '__none__'))->get();
        if ($matches->count() > 1) {
            return ['DUPLICATE', 'SKU و بارکد به دو کالای متفاوت اشاره می‌کنند.', null];
        }

        return ['VALID', $matches->isEmpty() ? 'کالای جدید به‌صورت موقت ساخته می‌شود.' : null, $matches->first()?->id];
    }
}
