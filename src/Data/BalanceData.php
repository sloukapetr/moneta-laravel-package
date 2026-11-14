<?php

namespace SloukaPetr\Moneta\Data;

use SloukaPetr\Moneta\Enums\BalanceType;
use SloukaPetr\Moneta\Enums\CreditDebit;
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
        /** Absolute amount in minor units. A debit stays positive; use $signedAmountMinor for the sign. */
        public int $amountMinor = 0,
        public ?BalanceType $balanceTypeEnum = null,
        public int $signedAmountMinor = 0,
    ) {
    }

    public function isNegative(): bool
    {
        return $this->signedAmountMinor < 0;
    }

    public static function fromArray(array $data): self
    {
        $amountValue = data_get($data, 'amount.value', 0);
        $amountValue = is_int($amountValue) || is_float($amountValue) || is_string($amountValue) ? $amountValue : 0;
        $code = data_get($data, 'type.codeOrProprietary.code');
        $code = is_string($code) ? $code : '';
        $dateTime = data_get($data, 'date.dateTime');
        $indicator = (string) ($data['creditDebitIndicator'] ?? '');
        $amountMinor = MinorAmount::fromApi($amountValue);

        return new self(
            (string) data_get($data, 'amount.currency', ''),
            (float) $amountValue,
            $indicator,
            $code,
            is_string($dateTime) ? $dateTime : null,
            $code,
            $amountMinor,
            $code === '' ? null : BalanceType::tryFrom($code),
            $indicator === CreditDebit::Debit->value ? -abs($amountMinor) : $amountMinor,
        );
    }
}
