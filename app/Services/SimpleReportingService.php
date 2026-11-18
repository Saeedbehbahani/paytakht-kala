<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SimpleReportingService
{
    public function summarize(Carbon $from, Carbon $until): array
    {
        $during = fn ($query, string $table) => $query->whereRaw("COALESCE({$table}.business_at, {$table}.created_at) >= ?", [$from])
            ->whereRaw("COALESCE({$table}.business_at, {$table}.created_at) < ?", [$until]);
        $sales = $during(DB::table('sales')->where('sales.status', 'COMPLETED'), 'sales');
        $expenses = $during(DB::table('expenses')->where('expenses.status', 'COMPLETED'), 'expenses');
        $stockIns = $during(DB::table('purchases')->where('purchases.status', 'COMPLETED'), 'purchases');
        $knownItems = (clone $sales)->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->whereNotNull('sale_items.purchase_price_snapshot_toman');
        $quantityExpression = '(CAST(sale_items.quantity AS DECIMAL(20,0)) - CAST(sale_items.returned_quantity AS DECIMAL(20,0)))';
        $profitExpression = '(CAST(sale_items.unit_price_toman AS DECIMAL(20,0)) - CAST(sale_items.purchase_price_snapshot_toman AS DECIMAL(20,0))) * '.$quantityExpression;
        $salesProfit = (int) (clone $knownItems)->sum(DB::raw($profitExpression));
        $expenseTotal = (int) (clone $expenses)->sum('expenses.amount_toman');
        $topSelling = (clone $sales)->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')->join('products', 'products.id', '=', 'sale_items.product_id')
            ->select('products.name', DB::raw("SUM({$quantityExpression}) as quantity"))
            ->groupBy('products.id', 'products.name')->orderByDesc('quantity')->limit(5)->get();
        $mostProfitable = (clone $knownItems)->join('products', 'products.id', '=', 'sale_items.product_id')
            ->select('products.name', DB::raw("SUM({$profitExpression}) as profit"))
            ->groupBy('products.id', 'products.name')->orderByDesc('profit')->limit(5)->get();
        $stockInItems = (clone $stockIns)->join('purchase_items', 'purchase_items.purchase_id', '=', 'purchases.id');

        return [
            'salesAmount' => (int) (clone $sales)->sum('sales.total_toman'),
            'salesProfit' => $salesProfit,
            'expenses' => $expenseTotal,
            'netProfit' => $salesProfit - $expenseTotal,
            'saleCount' => (clone $sales)->count(),
            'stockInCount' => (clone $stockIns)->count(),
            'stockInQuantity' => (int) (clone $stockInItems)->sum('purchase_items.quantity'),
            'stockInValue' => (int) (clone $stockIns)->sum('purchases.total_toman'),
            'transactionCount' => (clone $sales)->count() + (clone $stockIns)->count() + (clone $expenses)->count(),
            'unknownProfitSales' => (clone $sales)->whereExists(function ($query) {
                $query->selectRaw('1')->from('sale_items')->whereColumn('sale_items.sale_id', 'sales.id')->whereNull('sale_items.purchase_price_snapshot_toman');
            })->count(),
            'topSelling' => $topSelling,
            'mostProfitable' => $mostProfitable,
            'lowStock' => DB::table('products')->where('active', true)->where('status', 'ACTIVE')->whereNotNull('minimum_stock')->whereColumn('stock_quantity', '<=', 'minimum_stock')->orderBy('stock_quantity')->limit(10)->get(['id', 'name', 'stock_quantity', 'minimum_stock']),
            'outOfStock' => DB::table('products')->where('active', true)->where('status', 'ACTIVE')->where('stock_quantity', 0)->count(),
            'salesRows' => (clone $sales)->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')->join('products', 'products.id', '=', 'sale_items.product_id')->leftJoin('users', 'users.id', '=', 'sales.created_by_user_id')->select('products.name', 'sale_items.quantity', 'sale_items.unit_price_toman', 'sale_items.purchase_price_snapshot_toman', 'sales.business_at', 'sales.created_at', 'users.name as user_name')->latest('sales.id')->limit(20)->get(),
            'stockInRows' => (clone $stockIns)->join('purchase_items', 'purchase_items.purchase_id', '=', 'purchases.id')->join('products', 'products.id', '=', 'purchase_items.product_id')->leftJoin('users', 'users.id', '=', 'purchases.created_by_user_id')->select('products.name', 'purchase_items.quantity', 'purchase_items.unit_cost_toman', 'purchases.business_at', 'purchases.created_at', 'users.name as user_name')->latest('purchases.id')->limit(20)->get(),
            'expenseRows' => (clone $expenses)->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')->leftJoin('users', 'users.id', '=', 'expenses.created_by_user_id')->select('expense_categories.name', 'expenses.amount_toman', 'expenses.business_at', 'expenses.created_at', 'users.name as user_name')->latest('expenses.id')->limit(20)->get(),
        ];
    }
}
