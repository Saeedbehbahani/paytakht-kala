<?php

namespace App\Console\Commands;

use App\Models\FinancialAccount;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckIntegrity extends Command
{
    protected $signature = 'paytakht:check-integrity';

    protected $description = 'Read-only reconciliation of inventory and financial projections against their ledgers';

    public function handle(): int
    {
        $errors = [];
        foreach (Product::query()->orderBy('id')->get() as $product) {
            $ledger = (int) DB::table('stock_movements')->where('product_id', $product->id)->sum('quantity_delta');
            if ($ledger !== $product->stock_quantity) {
                $errors[] = "STOCK product={$product->id} projected={$product->stock_quantity} ledger={$ledger}";
            }
            $unknownLedger = (int) DB::table('stock_movements')->where('product_id', $product->id)->sum('unknown_cost_quantity_delta');
            if ($unknownLedger !== (int) $product->unknown_cost_quantity || $unknownLedger < 0 || $unknownLedger > $ledger) {
                $errors[] = "UNKNOWN_COST product={$product->id} projected={$product->unknown_cost_quantity} ledger={$unknownLedger}";
            }
            $knownQuantity = $ledger - $unknownLedger;
            if (($knownQuantity > 0 && $product->average_cost_toman === null)
                || ($knownQuantity === 0 && $ledger > 0 && $product->average_cost_toman !== null)) {
                $errors[] = "COST_STATE product={$product->id} known_qty={$knownQuantity} average=".($product->average_cost_toman ?? 'unknown');
            }
        }
        foreach (FinancialAccount::query()->orderBy('id')->get() as $account) {
            $incoming = (int) DB::table('financial_movements')->where('financial_account_id', $account->id)->where('direction', 'IN')->sum('amount_toman');
            $outgoing = (int) DB::table('financial_movements')->where('financial_account_id', $account->id)->where('direction', 'OUT')->sum('amount_toman');
            $ledger = $incoming - $outgoing;
            if ($ledger !== $account->balance_toman) {
                $errors[] = "MONEY account={$account->id} projected={$account->balance_toman} ledger={$ledger}";
            }
        }
        if (Schema::hasTable('store_money_state')) {
            $storeState = DB::table('store_money_state')->where('id', 1)->first();
            if (! $storeState) {
                $errors[] = 'STORE_MONEY_STATE missing';
            } else {
                $storeIn = (int) DB::table('store_money_movements')->where('direction', 'IN')->sum('amount_toman');
                $storeOut = (int) DB::table('store_money_movements')->where('direction', 'OUT')->sum('amount_toman');
                if ($storeIn - $storeOut !== (int) $storeState->balance_toman) {
                    $errors[] = "STORE_MONEY projected={$storeState->balance_toman} ledger=".($storeIn - $storeOut);
                }
                if ($storeState->cutoff_at === null && (DB::table('store_money_movements')->exists() || (int) $storeState->balance_toman !== 0)) {
                    $errors[] = 'STORE_MONEY inactive state has movements';
                }
            }
        }
        if (Schema::hasTable('personal_payables')) {
            foreach (DB::table('personal_payables')->orderBy('id')->get() as $personal) {
                $created = (int) DB::table('personal_payable_movements')->where('personal_payable_id', $personal->id)->where('kind', 'CREATE')->sum('amount_toman');
                $settled = (int) DB::table('personal_payable_movements')->where('personal_payable_id', $personal->id)->whereIn('kind', ['REIMBURSE', 'REVERSAL', 'ADJUSTMENT'])->sum('amount_toman');
                if ($created - $settled !== (int) $personal->outstanding_toman || $created !== (int) $personal->original_amount_toman) {
                    $errors[] = "PERSONAL_PAYABLE id={$personal->id} projected={$personal->outstanding_toman} ledger=".($created - $settled);
                }
            }
        }
        foreach (DB::table('receivables')->orderBy('id')->get() as $receivable) {
            $saleRemaining = DB::table('sales')->where('id', $receivable->sale_id)->value('receivable_amount_toman');
            if ($saleRemaining === null || (int) $saleRemaining !== (int) $receivable->remaining_amount_toman) {
                $errors[] = "RECEIVABLE id={$receivable->id} ledger={$receivable->remaining_amount_toman} sale=".($saleRemaining ?? 'missing');
            }
        }
        foreach (DB::table('payables')->orderBy('id')->get() as $payable) {
            if ($payable->source_type === 'OPENING' || $payable->source_type === 'EXPENSE') {
                continue;
            }
            $purchaseRemaining = DB::table('purchases')->where('id', $payable->purchase_id)->value('remaining_amount_toman');
            if ($purchaseRemaining === null || (int) $purchaseRemaining !== (int) $payable->remaining_amount_toman) {
                $errors[] = "PAYABLE id={$payable->id} ledger={$payable->remaining_amount_toman} purchase=".($purchaseRemaining ?? 'missing');
            }
        }
        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }
            $this->error('INTEGRITY CHECK FAILED');

            return self::FAILURE;
        }
        $this->info('INTEGRITY CHECK PASSED');

        return self::SUCCESS;
    }
}
