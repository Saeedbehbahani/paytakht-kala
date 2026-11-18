<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class ExternalDirectSettlementService
{
    public function settle(string $uuid, int $saleId, int $payableId, int $amount, string $evidence, int $userId): int
    {
        // A direct payment nets two obligations. Partial returns and voids need an
        // explicit commercial rule for who refunds whom before this is reversible.
        throw ValidationException::withMessages([
            'external_direct' => 'تسویه مستقیم تا تعیین قاعدهٔ مرجوعی و ابطال دو طرف، غیرفعال است.',
        ]);
    }
}
