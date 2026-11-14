<?php

declare(strict_types=1);

namespace SloukaPetr\Moneta\Support;

use InvalidArgumentException;

final class MinorAmount
{
    public static function fromApi(int|float|string $value): int
    {
        $decimal = self::decimalString($value);

        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $decimal, $matches)) {
            throw new InvalidArgumentException('Moneta amount must be a number with at most 2 decimal places.');
        }

        $fraction = str_pad($matches[3] ?? '', 2, '0');
        $minor = (int) ($matches[2].$fraction);

        return $matches[1] === '-' ? -$minor : $minor;
    }

    private static function decimalString(int|float|string $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return trim($value);
        }

        $precise = rtrim(rtrim(number_format($value, 8, '.', ''), '0'), '.');
        $rounded = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        if ($precise !== $rounded) {
            throw new InvalidArgumentException('Moneta amount must be a number with at most 2 decimal places.');
        }

        return number_format($value, 2, '.', '');
    }
}
