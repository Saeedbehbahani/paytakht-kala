<?php

namespace App\Services;

use App\Models\FinancialAccount;
use Illuminate\Validation\ValidationException;

class PaymentAccountPolicy
{
    private const ACCOUNT_TYPES = [
        'CASH' => 'CASH',
        'POS' => 'POS',
        'BANK' => 'BANK',
        'TRANSFER' => 'BANK',
    ];

    public function assertCompatible(string $method, mixed $accountId, string $field = 'payments'): ?FinancialAccount
    {
        $method = strtoupper($method);

        if (in_array($method, ['CHEQUE', 'INSTALLMENT', 'CREDIT'], true)) {
            if (filled($accountId)) {
                throw ValidationException::withMessages([$field => 'برای پرداخت چکی یا اعتباری نباید حساب نقدی، کارت‌خوان یا بانکی انتخاب شود.']);
            }

            return null;
        }

        $requiredType = self::ACCOUNT_TYPES[$method] ?? null;
        if (! $requiredType) {
            throw ValidationException::withMessages([$field => 'روش پرداخت انتخاب‌شده پشتیبانی نمی‌شود.']);
        }
        if (! filled($accountId)) {
            throw ValidationException::withMessages([$field => 'انتخاب حساب سازگار با روش پرداخت الزامی است.']);
        }

        $account = FinancialAccount::query()->whereKey((int) $accountId)->where('active', true)->first();
        if (! $account) {
            throw ValidationException::withMessages([$field => 'حساب انتخاب‌شده فعال یا معتبر نیست.']);
        }
        if ($account->type !== $requiredType) {
            $labels = ['CASH' => 'صندوق نقدی', 'POS' => 'کارت‌خوان', 'BANK' => 'حساب بانکی'];
            throw ValidationException::withMessages([$field => "این روش پرداخت فقط با {$labels[$requiredType]} قابل ثبت است."]);
        }

        return $account;
    }
}
