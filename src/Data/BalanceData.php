<?php

namespace SloukaPetr\Moneta\Data;

use SloukaPetr\Moneta\Enums\BalanceType;
use SloukaPetr\Moneta\Support\MinorAmount;

class BalanceData
{
    public function __construct(
        public string $currency,
        /** @deprecated Use $amountMinor. */
        public float $value,
        public string $creditDebitIndicator = '',
        public string $type = '',
        public ?string $dateTime = null,
        public ?string $balanceType = null,
        public int $amountMinor = 0,
        public ?BalanceType $balanceTypeEnum = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $amountValue = data_get($data, 'amount.value', 0);
        $amountValue = is_int($amountValue) || is_float($amountValue) || is_string($amountValue) ? $amountValue : 0;
        $code = data_get($data, 'type.codeOrProprietary.code');
        $code = is_string($code) ? $code : '';
        $dateTime = data_get($data, 'date.dateTime');

        return new self(
            (string) data_get($data, 'amount.currency', ''),
            (float) $amountValue,
            (string) ($data['creditDebitIndicator'] ?? ''),
            $code,
            is_string($dateTime) ? $dateTime : null,
            $code,
            MinorAmount::fromApi($amountValue),
            $code === '' ? null : BalanceType::tryFrom($code),
        );
    }
}
