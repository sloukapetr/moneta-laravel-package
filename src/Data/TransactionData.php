<?php

namespace SloukaPetr\Moneta\Data;

use Spatie\LaravelData\Data;

class TransactionData extends Data
{
    public function __construct(
        public string $entryReference,
        public string $status,
        public string $creditDebitIndicator,
        public float $amount,
        public string $currency,
        public ?string $bookingDate = null,
        public ?string $enteredDate = null,
        public ?string $valueDate = null,
        public ?string $purpose = null,
        public ?string $description = null,
        public ?string $creditorReference = null,
        public ?string $variableSymbol = null,
        public ?string $constantSymbol = null,
        public ?string $specificSymbol = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $symbols = self::extractSymbols(
            (string) data_get($data, 'entryDetails.transactionDetails.remittanceInformation.structured.creditorReferenceInformation.reference', '')
        );

        return new self(
            (string) ($data['entryReference'] ?? ''),
            (string) ($data['status'] ?? ''),
            (string) ($data['creditDebitIndicator'] ?? ''),
            (float) data_get($data, 'amount.value', 0),
            (string) data_get($data, 'amount.currency', ''),
            data_get($data, 'bookingDate.date'),
            data_get($data, 'enteredDate.date'),
            data_get($data, 'valueDate.date'),
            data_get($data, 'entryDetails.transactionDetails.purpose.proprietary'),
            data_get($data, 'entryDetails.transactionDetails.references.transactionDescription'),
            data_get($data, 'entryDetails.transactionDetails.remittanceInformation.structured.creditorReferenceInformation.reference'),
            $symbols['variableSymbol'],
            $symbols['constantSymbol'],
            $symbols['specificSymbol'],
        );
    }

    protected static function extractSymbols(string $reference): array
    {
        if ($reference === '') {
            return [
                'variableSymbol' => null,
                'constantSymbol' => null,
                'specificSymbol' => null,
            ];
        }

        preg_match('/\bVS:([0-9]+)\b/i', $reference, $vs);
        preg_match('/\bKS:([0-9]+)\b/i', $reference, $ks);
        preg_match('/\bSS:([0-9]+)\b/i', $reference, $ss);

        return [
            'variableSymbol' => $vs[1] ?? null,
            'constantSymbol' => $ks[1] ?? null,
            'specificSymbol' => $ss[1] ?? null,
        ];
    }
}
