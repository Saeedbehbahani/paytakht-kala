<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\FinancialAccount;
use App\Models\ReviewException;
use App\Models\Setting;
use App\Services\AuditService;
use App\Services\ExceptionService;
use App\Services\ManagerialReportingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function more(Request $request)
    {
        return view('navigation.more', [
            'setupComplete' => $request->user()->isAdmin() && $this->setupIsComplete(),
        ]);
    }

    public function settings()
    {
        $employeeCashAccountId = Setting::valueOf('employee_cash_account_id');
        $employeePosAccountId = Setting::valueOf('employee_pos_account_id');
        $employeeCardTransferAccountId = Setting::valueOf('employee_card_transfer_account_id');
        $employeeCashAccounts = FinancialAccount::where('active', true)->where('type', 'CASH')->orderBy('name')->get();
        $employeePosAccounts = FinancialAccount::where('active', true)->where('type', 'POS')->orderBy('name')->get();
        $employeeCardTransferAccounts = FinancialAccount::where('active', true)->where('type', 'BANK')->orderBy('name')->get();

        return view('admin.settings', [
            'maxDiscount' => Setting::valueOf('employee_max_discount_percent', 3),
            'storeName' => Setting::valueOf('store_name', 'پایتخت کالا'),
            'storePhone' => Setting::valueOf('store_phone', ''),
            'invoiceInfo' => Setting::valueOf('invoice_info', ''),
            'defaultMinimumStock' => Setting::valueOf('default_minimum_stock', 1),
            'closingTolerance' => Setting::valueOf('closing_tolerance_toman', 0),
            'employeeCashAccountId' => $employeeCashAccountId,
            'employeePosAccountId' => $employeePosAccountId,
            'employeeCardTransferAccountId' => $employeeCardTransferAccountId,
            'employeeCashAccounts' => $employeeCashAccounts,
            'employeePosAccounts' => $employeePosAccounts,
            'employeeCardTransferAccounts' => $employeeCardTransferAccounts,
            'employeeMappingWarnings' => collect([
                $employeeCashAccounts->contains('id', (int) $employeeCashAccountId) ? null : 'صندوق پیش‌فرض کارکنان تعیین نشده یا نامعتبر است.',
                $employeePosAccounts->contains('id', (int) $employeePosAccountId) ? null : 'کارتخوان پیش‌فرض کارکنان تعیین نشده یا نامعتبر است.',
                $employeeCardTransferAccounts->contains('id', (int) $employeeCardTransferAccountId) ? null : 'حساب کارت به کارت / انتقال کارکنان تعیین نشده یا نامعتبر است.',
            ])->filter()->values(),
            'productCategories' => DB::table('categories')->leftJoin('products', 'products.category_id', '=', 'categories.id')->groupBy('categories.id', 'categories.name', 'categories.active')->select('categories.id', 'categories.name', 'categories.active', DB::raw('COUNT(products.id) as usage_count'))->orderByDesc('categories.active')->orderBy('categories.name')->get(),
            'expenseCategories' => DB::table('expense_categories')->leftJoin('expenses', 'expenses.expense_category_id', '=', 'expense_categories.id')->groupBy('expense_categories.id', 'expense_categories.name', 'expense_categories.active')->select('expense_categories.id', 'expense_categories.name', 'expense_categories.active', DB::raw('COUNT(expenses.id) as usage_count'))->orderByDesc('expense_categories.active')->orderBy('expense_categories.name')->get(),
        ]);
    }

    public function settingsUpdate(Request $request, AuditService $audit)
    {
        $data = $request->validate(['store_name' => 'required|string|max:120', 'store_phone' => 'nullable|string|max:30', 'invoice_info' => 'nullable|string|max:500', 'default_minimum_stock' => 'required|integer|min:0|max:999999', 'employee_max_discount_percent' => 'required|numeric|min:0|max:100', 'closing_tolerance_toman' => 'nullable|integer|min:0|max:999999999999', 'employee_cash_account_id' => ['required', Rule::exists('financial_accounts', 'id')->where(fn ($query) => $query->where('active', true)->where('type', 'CASH'))], 'employee_pos_account_id' => ['required', Rule::exists('financial_accounts', 'id')->where(fn ($query) => $query->where('active', true)->where('type', 'POS'))], 'employee_card_transfer_account_id' => ['required', Rule::exists('financial_accounts', 'id')->where(fn ($query) => $query->where('active', true)->where('type', 'BANK'))]]);
        foreach (['store_name' => $data['store_name'], 'store_phone' => $data['store_phone'] ?? '', 'invoice_info' => $data['invoice_info'] ?? '', 'default_minimum_stock' => $data['default_minimum_stock'], 'employee_max_discount_percent' => $data['employee_max_discount_percent'], 'closing_tolerance_toman' => $data['closing_tolerance_toman'] ?? Setting::valueOf('closing_tolerance_toman', 0), 'employee_cash_account_id' => $data['employee_cash_account_id'], 'employee_pos_account_id' => $data['employee_pos_account_id'], 'employee_card_transfer_account_id' => $data['employee_card_transfer_account_id']] as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        $audit->record('employee_sales_policy_update', 'SETTING', null, [], $data, 'تنظیمات تخفیف و قیمت کارکنان تغییر کرد');

        return back()->with('success', 'تنظیمات فروشگاه ذخیره شد.');
    }

    public function productCategoryStore(Request $request, AuditService $audit)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120', 'unique:categories,name']]);
        $category = Category::create(['name' => $data['name'], 'active' => true]);
        $audit->record('category_create', 'CATEGORY', $category->id, [], $category->only(['name', 'active']), 'دسته کالای جدید ایجاد شد');

        return back()->with('success', 'دسته کالا ایجاد شد.');
    }

    public function productCategoryUpdate(Request $request, Category $category, AuditService $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('categories', 'name')->ignore($category->id)],
            'active' => ['nullable', 'boolean'],
        ]);
        $before = $category->only(['name', 'active']);
        $category->update(['name' => $data['name'], 'active' => $request->boolean('active')]);
        $audit->record('category_update', 'CATEGORY', $category->id, $before, $category->only(['name', 'active']), 'دسته کالا به‌روزرسانی شد');

        return back()->with('success', 'دسته کالا به‌روزرسانی شد؛ سوابق کالاهای قبلی حفظ شده‌اند.');
    }

    public function expenseCategoryStore(Request $request, AuditService $audit)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120', 'unique:expense_categories,name']]);
        $id = DB::table('expense_categories')->insertGetId(['name' => $data['name'], 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $audit->record('expense_category_create', 'EXPENSE_CATEGORY', $id, [], ['name' => $data['name'], 'active' => true], 'دسته هزینه جدید ایجاد شد');

        return back()->with('success', 'دسته هزینه ایجاد شد.');
    }

    public function expenseCategoryUpdate(Request $request, int $category, AuditService $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('expense_categories', 'name')->ignore($category)],
            'active' => ['nullable', 'boolean'],
        ]);
        $before = DB::table('expense_categories')->where('id', $category)->first();
        abort_unless($before, 404);
        $after = ['name' => $data['name'], 'active' => $request->boolean('active')];
        DB::table('expense_categories')->where('id', $category)->update($after + ['updated_at' => now()]);
        $audit->record('expense_category_update', 'EXPENSE_CATEGORY', $category, ['name' => $before->name, 'active' => (bool) $before->active], $after, 'دسته هزینه به‌روزرسانی شد');

        return back()->with('success', 'دسته هزینه به‌روزرسانی شد؛ سوابق هزینه‌های قبلی حفظ شده‌اند.');
    }

    public function setup()
    {
        $steps = [
            ['label' => 'مشخصات فروشگاه', 'route' => 'admin.settings', 'complete' => DB::table('settings')->where('key', 'store_name')->exists()],
            ['label' => 'دسته‌بندی کالاها', 'route' => 'admin.settings', 'complete' => DB::table('categories')->where('active', true)->exists()],
            ['label' => 'دسته‌بندی هزینه‌ها', 'route' => 'admin.settings', 'complete' => DB::table('expense_categories')->where('active', true)->exists()],
            ['label' => 'کارکنان', 'route' => 'admin.employees.index', 'complete' => DB::table('users')->where('role', 'EMPLOYEE')->exists()],
            ['label' => 'کالاها', 'route' => 'products.index', 'complete' => DB::table('products')->exists()],
            ['label' => 'موجودی افتتاحیه', 'route' => 'inventory.index', 'complete' => $this->setupIsComplete()],
            ['label' => 'تعهدات افتتاحیه (در صورت وجود)', 'route' => 'admin.opening-obligations.index', 'complete' => DB::table('opening_obligation_batches')->where('status', 'FINALIZED')->exists(), 'optional' => true],
        ];

        return view('admin.setup', ['steps' => $steps, 'setupComplete' => $this->setupIsComplete()]);
    }

    private function setupIsComplete(): bool
    {
        return DB::table('opening_inventory_sessions')->where('status', 'FINALIZED')->exists();
    }

    public function audit()
    {
        return view('admin.audit', [
            'logs' => DB::table('audit_logs')
                ->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
                ->select('audit_logs.*', 'users.name as user_name')
                ->latest('audit_logs.created_at')
                ->paginate(40),
        ]);
    }

    public function reports(ManagerialReportingService $reporting)
    {
        $today = today();
        $month = now()->startOfMonth();
        $todayMetrics = $reporting->summarize($today, now());
        $monthMetrics = $reporting->summarize($month, now());
        $purchaseReturnsMonth = (int) DB::table('purchase_returns')->where('created_at', '>=', $month)->sum('total_refund_toman');

        $salesToday = $todayMetrics['net_sales'];
        $salesMonth = $monthMetrics['net_sales'];
        $purchasesMonth = (int) DB::table('purchases')->where('status', 'COMPLETED')->where('created_at', '>=', $month)->sum('total_toman') - $purchaseReturnsMonth;
        $expensesMonth = $monthMetrics['expenses'];
        $payrollMonth = $monthMetrics['payroll_accrued'];
        $payrollPaidMonth = (int) DB::table('payrolls')->where('created_at', '>=', $month)->sum('paid_amount_toman');
        $grossProfitMonth = $monthMetrics['gross_profit'];
        $returnsByItem = DB::table('sale_return_items')
            ->select('sale_item_id', DB::raw('SUM(refund_toman) as refund_toman'))
            ->groupBy('sale_item_id');
        $best = DB::table('sale_items')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoinSub($returnsByItem, 'item_returns', fn ($join) => $join->on('item_returns.sale_item_id', '=', 'sale_items.id'))
            ->where('sales.status', 'COMPLETED')
            ->where('sales.created_at', '>=', $month)
            ->groupBy('products.id', 'products.name')
            ->select('products.name', DB::raw('SUM(sale_items.quantity - sale_items.returned_quantity) as qty'), DB::raw('SUM(sale_items.line_total_toman - COALESCE(item_returns.refund_toman, 0)) as total'))
            ->orderByDesc('qty')
            ->limit(10)
            ->get();

        $least = DB::table('products')
            ->leftJoin('sale_items', 'sale_items.product_id', '=', 'products.id')
            ->where('products.active', true)
            ->groupBy('products.id', 'products.name', 'products.stock_quantity')
            ->select('products.name', 'products.stock_quantity', DB::raw('COALESCE(SUM(sale_items.quantity - sale_items.returned_quantity), 0) as qty'))
            ->orderBy('qty')
            ->limit(10)
            ->get();

        $highMargin = DB::table('products')
            ->where('active', true)
            ->where('average_cost_toman', '>', 0)
            ->where('unknown_cost_quantity', 0)
            ->select('name', 'sale_price_toman', 'average_cost_toman', DB::raw('(CAST(sale_price_toman AS DECIMAL(20, 0)) - CAST(average_cost_toman AS DECIMAL(20, 0))) as margin_toman'))
            ->orderByDesc('margin_toman')
            ->limit(10)
            ->get();

        $salesByEmployee = DB::table('users')->where('role', 'EMPLOYEE')->where('active', true)->get(['id', 'name'])->map(function ($employee) use ($reporting, $month) {
            $metrics = $reporting->summarize($month, now(), $employee->id);

            return (object) [
                'name' => $employee->name,
                'sale_count' => $metrics['sale_count'],
                'total' => $metrics['net_sales'],
                'gross_profit' => $metrics['gross_profit'],
                'replacement_margin' => $metrics['replacement_margin'],
                'replacement_event_count' => $metrics['replacement_event_count'],
                'missing_replacement_sale_count' => $metrics['missing_replacement_sale_count'],
                'return_rate' => $metrics['return_rate'],
                'discount_rate' => $metrics['discount_rate'],
                'documentation_quality' => $metrics['documentation_quality'],
                'exception_count' => $metrics['exception_count'],
                'closing_accuracy' => $metrics['closing_accuracy'],
            ];
        })->sortByDesc('total')->values();

        return view('admin.reports', [
            'salesToday' => $salesToday,
            'salesMonth' => $salesMonth,
            'purchasesMonth' => $purchasesMonth,
            'expensesMonth' => $expensesMonth,
            'payrollMonth' => $payrollMonth,
            'payrollPaidMonth' => $payrollPaidMonth,
            'grossProfitMonth' => $grossProfitMonth,
            'netProfitMonth' => $monthMetrics['managerial_net_profit'],
            'managerialMetrics' => $monthMetrics,
            'inventoryValue' => $reporting->inventoryValuation()['known_inventory_value'],
            'unknownInventoryQuantity' => $reporting->inventoryValuation()['unknown_inventory_quantity'],
            'best' => $best,
            'least' => $least,
            'highMargin' => $highMargin,
            'lowStock' => DB::table('products')->where('active', true)->whereColumn('stock_quantity', '<=', 'minimum_stock')->orderBy('stock_quantity')->limit(20)->get(),
            'stale' => DB::table('products')->where('stock_quantity', '>', 0)->where(fn ($q) => $q->whereNull('last_sale_at')->orWhere('last_sale_at', '<', now()->subDays(90)))->orderBy('last_sale_at')->limit(20)->get(),
            'salesByEmployee' => $salesByEmployee,
            'receivables' => (int) DB::table('receivables')->whereIn('status', ['OPEN', 'PARTIAL'])->sum('remaining_amount_toman'),
            'payables' => (int) DB::table('payables')->whereIn('status', ['OPEN', 'PARTIAL'])->sum('remaining_amount_toman'),
        ]);
    }

    public function exceptions(Request $request)
    {
        $query = ReviewException::query()->latest('detected_at');
        if (in_array($request->query('status'), ['OPEN', 'REVIEWED'], true)) {
            $query->where('status', $request->query('status'));
        }

        return view('admin.exceptions', ['exceptions' => $query->paginate(40)->withQueryString(), 'openCount' => ReviewException::where('status', 'OPEN')->count()]);
    }

    public function reviewException(Request $request, ReviewException $exception, ExceptionService $service)
    {
        $data = $request->validate(['review_note' => 'nullable|string|max:2000']);
        $service->review($exception, $request->user()->id, $data['review_note'] ?? null);

        return back()->with('success', 'مورد برای بررسی به‌عنوان بررسی‌شده ثبت شد.');
    }
}
