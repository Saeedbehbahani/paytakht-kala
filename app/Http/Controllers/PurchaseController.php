<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\User;
use App\Services\AuditService;
use App\Services\PurchaseService;
use App\Support\IntegerMath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $query = Purchase::with(['items.product', 'creator'])->latest();
        if ($search = trim((string) $request->query('q'))) {
            $query->whereHas('items.product', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('model', 'like', "%{$search}%"));
        }
        if ($request->filled('period')) {
            $from = match ($request->query('period')) {
                'week' => now()->startOfWeek(6),
                'month' => now()->startOfMonth(),
                'range' => now()->parse($request->query('from'))->startOfDay(),
                default => now()->startOfDay(),
            };
            $until = $request->query('period') === 'range' ? now()->parse($request->query('to'))->addDay()->startOfDay() : now()->addDay()->startOfDay();
            $query->whereRaw('COALESCE(business_at, created_at) >= ?', [$from])->whereRaw('COALESCE(business_at, created_at) < ?', [$until]);
        }

        return view('purchases.index', ['purchases' => $query->paginate(20)->withQueryString()]);
    }

    public function create()
    {
        return view('purchases.create', ['categories' => Category::where('active', true)->orderBy('name')->get()]);
    }

    public function store(Request $r, PurchaseService $service, AuditService $audit)
    {
        $d = $r->validate(['request_uuid' => 'required|uuid', 'business_at' => 'nullable|date', 'note' => 'nullable|string|max:1000', 'items' => 'nullable|array', 'items.*.product_id' => 'nullable|exists:products,id', 'items.*.quantity' => 'nullable|integer|min:1|max:'.IntegerMath::MAX_QUANTITY, 'items.*.unit_cost_toman' => 'nullable|integer|min:1|max:'.IntegerMath::MAX_MONEY, 'new_product.name' => 'nullable|string|max:150', 'new_product.category_id' => 'nullable|exists:categories,id', 'new_product.sku' => ['nullable', 'string', 'max:80', 'unique:products,sku'], 'new_product.barcode' => ['nullable', 'string', 'max:80', 'unique:products,barcode'], 'new_product.brand' => 'nullable|string|max:100', 'new_product.model' => 'nullable|string|max:100', 'new_product.quantity' => 'nullable|integer|min:1|max:'.IntegerMath::MAX_QUANTITY, 'new_product.unit_cost_toman' => 'nullable|integer|min:1|max:'.IntegerMath::MAX_MONEY]);
        $d['items'] = array_values(array_filter($d['items'] ?? [], fn ($i) => ! empty($i['product_id'])));
        $p = DB::transaction(function () use ($d, $r, $service, $audit) {
            User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
            if ($existing = Purchase::where('request_uuid', $d['request_uuid'])->first()) {
                abort_unless($existing->created_by_user_id === $r->user()->id, 403);

                return $existing;
            }
            if (filled(data_get($d, 'new_product.name'))) {
                $n = $d['new_product'];
                if (empty($n['quantity']) || empty($n['unit_cost_toman'])) {
                    throw ValidationException::withMessages(['new_product.quantity' => 'تعداد و قیمت خرید کالای جدید لازم است.']);
                }
                $sku = $n['sku'] ?? ('AUTO-'.strtoupper(substr((string) Str::uuid(), 0, 12)));
                $product = Product::create(['category_id' => $n['category_id'] ?? null, 'name' => $n['name'], 'sku' => $sku, 'barcode' => $n['barcode'] ?? null, 'brand' => $n['brand'] ?? null, 'model' => $n['model'] ?? null, 'sale_price_toman' => 0, 'slug' => Str::slug($n['name'].'-'.$sku), 'status' => 'ACTIVE', 'active' => true, 'created_by_user_id' => $r->user()->id]);
                $d['items'][] = ['product_id' => $product->id, 'quantity' => $n['quantity'], 'unit_cost_toman' => $n['unit_cost_toman']];
                $audit->record('product_create_from_stock_in', 'PRODUCT', $product->id, [], [], 'کالای فعال هنگام ورود ایجاد شد', $r->user()->id);
            }
            if (! $d['items']) {
                throw ValidationException::withMessages(['items' => 'حداقل یک کالا وارد کنید.']);
            }
            foreach ($d['items'] as $item) {
                if (empty($item['quantity']) || empty($item['unit_cost_toman'])) {
                    throw ValidationException::withMessages(['items' => 'تعداد و قیمت خرید نهایی لازم است.']);
                }
            }

            return $service->create($d, $r->user()->id);
        });

        return redirect()->route('purchases.success', $p);
    }

    public function success(Purchase $purchase)
    {
        $this->authorize('view', $purchase);
        $purchase->load(['items.product', 'creator']);
        $movements = DB::table('stock_movements')->where('reference_type', 'PURCHASE')->where('reference_id', $purchase->id)->get()->keyBy('product_id');

        return view('purchases.success', compact('purchase', 'movements'));
    }
}
