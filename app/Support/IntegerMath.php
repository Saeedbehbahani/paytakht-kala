<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class IntegerMath
{
    public const MAX_MONEY = 9_000_000_000_000_000;

    public const MAX_QUANTITY = 1_000_000_000;

    public static function nonNegative(int $value, int $maximum, string $field): int
    {
        if ($value < 0 || $value > $maximum) {
            throw ValidationException::withMessages([$field => 'عدد خارج از محدوده امن است.']);
        }

        return $value;
    }

    public static function positive(int $value, int $maximum, string $field): int
    {
        if ($value < 1 || $value > $maximum) {
            throw ValidationException::withMessages([$field => 'عدد خارج از محدوده امن است.']);
        }

        return $value;
    }

    public static function add(int $left, int $right, int $maximum, string $field): int
    {
        if ($right < 0 || $left < 0 || $left > $maximum - $right) {
            throw ValidationException::withMessages([$field => 'جمع از محدوده امن بیشتر است.']);
        }

        return $left + $right;
    }

    public static function multiply(int $left, int $right, int $maximum, string $field): int
    {
        if ($left < 0 || $right < 0 || ($left !== 0 && $right > intdiv($maximum, $left))) {
            throw ValidationException::withMessages([$field => 'حاصل محاسبه از محدوده امن بیشتر است.']);
        }

        return $left * $right;
    }
}
