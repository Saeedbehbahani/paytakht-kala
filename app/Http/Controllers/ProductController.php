<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\AuditService;
use App\Support\IntegerMath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('category')->where('active', true)->where('status', 'ACTIVE');
        if ($search = trim((string) $request->query('q'))) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('brand', 'like', "%{$search}%")
                ->orWhere('model', 'like', "%{$search}%")
                ->orWhere('sku', $search)->orWhere('barcode', $search));
        }

        return view('products.index', ['products' => $query->orderBy('name')->paginate(30)->withQueryString(), 'categories' => Category::where('active', true)->orderBy('name')->get()]);
    }

    public function show(Product $product)
    {
        $movements = DB::table('stock_movements')->leftJoin('users', 'users.id', '=', 'stock_movements.created_by_user_id')
            ->where('stock_movements.product_id', $product->id)
            ->select('stock_movements.*', 'users.name as user_name')
            ->orderByDesc('stock_movements.created_at')->orderByDesc('stock_movements.id')->paginate(30);
        $entries = DB::table('purchase_items')->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')->leftJoin('users', 'users.id', '=', 'purchases.created_by_user_id')
            ->where('purchase_items.product_id', $product->id)->where('purchases.status', 'COMPLETED')
            ->select('purchase_items.quantity', 'purchase_items.unit_cost_toman', 'purchases.business_at', 'purchases.created_at', 'users.name as user_name')->latest('purchases.id')->limit(20)->get();
        $sales = DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')->leftJoin('users', 'users.id', '=', 'sales.created_by_user_id')
            ->where('sale_items.product_id', $product->id)->where('sales.status', 'COMPLETED')
            ->select('sale_items.quantity', 'sale_items.unit_price_toman', 'sale_items.purchase_price_snapshot_toman', 'sales.business_at', 'sales.created_at', 'users.name as user_name')->latest('sales.id')->limit(20)->get();

        return view('products.show', ['product' => $product->load('category'), 'categories' => Category::where('active', true)->orderBy('name')->get(), 'movements' => $movements, 'entries' => $entries, 'sales' => $sales]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $this->validated($request);
        $data['sku'] ??= 'AUTO-'.strtoupper(substr((string) Str::uuid(), 0, 12));
        $data['slug'] = Str::slug($data['name'].'-'.$data['sku']);
        $data['status'] = 'ACTIVE';
        $data['active'] = true;
        $data['sale_price_toman'] ??= 0;
        $data['created_by_user_id'] = $request->user()->id;
        if ($request->hasFile('image')) {
            $data['image_path'] = $this->storeImage($request);
        }
        unset($data['image']);
        $product = Product::create($data);
        $audit->record('product_create', 'PRODUCT', $product->id, [], $product->toArray(), "کالای {$product->name} ایجاد شد", $request->user()->id);

        return redirect()->route('products.success', [$product, 'action' => 'created']);
    }

    public function update(Request $request, Product $product, AuditService $audit)
    {
        $data = $this->validated($request, $product);
        $data['sku'] ??= $product->sku;
        $data['slug'] = Str::slug($data['name'].'-'.$data['sku']);
        if ($request->hasFile('image')) {
            $data['image_path'] = $this->storeImage($request);
        }
        unset($data['image']);
        $before = $product->toArray();
        $product->update($data);
        $audit->record('product_update', 'PRODUCT', $product->id, $before, $product->fresh()->toArray(), "کالای {$product->name} ویرایش شد", $request->user()->id);

        return redirect()->route('products.success', [$product, 'action' => 'updated']);
    }

    public function success(Request $request, Product $product)
    {
        return view('products.success', ['product' => $product, 'action' => $request->query('action') === 'created' ? 'created' : 'updated']);
    }

    public function image(Product $product)
    {
        abort_unless($product->image_path && Storage::disk('local')->exists($product->image_path), 404);

        return Storage::disk('local')->response($product->image_path, basename($product->image_path), [
            'Content-Type' => Storage::disk('local')->mimeType($product->image_path),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function lookup(Request $request)
    {
        $search = trim((string) $request->query('q'));
        $query = Product::where('active', true)->where('status', 'ACTIVE');
        if ($request->query('context') === 'sale') {
            $query->where('stock_quantity', '>', 0);
        }
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('brand', 'like', "%{$search}%")->orWhere('model', 'like', "%{$search}%")->orWhere('sku', $search)->orWhere('barcode', $search))
                ->orderByRaw('CASE WHEN barcode = ? OR sku = ? THEN 0 WHEN name LIKE ? THEN 1 ELSE 2 END', [$search, $search, $search.'%']);
        } else {
            $query->orderByDesc('last_sale_at')->orderByDesc('updated_at');
        }
        $products = $query->limit(12)->get(['id', 'name', 'brand', 'model', 'sku', 'barcode', 'stock_quantity']);
        $products->each(function (Product $product) {
            $product->setAttribute('display_sku', preg_match('/^(BOOTSTRAP|AUTO)-/i', (string) $product->sku) ? null : $product->sku);
            $product->makeHidden('sku');
        });

        return response()->json(['data' => $products]);
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:150',
            'sku' => ['nullable', 'string', 'max:80', Rule::unique('products', 'sku')->ignore($product?->id)],
            'barcode' => ['nullable', 'string', 'max:80', Rule::unique('products', 'barcode')->ignore($product?->id)],
            'category_id' => 'nullable|exists:categories,id',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'sale_price_toman' => 'nullable|integer|min:0|max:'.IntegerMath::MAX_MONEY,
            'last_replacement_cost_toman' => 'nullable|integer|min:0|max:'.IntegerMath::MAX_MONEY,
            'minimum_stock' => 'nullable|integer|min:0|max:'.IntegerMath::MAX_QUANTITY,
            'image' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);
    }

    private function storeImage(Request $request): string
    {
        $file = $request->file('image');
        $extension = strtolower($file->getClientOriginalExtension());
        $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        $actual = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        if (($allowed[$extension] ?? null) !== $actual) {
            throw ValidationException::withMessages(['image' => 'تصویر معتبر نیست.']);
        }

        return $file->storeAs('product-images/'.now()->format('Y/m'), Str::uuid().'.'.$extension, 'local');
    }
}
