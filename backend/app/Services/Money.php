<?php

namespace App\Services;

final class Money
{
    public static function cents(string|int|float $value): int
    {
        $parts = explode('.', (string) $value);

        return ((int) $parts[0]) * 100 + (int) str_pad(substr($parts[1] ?? '', 0, 2), 2, '0');
    }

    public static function decimal(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
