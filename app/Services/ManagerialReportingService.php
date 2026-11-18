<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ManagerialReportingService
{
    public function inventoryValuation(): array
    {
        $known = (int) DB::table('products')->sum(DB::raw('(CAST(stock_quantity AS DECIMAL(20,0)) - CAST(unknown_cost_quantity AS DECIMAL(20,0))) * average_cost_toman'));
        $unknown = (int) DB::table('products')->sum('unknown_cost_quantity');

        return ['known_inventory_value' => $known, 'unknown_inventory_quantity' => $unknown];
    }

    public function summarize(Carbon $from, Carbon $to, ?int $employeeId = null): array
    {
        $saleScope = fn ($query, string $alias = 'sales') => $employeeId ? $query->where("{$alias}.seller_user_id", $employeeId) : $query;

        $grossSalesQuery = DB::table('sales')->whereBetween('created_at', [$from, $to]);
        $grossSales = (int) $saleScope($grossSalesQuery)->sum('total_toman');

        $voidSalesQuery = DB::table('sales')->whereNotNull('voided_at')->whereBetween('voided_at', [$from, $to]);
        $voidSales = (int) $saleScope($voidSalesQuery)->sum('total_toman');

        $returnsQuery = DB::table('sale_returns')->join('sales', 'sales.id', '=', 'sale_returns.sale_id')->whereBetween('sale_returns.created_at', [$from, $to]);
        $returns = (int) $saleScope($returnsQuery)->sum('sale_returns.total_refund_toman');

        $saleCogsQuery = DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')->whereBetween('sales.created_at', [$from, $to]);
        $unknownSaleQuantity = (int) $saleScope(clone $saleCogsQuery)->sum('sale_items.unknown_cost_quantity_snapshot');
        $saleCogs = (int) $saleScope($saleCogsQuery)->sum(DB::raw('(CAST(sale_items.quantity AS DECIMAL(20,0)) - CAST(sale_items.unknown_cost_quantity_snapshot AS DECIMAL(20,0))) * sale_items.cost_snapshot_toman'));

        $voidCogsQuery = DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')->whereNotNull('sales.voided_at')->whereBetween('sales.voided_at', [$from, $to]);
        $unknownVoidQuantity = (int) $saleScope(clone $voidCogsQuery)->sum('sale_items.unknown_cost_quantity_snapshot');
        $voidCogs = (int) $saleScope($voidCogsQuery)->sum(DB::raw('(CAST(sale_items.quantity AS DECIMAL(20,0)) - CAST(sale_items.unknown_cost_quantity_snapshot AS DECIMAL(20,0))) * sale_items.cost_snapshot_toman'));

        $returnCogsQuery = DB::table('sale_return_items')
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->join('sales', 'sales.id', '=', 'sale_returns.sale_id')
            ->join('sale_items', 'sale_items.id', '=', 'sale_return_items.sale_item_id')
            ->whereBetween('sale_returns.created_at', [$from, $to]);
        $returnCogs = (int) $saleScope($returnCogsQuery)->sum(DB::raw('(CAST(sale_return_items.quantity AS DECIMAL(20,0)) - CAST(sale_return_items.unknown_cost_quantity AS DECIMAL(20,0))) * sale_items.cost_snapshot_toman'));
        $unknownReturnQuantity = (int) $saleScope(clone $returnCogsQuery)->sum('sale_return_items.unknown_cost_quantity');

        $completeReplacementSale = static fn ($query) => $query->whereNotExists(function ($missing) {
            $missing->selectRaw('1')
                ->from('sale_items as missing_replacement_items')
                ->whereColumn('missing_replacement_items.sale_id', 'sales.id')
                ->whereNull('missing_replacement_items.replacement_cost_snapshot_toman');
        });

        $replacementSalesQuery = DB::table('sales')->whereBetween('sales.created_at', [$from, $to]);
        $replacementSales = (int) $completeReplacementSale($saleScope($replacementSalesQuery))->sum('sales.total_toman');
        $replacementSaleCountQuery = DB::table('sales')->whereBetween('sales.created_at', [$from, $to]);
        $replacementSaleCount = (int) $completeReplacementSale($saleScope($replacementSaleCountQuery))->count();
        $allSaleCountQuery = DB::table('sales')->whereBetween('sales.created_at', [$from, $to]);
        $allSaleCount = (int) $saleScope($allSaleCountQuery)->count();

        $replacementVoidSalesQuery = DB::table('sales')->whereNotNull('sales.voided_at')->whereBetween('sales.voided_at', [$from, $to]);
        $replacementVoidSalesQuery = $completeReplacementSale($saleScope($replacementVoidSalesQuery));
        $replacementVoidSales = (int) (clone $replacementVoidSalesQuery)->sum('sales.total_toman');
        $replacementVoidSaleCount = (int) $replacementVoidSalesQuery->count();

        $replacementReturnsQuery = DB::table('sale_returns')->join('sales', 'sales.id', '=', 'sale_returns.sale_id')->whereBetween('sale_returns.created_at', [$from, $to]);
        $replacementReturnsQuery = $completeReplacementSale($saleScope($replacementReturnsQuery));
        $replacementReturns = (int) (clone $replacementReturnsQuery)->sum('sale_returns.total_refund_toman');
        $replacementReturnCount = (int) $replacementReturnsQuery->count();

        $allVoidCountQuery = DB::table('sales')->whereNotNull('sales.voided_at')->whereBetween('sales.voided_at', [$from, $to]);
        $allVoidCount = (int) $saleScope($allVoidCountQuery)->count();
        $allReturnCountQuery = DB::table('sale_returns')->join('sales', 'sales.id', '=', 'sale_returns.sale_id')->whereBetween('sale_returns.created_at', [$from, $to]);
        $allReturnCount = (int) $saleScope($allReturnCountQuery)->count();

        $saleReplacementCostQuery = DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')->whereBetween('sales.created_at', [$from, $to]);
        $saleReplacementCost = (int) $completeReplacementSale($saleScope($saleReplacementCostQuery))->sum(DB::raw('sale_items.quantity * sale_items.replacement_cost_snapshot_toman'));

        $voidReplacementCostQuery = DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')->whereNotNull('sales.voided_at')->whereBetween('sales.voided_at', [$from, $to]);
        $voidReplacementCost = (int) $completeReplacementSale($saleScope($voidReplacementCostQuery))->sum(DB::raw('sale_items.quantity * sale_items.replacement_cost_snapshot_toman'));

        $returnReplacementCostQuery = DB::table('sale_return_items')
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->join('sales', 'sales.id', '=', 'sale_returns.sale_id')
            ->join('sale_items', 'sale_items.id', '=', 'sale_return_items.sale_item_id')
            ->whereBetween('sale_returns.created_at', [$from, $to]);
        $returnReplacementCost = (int) $completeReplacementSale($saleScope($returnReplacementCostQuery))->sum(DB::raw('sale_return_items.quantity * sale_items.replacement_cost_snapshot_toman'));

        $replacementNetSales = $replacementSales - $replacementVoidSales - $replacementReturns;
        $replacementCost = $saleReplacementCost - $voidReplacementCost - $returnReplacementCost;

        $netSales = $grossSales - $voidSales - $returns;
        $cogs = $saleCogs - $voidCogs - $returnCogs;
        $grossProfit = $netSales - $cogs;
        $unknownCostEventQuantity = $unknownSaleQuantity + $unknownVoidQuantity + $unknownReturnQuantity;
        $historicalCostComplete = $unknownCostEventQuantity === 0;

        $expenses = 0;
        $payroll = 0;
        $stockLoss = 0;
        $unknownStockLossQuantity = 0;
        if ($employeeId === null) {
            $expenseCreated = (int) DB::table('expenses')->whereBetween('created_at', [$from, $to])->sum('amount_toman');
            $expenseVoids = (int) DB::table('expenses')->whereNotNull('voided_at')->whereBetween('voided_at', [$from, $to])->sum('amount_toman');
            $expenses = $expenseCreated - $expenseVoids;
            $payroll = (int) DB::table('payrolls')->whereBetween('created_at', [$from, $to])->sum('net_salary_toman');
            $stockLoss = (int) DB::table('stock_movements')
                ->where('reference_type', 'STOCK_COUNT')
                ->where('quantity_delta', '<', 0)
                ->whereBetween('created_at', [$from, $to])
                ->sum(DB::raw('(-(quantity_delta - unknown_cost_quantity_delta)) * unit_cost_snapshot_toman'));
            $unknownStockLossQuantity = -(int) DB::table('stock_movements')->where('reference_type', 'STOCK_COUNT')->where('quantity_delta', '<', 0)->whereBetween('created_at', [$from, $to])->sum('unknown_cost_quantity_delta');
        }

        $discountQuery = DB::table('sales')->whereBetween('created_at', [$from, $to]);
        $discounts = (int) $saleScope($discountQuery)->sum('discount_toman');
        $saleCountQuery = DB::table('sales')->whereBetween('created_at', [$from, $to]);
        $saleCount = (int) $saleScope($saleCountQuery)->count();
        $documentationQuality = null;
        $exceptionCount = null;
        if ($employeeId !== null) {
            $documented = (int) DB::table('purchases')->where('created_by_user_id', $employeeId)->whereBetween('created_at', [$from, $to])->whereNotNull('invoice_image_path')->count()
                + (int) DB::table('expenses')->where('created_by_user_id', $employeeId)->whereBetween('created_at', [$from, $to])->whereNotNull('receipt_path')->count();
            $documentable = (int) DB::table('purchases')->where('created_by_user_id', $employeeId)->whereBetween('created_at', [$from, $to])->count()
                + (int) DB::table('expenses')->where('created_by_user_id', $employeeId)->whereBetween('created_at', [$from, $to])->count();
            $documentationQuality = $documentable > 0 ? round(($documented / $documentable) * 100, 2) : null;
            $saleIds = DB::table('sales')->where('seller_user_id', $employeeId)->whereBetween('created_at', [$from, $to])->pluck('id');
            $returnIds = DB::table('sale_returns')->join('sales', 'sales.id', '=', 'sale_returns.sale_id')->where('sales.seller_user_id', $employeeId)->whereBetween('sale_returns.created_at', [$from, $to])->pluck('sale_returns.id');
            $exceptionCount = (int) DB::table('review_exceptions')->where(function ($query) use ($saleIds, $returnIds) {
                $query->where(fn ($part) => $part->where('subject_type', 'SALE')->whereIn('subject_id', $saleIds))
                    ->orWhere(fn ($part) => $part->where('subject_type', 'SALE_RETURN')->whereIn('subject_id', $returnIds));
            })->count();
        }

        return [
            'gross_sales' => $grossSales,
            'sale_voids' => $voidSales,
            'returns' => $returns,
            'net_sales' => $netSales,
            'cogs' => $historicalCostComplete ? $cogs : null,
            'known_cogs_partial' => $cogs,
            'unknown_cost_event_quantity' => $unknownCostEventQuantity,
            'historical_cost_complete' => $historicalCostComplete,
            'gross_profit' => $historicalCostComplete ? $grossProfit : null,
            'replacement_cost' => $replacementCost,
            'replacement_margin' => $replacementNetSales - $replacementCost,
            'replacement_sale_count' => $replacementSaleCount,
            'replacement_event_count' => $replacementSaleCount + $replacementVoidSaleCount + $replacementReturnCount,
            'missing_replacement_sale_count' => ($allSaleCount + $allVoidCount + $allReturnCount) - ($replacementSaleCount + $replacementVoidSaleCount + $replacementReturnCount),
            'expenses' => $expenses,
            'payroll_accrued' => $payroll,
            'stock_discrepancy_loss' => $unknownStockLossQuantity > 0 ? null : $stockLoss,
            'known_stock_loss_partial' => $stockLoss,
            'unknown_stock_loss_quantity' => $unknownStockLossQuantity,
            'managerial_net_profit' => $historicalCostComplete && $unknownStockLossQuantity === 0 ? $grossProfit - $expenses - $payroll - $stockLoss : null,
            'discounts' => $discounts,
            'sale_count' => $saleCount,
            'return_rate' => $grossSales > 0 ? round(($returns / $grossSales) * 100, 2) : 0.0,
            'discount_rate' => $grossSales > 0 ? round(($discounts / $grossSales) * 100, 2) : 0.0,
            'documentation_quality' => $documentationQuality,
            'exception_count' => $exceptionCount,
            'closing_accuracy' => null,
        ];
    }
}
