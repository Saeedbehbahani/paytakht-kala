<?php

namespace App\Services;

use App\Support\IntegerMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreMoneyService
{
    public function active(): bool
    {
        return DB::table('store_money_state')->where('id', 1)->whereNotNull('cutoff_at')->exists();
    }

    public function balance(): int
    {
        return (int) DB::table('store_money_state')->where('id', 1)->value('balance_toman');
    }

    public function cutoffAt(): ?string
    {
        return DB::table('store_money_state')->where('id', 1)->value('cutoff_at');
    }

    public function initialize(int $amount, string $evidence, int $userId, string $requestUuid): int
    {
        return DB::transaction(function () use ($amount, $evidence, $userId, $requestUuid) {
            $state = DB::table('store_money_state')->where('id', 1)->lockForUpdate()->first();
            if ($state->cutoff_at !== null || DB::table('store_money_movements')->exists()) {
                throw ValidationException::withMessages(['opening' => 'دفتر پول فروشگاه قبلاً فعال شده است.']);
            }
            if (trim($evidence) === '') {
                throw ValidationException::withMessages(['opening' => 'مدرک تطبیق مانده افتتاحیه الزامی است.']);
            }
            IntegerMath::nonNegative($amount, IntegerMath::MAX_MONEY, 'opening');
            $cutoff = now();
            DB::table('store_money_state')->where('id', 1)->update(['cutoff_at' => $cutoff, 'opening_evidence' => $evidence, 'updated_at' => $cutoff]);
            if ($amount > 0) {
                return $this->record('IN', $amount, 'CUTOVER', 1, 'OPENING', 0, $userId, $requestUuid);
            }

            return 0;
        }, 5);
    }

    public function record(string $direction, int $amount, string $eventType, int $eventId, string $effectKind, int $sequence, int $userId, ?string $requestUuid = null, ?int $reversalOfId = null, ?string $paymentMethod = null, ?int $locationAccountId = null): int
    {
        return DB::transaction(fn () => $this->recordLocked($direction, $amount, $eventType, $eventId, $effectKind, $sequence, $userId, $requestUuid, $reversalOfId, $paymentMethod, $locationAccountId), 5);
    }

    private function recordLocked(string $direction, int $amount, string $eventType, int $eventId, string $effectKind, int $sequence, int $userId, ?string $requestUuid, ?int $reversalOfId, ?string $paymentMethod, ?int $locationAccountId): int
    {
        if (! in_array($direction, ['IN', 'OUT'], true)) {
            throw ValidationException::withMessages(['direction' => 'جهت گردش پول فروشگاه نامعتبر است.']);
        }
        IntegerMath::positive($amount, IntegerMath::MAX_MONEY, 'amount');
        $state = DB::table('store_money_state')->where('id', 1)->lockForUpdate()->first();
        if (! $state || $state->cutoff_at === null) {
            throw ValidationException::withMessages(['store_money' => 'مانده افتتاحیه پول فروشگاه هنوز تطبیق و فعال نشده است.']);
        }
        if ($direction === 'OUT' && $paymentMethod === 'CASH' && ! DB::table('financial_accounts')->where('id', $locationAccountId)->where('type', 'CASH')->exists()) {
            throw ValidationException::withMessages(['payment_method' => 'صندوق نقدی معتبر برای پرداخت نقدی لازم است.']);
        }
        $existing = DB::table('store_money_movements')->where('event_type', $eventType)->where('event_id', $eventId)->where('effect_kind', $effectKind)->where('sequence', $sequence)->first();
        if ($existing) {
            if ($existing->direction !== $direction || (int) $existing->amount_toman !== $amount) {
                throw ValidationException::withMessages(['store_money' => 'اثر مالی تکراری با مبلغ متفاوت شناسایی شد.']);
            }

            return (int) $existing->id;
        }
        $next = $direction === 'IN'
            ? IntegerMath::add((int) $state->balance_toman, $amount, IntegerMath::MAX_MONEY, 'amount')
            : (int) $state->balance_toman - $amount;
        if ($next < 0) {
            throw ValidationException::withMessages(['store_money' => 'موجودی پول فروشگاه کافی نیست.']);
        }
        $id = DB::table('store_money_movements')->insertGetId([
            'direction' => $direction, 'amount_toman' => $amount, 'balance_after_toman' => $next,
            'event_type' => $eventType, 'event_id' => $eventId, 'effect_kind' => $effectKind,
            'sequence' => $sequence, 'request_uuid' => $requestUuid, 'reversal_of_id' => $reversalOfId,
            'payment_method' => $paymentMethod,
            'location_account_id' => $locationAccountId,
            'user_id' => $userId, 'created_at' => now(),
        ]);
        DB::table('store_money_state')->where('id', 1)->update(['balance_toman' => $next, 'updated_at' => now()]);

        return $id;
    }
}
