<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(private SimpleReportingService $reports) {}

    public function home(): array
    {
        $metrics = $this->reports->summarize(now()->startOfDay(), now()->startOfDay()->addDay());

        return [
            'salesToday' => $metrics['salesAmount'],
            'saleProfitToday' => $metrics['salesProfit'],
            'expenseToday' => $metrics['expenses'],
            'netToday' => $metrics['netProfit'],
            'saleCount' => $metrics['saleCount'],
            'stockInCount' => $metrics['stockInCount'],
            'low' => $metrics['lowStock']->count(),
            'unknownProfitSales' => $metrics['unknownProfitSales'],
            'activity' => DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
                ->whereIn('audit_logs.action', ['sale', 'stock_in', 'expense', 'stock_adjustment', 'product_create', 'product_update'])
                ->select('audit_logs.*', 'users.name as user_name')
                ->orderByDesc('audit_logs.created_at')->orderByDesc('audit_logs.id')->limit(12)->get(),
        ];
    }

    public function employee(int $userId): array
    {
        $sales = fn ($from, $until) => DB::table('sales')
            ->where('seller_user_id', $userId)
            ->where('status', 'COMPLETED')
            ->whereRaw('COALESCE(business_at, created_at) >= ?', [$from])
            ->whereRaw('COALESCE(business_at, created_at) < ?', [$until]);

        $today = now()->startOfDay();
        $month = now()->startOfMonth();
        $previousMonth = now()->subMonthNoOverflow()->startOfMonth();
        $previousMonthEnd = $previousMonth->copy()->addMonth();

        return [
            'todaySales' => (int) $sales($today, $today->copy()->addDay())->sum('total_toman'),
            'todayCount' => $sales($today, $today->copy()->addDay())->count(),
            'monthSales' => (int) $sales($month, $month->copy()->addMonth())->sum('total_toman'),
            'previousMonth' => (int) $sales($previousMonth, $previousMonthEnd)->sum('total_toman'),
            'recent' => DB::table('sales')->where('seller_user_id', $userId)->where('status', 'COMPLETED')->latest('id')->limit(5)->get(),
            'storeMoneyActive' => false,
            'storeMoneyBalance' => 0,
        ];
    }
}
