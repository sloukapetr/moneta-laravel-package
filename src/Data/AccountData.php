<?php

namespace SloukaPetr\Moneta\Data;

class AccountData
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
        public string $productName = '',
        public string $feePlan = '',
        public ?string $accountNumber = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $accountNumber = data_get($data, 'identification.other');

        return new self(
            (string) ($data['id'] ?? ''),
            (string) ($data['nameI18N'] ?? ''),
            (string) ($data['currency'] ?? ''),
            (string) data_get($data, 'identification.iban', ''),
            (array) ($data['ownersNames'] ?? []),
            (string) data_get($data, 'servicer.bankCode', ''),
            (string) data_get($data, 'servicer.bic', ''),
            (string) data_get($data, 'servicer.countryCode', ''),
            (string) ($data['productI18N'] ?? ''),
            (string) ($data['feePlanI18N'] ?? ''),
            is_string($accountNumber) && $accountNumber !== '' ? $accountNumber : null,
        );
    }
}
