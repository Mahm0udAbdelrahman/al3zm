<?php

namespace App\Services\Travel\Support;

/**
 * Currency maths: how many decimals a currency has and its minor units.
 * OMR, KWD, BHD, JOD and TND have three (1 OMR = 1000 baisa).
 */
final class Currency
{
    private const DECIMALS = [
        'OMR' => 3, 'KWD' => 3, 'BHD' => 3, 'JOD' => 3, 'TND' => 3, 'IQD' => 3, 'LYD' => 3,
        'JPY' => 0, 'KRW' => 0, 'VND' => 0, 'CLP' => 0,
    ];

    public static function decimals(string $currency): int
    {
        return self::DECIMALS[strtoupper($currency)] ?? 2;
    }

    public static function round(float $amount, string $currency): float
    {
        return round($amount, self::decimals($currency));
    }

    public static function toMinor(float $amount, string $currency): int
    {
        return (int) round($amount * (10 ** self::decimals($currency)));
    }

    public static function fromMinor(int $minor, string $currency): float
    {
        return $minor / (10 ** self::decimals($currency));
    }

    public static function format(float $amount, string $currency): string
    {
        return number_format($amount, self::decimals($currency), '.', '');
    }
}
