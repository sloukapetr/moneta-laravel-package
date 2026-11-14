<?php

declare(strict_types=1);

namespace SloukaPetr\Moneta\Data;

use SloukaPetr\Moneta\Support\MinorAmount;

final readonly class StatementSummaryData
{
    public function __construct(
        public string $accountNumber,
        public string $bankCode,
        public string $currency,
        public int $beginBalanceMinor,
        public int $endBalanceMinor,
        public int $creditSumMinor,
        public int $debitSumMinor,
        public string $period,
        public int $sequenceNumber,
        public ?string $generatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $number = trim((string) ($data['accountNumber'] ?? ''));
        $prefix = trim((string) ($data['accountPrefix'] ?? ''));

        return new self(
            $prefix !== '' ? $prefix.'-'.$number : $number,
            (string) ($data['bankCode'] ?? ''),
            (string) ($data['currency'] ?? $data['accountCurrency'] ?? ''),
            MinorAmount::fromApi(self::amount($data['begBalance'] ?? 0)),
            MinorAmount::fromApi(self::amount($data['finBalance'] ?? 0)),
            MinorAmount::fromApi(self::amount($data['creditSum'] ?? 0)),
            MinorAmount::fromApi(self::amount($data['debitSum'] ?? 0)),
            (string) ($data['period'] ?? ''),
            (int) ($data['sequenceNumber'] ?? 0),
            is_string($data['generateDate'] ?? null) && $data['generateDate'] !== '' ? $data['generateDate'] : null,
        );
    }

    private static function amount(mixed $value): int|float|string
    {
        return is_int($value) || is_float($value) || is_string($value) ? $value : 0;
    }
}
