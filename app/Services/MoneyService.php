<?php

namespace App\Services;

use App\Models\FinancialAccount;
use App\Support\IntegerMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MoneyService
{
    public function record(int $accountId, string $direction, int $amount, string $type, string $referenceType, int $referenceId, int $userId, ?string $note = null, ?string $requestUuid = null): int
    {
        if (! in_array($direction, ['IN', 'OUT'], true)) {
            throw ValidationException::withMessages(['direction' => 'جهت گردش مالی معتبر نیست.']);
        }
        IntegerMath::positive($amount, IntegerMath::MAX_MONEY, 'amount');
        $account = FinancialAccount::query()->lockForUpdate()->findOrFail($accountId);
        $next = $direction === 'IN' ? IntegerMath::add($account->balance_toman, $amount, IntegerMath::MAX_MONEY, 'amount') : $account->balance_toman - $amount;
        if ($direction === 'OUT' && $next < 0) {
            throw ValidationException::withMessages(['account' => "موجودی حساب {$account->name} کافی نیست."]);
        }
        $account->forceFill(['balance_toman' => $next])->save();

        return DB::table('financial_movements')->insertGetId([
            'request_uuid' => $requestUuid, 'financial_account_id' => $account->id, 'direction' => $direction, 'amount_toman' => $amount,
            'balance_after_toman' => $next, 'type' => $type, 'reference_type' => $referenceType, 'reference_id' => $referenceId,
            'created_by_user_id' => $userId, 'note' => $note, 'created_at' => now(),
        ]);
    }

    public function transfer(string $uuid, int $fromId, int $toId, int $amount, int $userId, ?string $note = null): void
    {
        DB::transaction(function () use ($uuid, $fromId, $toId, $amount, $userId, $note) {
            if ($fromId === $toId) {
                throw ValidationException::withMessages(['to_account_id' => 'حساب مقصد باید متفاوت باشد.']);
            }
            $ids = [$fromId, $toId];
            sort($ids);
            FinancialAccount::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if (DB::table('financial_movements')->where('request_uuid', $uuid)->exists()) {
                return;
            }
            $ref = (int) sprintf('%u', crc32($uuid));
            $this->record($fromId, 'OUT', $amount, 'ACCOUNT_TRANSFER', 'TRANSFER', $ref, $userId, $note, $uuid);
            $this->record($toId, 'IN', $amount, 'ACCOUNT_TRANSFER', 'TRANSFER', $ref, $userId, $note);
            app(AuditService::class)->record('account_transfer', 'TRANSFER', $ref, [], compact('fromId', 'toId', 'amount'), 'انتقال وجه بین حساب‌ها', $userId);
        });
    }
}
