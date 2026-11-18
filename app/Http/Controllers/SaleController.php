<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\SaleService;
use App\Support\IntegerMath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $query = Sale::with(['items.product', 'seller'])->latest();
        if (! $request->user()->isAdmin()) {
            $query->where('seller_user_id', $request->user()->id);
        }
        $period = $request->query('period');
        if ($period) {
            $from = match ($period) {
                'week' => now()->startOfWeek(6),
                'month' => now()->startOfMonth(),
                'range' => now()->parse($request->query('from'))->startOfDay(),
                default => now()->startOfDay(),
            };
            $until = $period === 'range' ? now()->parse($request->query('to'))->addDay()->startOfDay() : now()->addDay()->startOfDay();
            $query->whereRaw('COALESCE(business_at, created_at) >= ?', [$from])->whereRaw('COALESCE(business_at, created_at) < ?', [$until]);
        }
        if ($search = trim((string) $request->query('q'))) {
            $query->whereHas('items.product', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('model', 'like', "%{$search}%"));
        }

        return view('sales.index', ['sales' => $query->paginate(20)->withQueryString()]);
    }

    public function create()
    {
        return view('sales.create');
    }

    public function store(Request $r, SaleService $service)
    {
        $d = $r->validate(['request_uuid' => 'required|uuid', 'business_at' => 'nullable|date', 'note' => 'nullable|string|max:1000', 'items' => 'required|array|min:1', 'items.*.product_id' => 'required|exists:products,id', 'items.*.quantity' => 'required|integer|min:1|max:'.IntegerMath::MAX_QUANTITY, 'items.*.purchase_price_toman' => 'required|integer|min:0|max:'.IntegerMath::MAX_MONEY, 'items.*.unit_price_toman' => 'required|integer|min:0|max:'.IntegerMath::MAX_MONEY], [
            'items.*.purchase_price_toman.required' => 'قیمت خرید این فروش را وارد کنید.',
            'items.*.unit_price_toman.required' => 'قیمت فروش را وارد کنید.',
        ]);

        return redirect()->route('sales.success', $service->create($d, $r->user()->id));
    }

    public function success(Request $request, Sale $sale)
    {
        $this->authorize('view', $sale);
        $sale->load(['items.product', 'seller']);
        $stockAfter = DB::table('stock_movements')->where('reference_type', 'SALE')->where('reference_id', $sale->id)->pluck('stock_after', 'product_id');

        return view('sales.success', compact('sale', 'stockAfter'));
    }
}
