<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class CleanupTestTransactions extends Command
{
    protected $signature = 'paytakht:cleanup-test-transactions
        {--discover : Show a read-only sales summary for every user who has sales}
        {--confirm : Perform the selected deletion after reviewing a dry run}
        {--users=* : Explicit operator user ID; repeat for every approved test operator}
        {--sales=* : Explicit sale ID to delete; repeat for every approved sale}
        {--purchases=* : Explicit related purchase ID to delete; repeat only for approved test stock-ins}
        {--expenses=* : Explicit related expense ID to delete; repeat only for approved test expenses}';

    protected $description = 'Dry-run, then explicitly remove approved Mohsen/Ramin test transactions without touching catalog or users';

    public function handle(): int
    {
        if ($this->option('discover')) {
            if ($this->option('confirm') || $this->ids('users') !== [] || $this->ids('sales') !== [] || $this->ids('purchases') !== [] || $this->ids('expenses') !== []) {
                $this->error('--discover is read-only and cannot be combined with confirmation or transaction IDs.');

                return self::FAILURE;
            }

            return $this->discoverSalesUsers();
        }

        $selectedUserIds = $this->ids('users');
        $users = $selectedUserIds === []
            ? DB::table('users')->whereIn('name', ['Mohsen', 'Ramin'])->orderBy('id')->get(['id', 'name'])
            : DB::table('users')->whereIn('id', $selectedUserIds)->orderBy('id')->get(['id', 'name']);
        if ($selectedUserIds !== [] && $users->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all() !== $selectedUserIds) {
            $this->error('One or more explicit --users IDs do not exist.');

            return self::FAILURE;
        }
        $userIds = $users->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->table(['User ID', 'User name'], $users->map(fn ($user) => [$user->id, $user->name])->all());
        $candidates = $this->candidates($userIds);
        $this->renderCandidates($candidates, $users->keyBy('id')->all());

        if (! $this->option('confirm')) {
            $this->warn('DRY RUN ONLY: no records were changed. Re-run with --confirm and only the IDs you explicitly approve.');

            return self::SUCCESS;
        }

        $selected = [
            'sales' => $this->ids('sales'),
            'purchases' => $this->ids('purchases'),
            'expenses' => $this->ids('expenses'),
        ];
        if ($selected['sales'] === []) {
            $this->error('Confirmation requires at least one explicit --sales ID.');

            return self::FAILURE;
        }
        $this->assertSelectedCandidates($selected, $candidates);

        $result = DB::transaction(function () use ($selected, $userIds) {
            $result = $this->deleteSelected($selected, $userIds);
            $integrity = Artisan::call('paytakht:check-integrity');
            if ($integrity !== self::SUCCESS) {
                throw new RuntimeException('CheckIntegrity failed; cleanup was rolled back. '.trim(Artisan::output()));
            }

            return $result;
        }, 5);

        $integrity = Artisan::call('paytakht:check-integrity');
        $this->output->write(Artisan::output());
        if ($integrity !== self::SUCCESS) {
            throw new RuntimeException('CheckIntegrity failed after cleanup.');
        }

        $this->info(json_encode([
            'deleted_records' => $result,
            'remaining_sales_count' => DB::table('sales')->count(),
            'product_count_unchanged' => DB::table('products')->count(),
            'check_integrity' => 'PASS',
        ], JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    private function discoverSalesUsers(): int
    {
        $rows = DB::table('users')
            ->join('sales', 'sales.created_by_user_id', '=', 'users.id')
            ->groupBy('users.id', 'users.name')
            ->orderBy('users.id')
            ->get([
                'users.id',
                'users.name',
                DB::raw('COUNT(sales.id) AS sales_count'),
                DB::raw('MAX(COALESCE(sales.business_at, sales.created_at)) AS latest_sale_at'),
                DB::raw('SUM(sales.total_toman) AS total_sales_toman'),
            ]);

        $this->table(
            ['User ID', 'User name', 'Sales count', 'Latest sale date', 'Total sales toman'],
            $rows->map(fn ($row) => [$row->id, $row->name, $row->sales_count, $row->latest_sale_at, $row->total_sales_toman])->all()
        );
        $this->info('DISCOVERY ONLY: no records were changed.');

        return self::SUCCESS;
    }

    private function candidates(array $userIds): array
    {
        if ($userIds === []) {
            return ['sales' => collect(), 'purchases' => collect(), 'expenses' => collect()];
        }

        return [
            'sales' => DB::table('sales')->whereIn('created_by_user_id', $userIds)->orderBy('id')->get(),
            'purchases' => DB::table('purchases')->whereIn('created_by_user_id', $userIds)->orderBy('id')->get(),
            'expenses' => DB::table('expenses')->whereIn('created_by_user_id', $userIds)->orderBy('id')->get(),
        ];
    }

    private function renderCandidates(array $candidates, array $users): void
    {
        $saleRows = $candidates['sales']->map(function ($sale) use ($users) {
            $items = DB::table('sale_items')->join('products', 'products.id', '=', 'sale_items.product_id')
                ->where('sale_items.sale_id', $sale->id)
                ->orderBy('sale_items.id')
                ->get(['products.name', 'sale_items.quantity'])
                ->map(fn ($item) => "{$item->name} × {$item->quantity}")->implode('; ');
            $movements = DB::table('stock_movements')->where('reference_type', 'SALE')->where('reference_id', $sale->id)->pluck('id')->implode(', ');

            return [$sale->id, $users[$sale->created_by_user_id]->name ?? $sale->created_by_user_id, $sale->business_at ?? $sale->created_at, $sale->total_toman, $items, $movements ?: '—'];
        });
        $this->table(['Sale ID', 'User', 'Date', 'Total toman', 'Items', 'Stock movement IDs'], $saleRows->all());

        foreach (['purchases' => ['Purchase ID', 'Total toman'], 'expenses' => ['Expense ID', 'Amount toman']] as $type => [$idLabel, $amountLabel]) {
            $rows = $candidates[$type]->map(fn ($record) => [
                $record->id,
                $users[$record->created_by_user_id]->name ?? $record->created_by_user_id,
                $record->business_at ?? $record->created_at,
                $record->total_toman ?? $record->amount_toman,
            ]);
            $this->table([$idLabel, 'User', 'Date', $amountLabel], $rows->all());
        }
    }

    private function ids(string $option): array
    {
        return collect($this->option($option))->flatMap(fn ($value) => explode(',', (string) $value))
            ->map(fn ($value) => trim($value))->filter(fn ($value) => ctype_digit($value) && (int) $value > 0)
            ->map(fn ($value) => (int) $value)->unique()->sort()->values()->all();
    }

    private function assertSelectedCandidates(array $selected, array $candidates): void
    {
        foreach ($selected as $type => $ids) {
            $allowed = $candidates[$type]->pluck('id')->map(fn ($id) => (int) $id)->all();
            $invalid = array_values(array_diff($ids, $allowed));
            if ($invalid !== []) {
                throw new RuntimeException("Selected {$type} are not Mohsen/Ramin candidates: ".implode(',', $invalid));
            }
        }
    }

    private function deleteSelected(array $selected, array $userIds): array
    {
        $productCount = DB::table('products')->count();
        $sales = DB::table('sales')->whereIn('id', $selected['sales'])->whereIn('created_by_user_id', $userIds)->lockForUpdate()->get();
        if ($sales->count() !== count($selected['sales'])) {
            throw new RuntimeException('A selected sale changed before cleanup.');
        }
        $purchaseIds = $selected['purchases'];
        $expenseIds = $selected['expenses'];
        $movementProductIds = DB::table('stock_movements')
            ->where(fn ($query) => $query->where(fn ($q) => $q->where('reference_type', 'SALE')->whereIn('reference_id', $selected['sales']))
                ->orWhere(fn ($q) => $q->where('reference_type', 'PURCHASE')->whereIn('reference_id', $purchaseIds)))
            ->pluck('product_id')->unique()->sort()->values()->all();
        $products = DB::table('products')->whereIn('id', $movementProductIds)->lockForUpdate()->get()->keyBy('id');
        $financialAccountIds = Schema::hasTable('financial_movements') ? DB::table('financial_movements')
            ->where(fn ($query) => $query->where(fn ($q) => $q->where('reference_type', 'SALE')->whereIn('reference_id', $selected['sales']))
                ->orWhere(fn ($q) => $q->where('reference_type', 'PURCHASE')->whereIn('reference_id', $purchaseIds))
                ->orWhere(fn ($q) => $q->where('reference_type', 'EXPENSE')->whereIn('reference_id', $expenseIds)))
            ->pluck('financial_account_id')->unique()->sort()->values()->all() : [];
        $financialAccounts = $financialAccountIds === [] ? collect() : DB::table('financial_accounts')->whereIn('id', $financialAccountIds)->lockForUpdate()->get();

        $counts = array_fill_keys(['sales', 'sale_items', 'sale_payments', 'sale_movements', 'purchases', 'purchase_items', 'purchase_movements', 'expenses', 'audit_logs', 'financial_movements'], 0);
        $counts['sale_payments'] += $this->deleteWhere('payment_allocations', fn ($q) => $q->where('payable_type', 'SALE')->whereIn('payable_id', $selected['sales']));
        $counts['sale_payments'] += $this->deleteWhere('receivables', fn ($q) => $q->whereIn('sale_id', $selected['sales']), ['installments', 'cheques']);
        $counts['sale_items'] += $this->deleteWhere('sale_return_items', fn ($q) => $q->whereIn('sale_item_id', DB::table('sale_items')->whereIn('sale_id', $selected['sales'])->select('id')));
        $this->deleteWhere('sale_returns', fn ($q) => $q->whereIn('sale_id', $selected['sales']));
        $this->deleteWhere('cheques', fn ($q) => $q->whereIn('sale_id', $selected['sales']));
        $counts['financial_movements'] += $this->deleteReferenceMovements('SALE', $selected['sales']);
        $counts['sale_movements'] += $this->deleteStockMovements('SALE', $selected['sales']);
        $counts['sale_items'] += $this->deleteWhere('sale_items', fn ($q) => $q->whereIn('sale_id', $selected['sales']));
        $counts['sales'] += $this->deleteWhere('sales', fn ($q) => $q->whereIn('id', $selected['sales']));

        if ($purchaseIds !== []) {
            $counts['sale_payments'] += $this->deleteWhere('payment_allocations', fn ($q) => $q->where('payable_type', 'PURCHASE')->whereIn('payable_id', $purchaseIds));
            $this->deleteWhere('payables', fn ($q) => $q->whereIn('purchase_id', $purchaseIds), ['cheques']);
            $this->deleteWhere('purchase_return_items', fn ($q) => $q->whereIn('purchase_item_id', DB::table('purchase_items')->whereIn('purchase_id', $purchaseIds)->select('id')));
            $this->deleteWhere('purchase_returns', fn ($q) => $q->whereIn('purchase_id', $purchaseIds));
            $this->deleteWhere('cheques', fn ($q) => $q->whereIn('purchase_id', $purchaseIds));
            $counts['financial_movements'] += $this->deleteReferenceMovements('PURCHASE', $purchaseIds);
            $counts['purchase_movements'] += $this->deleteStockMovements('PURCHASE', $purchaseIds);
            $counts['purchase_items'] += $this->deleteWhere('purchase_items', fn ($q) => $q->whereIn('purchase_id', $purchaseIds));
            $counts['purchases'] += $this->deleteWhere('purchases', fn ($q) => $q->whereIn('id', $purchaseIds));
        }

        if ($expenseIds !== []) {
            $counts['financial_movements'] += $this->deleteReferenceMovements('EXPENSE', $expenseIds);
            $counts['expenses'] += $this->deleteWhere('expenses', fn ($q) => $q->whereIn('id', $expenseIds));
        }

        $entityIds = ['SALE' => $selected['sales'], 'PURCHASE' => $purchaseIds, 'EXPENSE' => $expenseIds];
        foreach ($entityIds as $entityType => $ids) {
            if ($ids !== []) {
                $counts['audit_logs'] += $this->deleteWhere('audit_logs', fn ($q) => $q->where('entity_type', $entityType)->whereIn('entity_id', $ids));
                $this->deleteWhere('private_uploads', fn ($q) => $q->where('reference_type', $entityType)->whereIn('reference_id', $ids));
            }
        }

        foreach ($products as $product) {
            $stock = (int) DB::table('stock_movements')->where('product_id', $product->id)->sum('quantity_delta');
            $unknown = Schema::hasColumn('stock_movements', 'unknown_cost_quantity_delta')
                ? (int) DB::table('stock_movements')->where('product_id', $product->id)->sum('unknown_cost_quantity_delta') : 0;
            if ($stock < 0 || $unknown < 0 || $unknown > $stock) {
                throw new RuntimeException("Cleanup would leave product {$product->id} with invalid inventory.");
            }
            $latest = DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')->where('sale_items.product_id', $product->id)
                ->whereNull('sales.voided_at')->orderByDesc('sales.business_at')->orderByDesc('sales.id')
                ->first(['sales.business_at', 'sale_items.unit_price_toman']);
            $update = ['stock_quantity' => $stock];
            if (Schema::hasColumn('products', 'unknown_cost_quantity')) {
                $update['unknown_cost_quantity'] = $unknown;
            }
            if (Schema::hasColumn('products', 'last_sale_at')) {
                $update['last_sale_at'] = $latest?->business_at;
            }
            if (Schema::hasColumn('products', 'last_sale_price_toman')) {
                $update['last_sale_price_toman'] = $latest?->unit_price_toman;
            }
            if ($stock > 0 && $unknown === $stock) {
                $update['average_cost_toman'] = null;
            }
            DB::table('products')->where('id', $product->id)->update($update);
        }
        foreach ($financialAccounts as $account) {
            $incoming = (int) DB::table('financial_movements')->where('financial_account_id', $account->id)->where('direction', 'IN')->sum('amount_toman');
            $outgoing = (int) DB::table('financial_movements')->where('financial_account_id', $account->id)->where('direction', 'OUT')->sum('amount_toman');
            DB::table('financial_accounts')->where('id', $account->id)->update(['balance_toman' => $incoming - $outgoing]);
        }
        if ($productCount !== DB::table('products')->count()) {
            throw new RuntimeException('Product count changed.');
        }

        return $counts;
    }

    private function deleteWhere(string $table, callable $scope, array $children = []): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }
        $query = DB::table($table);
        $scope($query);
        $ids = $query->pluck('id')->all();
        foreach ($children as $child) {
            if ($ids !== [] && Schema::hasTable($child)) {
                $column = $table === 'receivables' ? 'receivable_id' : 'payable_id';
                DB::table($child)->whereIn($column, $ids)->delete();
            }
        }

        return $ids === [] ? 0 : DB::table($table)->whereIn('id', $ids)->delete();
    }

    private function deleteStockMovements(string $type, array $ids): int
    {
        return $ids === [] ? 0 : DB::table('stock_movements')->where('reference_type', $type)->whereIn('reference_id', $ids)->delete();
    }

    private function deleteReferenceMovements(string $type, array $ids): int
    {
        return $ids === [] || ! Schema::hasTable('financial_movements') ? 0
            : DB::table('financial_movements')->where('reference_type', $type)->whereIn('reference_id', $ids)->delete();
    }
}
