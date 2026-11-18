<?php

namespace App\Services;

use App\Models\User;
use App\Support\IntegerMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PersonalPayableService
{
    public function create(string $sourceType, int $sourceId, int $amount, int $personUserId, int $actorId): int
    {
        IntegerMath::positive($amount, IntegerMath::MAX_MONEY, 'amount');
        $person = User::query()->whereKey($personUserId)->firstOrFail();
        $existing = DB::table('personal_payables')->where('source_type', $sourceType)->where('source_id', $sourceId)->first();
        if ($existing) {
            if ((int) $existing->original_amount_toman !== $amount || (int) $existing->person_user_id !== $personUserId) {
                throw ValidationException::withMessages(['personal_payable' => 'بدهی شخصی تکراری با مشخصات متفاوت است.']);
            }

            return (int) $existing->id;
        }
        $id = DB::table('personal_payables')->insertGetId([
            'person_user_id' => $personUserId, 'person_name' => $person->name,
            'source_type' => $sourceType, 'source_id' => $sourceId,
            'original_amount_toman' => $amount, 'outstanding_toman' => $amount,
            'status' => 'OPEN', 'created_by_user_id' => $actorId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->movement($id, 'CREATE', $amount, $amount, $sourceType, $sourceId, $actorId);

        return $id;
    }

    public function settle(int $id, string $kind, int $amount, string $eventType, int $eventId, int $actorId, ?string $uuid = null): void
    {
        if (! in_array($kind, ['REIMBURSE', 'REVERSAL', 'ADJUSTMENT'], true)) {
            throw ValidationException::withMessages(['personal_payable' => 'نوع گردش بدهی شخصی نامعتبر است.']);
        }
        IntegerMath::positive($amount, IntegerMath::MAX_MONEY, 'amount');
        $payable = DB::table('personal_payables')->where('id', $id)->lockForUpdate()->first();
        if (! $payable) {
            throw ValidationException::withMessages(['personal_payable' => 'بدهی شخصی یافت نشد.']);
        }
        if (DB::table('personal_payable_movements')->where('personal_payable_id', $id)->where('kind', $kind)->where('event_type', $eventType)->where('event_id', $eventId)->exists()) {
            return;
        }
        $next = (int) $payable->outstanding_toman - $amount;
        if ($next < 0) {
            throw ValidationException::withMessages(['amount_toman' => 'مبلغ از مانده بدهی شخصی بیشتر است.']);
        }
        $this->movement($id, $kind, $amount, $next, $eventType, $eventId, $actorId, $uuid);
        DB::table('personal_payables')->where('id', $id)->update(['outstanding_toman' => $next, 'status' => $next === 0 ? 'PAID' : 'OPEN', 'updated_at' => now()]);
    }

    private function movement(int $id, string $kind, int $amount, int $after, string $eventType, int $eventId, int $actorId, ?string $uuid = null): void
    {
        DB::table('personal_payable_movements')->insert([
            'personal_payable_id' => $id, 'kind' => $kind, 'amount_toman' => $amount,
            'outstanding_after_toman' => $after, 'event_type' => $eventType,
            'event_id' => $eventId, 'request_uuid' => $uuid,
            'created_by_user_id' => $actorId, 'created_at' => now(),
        ]);
    }
}
