<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Product;
use App\Services\InventoryService;
use App\Services\OperationsService;
use App\Support\IntegerMath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OperationsController extends Controller
{
    public function inventory(Request $r)
    {
        $q = Product::with('category')->where('active', true)->where('status', 'ACTIVE');
        if ($s = trim((string) $r->query('q'))) {
            $q->where(fn ($x) => $x->where('name', 'like', "%$s%")->orWhere('model', 'like', "%$s%")->orWhere('barcode', $s)->orWhere('sku', $s));
        }
        match ($r->query('filter')) {
            'low' => $q->whereNotNull('minimum_stock')->whereColumn('stock_quantity', '<=', 'minimum_stock')->where('stock_quantity', '>', 0),
            'out' => $q->where('stock_quantity', 0),
            default => null,
        };

        return view('inventory.index', ['products' => $q->orderBy('name')->paginate(30)->withQueryString()]);
    }

    public function adjust(Request $r, InventoryService $inventory)
    {
        $d = $r->validate(['request_uuid' => 'required|uuid', 'product_id' => 'required|exists:products,id', 'actual_quantity' => 'required|integer|min:0|max:'.IntegerMath::MAX_QUANTITY, 'reason' => 'nullable|string|max:1000']);
        $inventory->adjust($d['request_uuid'], $d['product_id'], $d['actual_quantity'], $d['reason'] ?? 'شمارش واقعی', $r->user()->id);
        $movement = DB::table('stock_movements')->where('request_uuid', $d['request_uuid'])->value('id');

        return $movement ? redirect()->route('inventory.adjust.success', $movement) : back()->with('success', 'موجودی از قبل درست بود و تغییری ثبت نشد.');
    }

    public function adjustmentSuccess(int $movement)
    {
        $record = DB::table('stock_movements')->join('products', 'products.id', '=', 'stock_movements.product_id')->leftJoin('users', 'users.id', '=', 'stock_movements.created_by_user_id')
            ->where('stock_movements.id', $movement)->where('stock_movements.movement_type', 'ADJUSTMENT')
            ->select('stock_movements.*', 'products.name as product_name', 'users.name as user_name')->firstOrFail();

        return view('inventory.adjust-success', compact('record'));
    }

    public function expenseForm(Request $request)
    {
        $expenses = DB::table('expenses')->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')->leftJoin('users', 'users.id', '=', 'expenses.created_by_user_id')->select('expenses.*', 'expense_categories.name as category_name', 'users.name as user_name')->latest('expenses.created_at');
        if ($request->filled('period')) {
            $from = match ($request->query('period')) {
                'week' => now()->startOfWeek(6), 'month' => now()->startOfMonth(), default => now()->startOfDay()
            };
            $expenses->whereRaw('COALESCE(expenses.business_at, expenses.created_at) >= ?', [$from]);
        }

        return view('operations.expense', ['categories' => DB::table('expense_categories')->where('active', true)->orderBy('name')->get(), 'expenses' => $expenses->limit(50)->get()]);
    }

    public function expense(Request $r, OperationsService $ops)
    {
        $d = $r->validate(['request_uuid' => 'required|uuid', 'business_at' => 'nullable|date', 'title' => 'required_without:expense_category_id|nullable|string|max:120', 'expense_category_id' => 'nullable|exists:expense_categories,id', 'amount_toman' => 'required|integer|min:1|max:'.IntegerMath::MAX_MONEY, 'note' => 'nullable|string|max:1000']);
        $expense = $ops->expense($d, $r->user()->id);

        return redirect()->route('expenses.success', $expense);
    }

    public function expenseSuccess(Expense $expense)
    {
        $this->authorize('view', $expense);
        $category = DB::table('expense_categories')->where('id', $expense->expense_category_id)->value('name');

        return view('operations.expense-success', ['expense' => $expense->load('creator'), 'category' => $category]);
    }

    public function expenseVoid(Request $r, int $id, OperationsService $ops)
    {
        $expense = Expense::query()->findOrFail($id);
        $this->authorize('void', $expense);
        $d = $r->validate(['reason' => 'required|string|max:1000']);
        $ops->voidExpense($id, $d['reason'], $r->user()->id);

        return back()->with('success', 'هزینه باطل شد.');
    }
}
