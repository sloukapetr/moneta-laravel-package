<?php

namespace SloukaPetr\Moneta\Data;

use Spatie\LaravelData\Data;

class BalanceData extends Data
{
    public function __construct(
        public string $currency,
        public float $value,
        public string $creditDebitIndicator = '',
        public string $type = '',
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) data_get($data, 'amount.currency', ''),
            (float) data_get($data, 'amount.value', 0),
            (string) ($data['creditDebitIndicator'] ?? ''),
            (string) data_get($data, 'type.codeOrProprietary.code', ''),
        );
    }
}
