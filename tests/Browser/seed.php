<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\PurchaseService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$user = User::create([
    'name' => 'مرورگر آزمایشی',
    'email' => 'browser@paytakht.test',
    'phone' => '09120000000',
    'role' => 'EMPLOYEE',
    'active' => true,
    'password' => 'BrowserOnly-2026!',
]);

$category = Category::create(['name' => 'آزمایش مرورگر', 'active' => true]);
$mixed = Product::create([
    'category_id' => $category->id,
    'name' => 'آب مرکبات‌گیری حرفه‌ای با نام بسیار بلند ویژه فروشگاه مرکزی',
    'sku' => 'BOOTSTRAP-20260921-9999',
    'barcode' => '6261234567890',
    'brand' => 'سایا',
    'model' => 'ULTRA-LONG-MODEL-XR-2026-PRO-MAX',
    'slug' => 'mixed-rtl-ltr-browser-product',
    'sale_price_toman' => 9_200_000_000,
    'minimum_stock' => 200_000,
    'status' => 'ACTIVE',
    'active' => true,
    'created_by_user_id' => $user->id,
]);
app(PurchaseService::class)->create([
    'request_uuid' => (string) str()->uuid(),
    'items' => [['product_id' => $mixed->id, 'quantity' => 123_456, 'unit_cost_toman' => 8_800_000_000]],
], $user->id);

Product::create([
    'category_id' => $category->id,
    'name' => 'کتری بدون موجودی',
    'sku' => 'AUTO-INTERNAL-ZERO',
    'model' => 'ZERO-2026',
    'slug' => 'zero-stock-browser-product',
    'sale_price_toman' => 0,
    'minimum_stock' => 2,
    'status' => 'ACTIVE',
    'active' => true,
    'created_by_user_id' => $user->id,
]);
