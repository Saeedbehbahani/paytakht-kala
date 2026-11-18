<?php

namespace App\Services;

use App\Models\FinancialAccount;
use App\Models\Setting;
use Illuminate\Validation\ValidationException;

class EmployeePaymentResolver
{
    public function resolve(string $bucket): array
    {
        $bucket = strtoupper($bucket);

        if ($bucket === 'CASH') {
            $account = $this->cashAccount();
            $method = 'CASH';
        } elseif ($bucket === 'POS') {
            $account = $this->posAccount();
            $method = 'POS';
        } elseif ($bucket === 'CARD_TRANSFER') {
            $account = $this->cardTransferAccount();
            $method = 'TRANSFER';
        } else {
            throw ValidationException::withMessages(['simple_payment_method' => 'روش پرداخت ساده معتبر نیست.']);
        }

        return ['method' => $method, 'financial_account_id' => $account->id];
    }

    public function cashAccount(): FinancialAccount
    {
        return $this->mappedAccount('employee_cash_account_id', ['CASH'], 'صندوق اصلی کارکنان');
    }

    public function cardTransferAccount(): FinancialAccount
    {
        return $this->mappedAccount('employee_card_transfer_account_id', ['BANK'], 'حساب کارت به کارت / انتقال کارکنان');
    }

    public function posAccount(): FinancialAccount
    {
        return $this->mappedAccount('employee_pos_account_id', ['POS'], 'کارتخوان پیش‌فرض کارکنان');
    }

    private function mappedAccount(string $settingKey, array $allowedTypes, string $label): FinancialAccount
    {
        $accountId = Setting::valueOf($settingKey);
        if (! is_numeric($accountId) || (int) $accountId < 1) {
            throw ValidationException::withMessages(['employee_account_mapping' => "{$label} در تنظیمات فروشگاه تعیین نشده است؛ مدیر باید mapping پرداخت کارکنان را تکمیل کند."]);
        }

        $account = FinancialAccount::query()->whereKey((int) $accountId)->where('active', true)->whereIn('type', $allowedTypes)->first();
        if (! $account) {
            throw ValidationException::withMessages(['employee_account_mapping' => "{$label} نامعتبر یا غیرفعال است؛ مدیر باید mapping پرداخت کارکنان را اصلاح کند."]);
        }

        return $account;
    }
}
