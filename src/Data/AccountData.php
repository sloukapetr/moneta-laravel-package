<?php

namespace SloukaPetr\Moneta\Data;

use Spatie\LaravelData\Data;

class AccountData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $currency,
        public string $iban,
        public array $ownersNames = [],
        public string $bankCode = '',
        public string $bic = '',
        public string $countryCode = '',
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['id'] ?? ''),
            (string) ($data['nameI18N'] ?? ''),
            (string) ($data['currency'] ?? ''),
            (string) data_get($data, 'identification.iban', ''),
            (array) ($data['ownersNames'] ?? []),
            (string) data_get($data, 'servicer.bankCode', ''),
            (string) data_get($data, 'servicer.bic', ''),
            (string) data_get($data, 'servicer.countryCode', ''),
        );
    }
}
