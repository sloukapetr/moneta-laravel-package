<?php

namespace SloukaPetr\Moneta\Data;

use SloukaPetr\Moneta\Enums\CreditDebit;
use SloukaPetr\Moneta\Enums\TransactionStatus;
use SloukaPetr\Moneta\Support\MinorAmount;
use SloukaPetr\Moneta\Support\PaymentSymbols;

class TransactionData
{
    public function __construct(
        public string $entryReference,
        public string $status,
        public string $creditDebitIndicator,
        /** @deprecated Use $amountMinor. */
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
        public int $amountMinor = 0,
        public ?TransactionStatus $statusEnum = null,
        public ?CreditDebit $direction = null,
        public bool $isReversal = false,
        public ?string $message = null,
        public ?string $counterpartyName = null,
        public ?string $counterpartyAccount = null,
        public ?string $counterpartyIban = null,
        public ?string $paymentType = null,
        public ?string $endToEndIdentification = null,
        public PaymentSymbols $symbols = new PaymentSymbols(null, null, null),
        public array $raw = [],
    ) {}

    public static function fromArray(array $data): self
    {
        $amountValue = data_get($data, 'amount.value', 0);
        $reference = data_get($data, 'entryDetails.transactionDetails.remittanceInformation.structured.creditorReferenceInformation.reference');
        $reference = is_string($reference) ? $reference : null;
        $symbols = PaymentSymbols::fromReference($reference);
        $status = (string) ($data['status'] ?? '');
        $indicator = (string) ($data['creditDebitIndicator'] ?? '');
        $direction = $indicator === '' ? null : CreditDebit::tryFrom($indicator);
        $counterparty = self::counterparty($data, $direction);

        return new self(
            (string) ($data['entryReference'] ?? ''),
            $status,
            $indicator,
            (float) (is_int($amountValue) || is_float($amountValue) || is_string($amountValue) ? $amountValue : 0),
            (string) data_get($data, 'amount.currency', ''),
            self::nullableString(data_get($data, 'bookingDate.date')),
            self::nullableString(data_get($data, 'enteredDate.date')),
            self::nullableString(data_get($data, 'valueDate.date')),
            self::nullableString(data_get($data, 'entryDetails.transactionDetails.purpose.proprietary')),
            self::nullableString(data_get($data, 'entryDetails.transactionDetails.references.transactionDescription')),
            $reference,
            $symbols->vs,
            $symbols->ks,
            $symbols->ss,
            MinorAmount::fromApi(is_int($amountValue) || is_float($amountValue) || is_string($amountValue) ? $amountValue : 0),
            $status === '' ? null : TransactionStatus::tryFrom($status),
            $direction,
            ($data['reversalIndicator'] ?? false) === true,
            self::nullableString(data_get($data, 'entryDetails.transactionDetails.remittanceInformation.unstructured')),
            $counterparty['name'],
            $counterparty['account'],
            $counterparty['iban'],
            self::nullableString(data_get($data, 'entryDetails.transactionDetails.references.clearingSystemReference')),
            self::nullableString(data_get($data, 'entryDetails.transactionDetails.references.endToEndIdentification')),
            $symbols,
            $data,
        );
    }

    public function isIncoming(): bool
    {
        return $this->direction === CreditDebit::Credit;
    }

    public function isBooked(): bool
    {
        return $this->statusEnum === TransactionStatus::Booked;
    }

    public function isSettledIncomingPayment(): bool
    {
        return $this->isBooked() && $this->isIncoming() && ! $this->isReversal;
    }

    /**
     * @deprecated Use PaymentSymbols::fromReference()
     *
     * @return array{variableSymbol: ?string, constantSymbol: ?string, specificSymbol: ?string}
     */
    protected static function extractSymbols(string $reference): array
    {
        $symbols = PaymentSymbols::fromReference($reference === '' ? null : $reference);

        return [
            'variableSymbol' => $symbols->vs,
            'constantSymbol' => $symbols->ks,
            'specificSymbol' => $symbols->ss,
        ];
    }

    /**
     * @return array{name: ?string, account: ?string, iban: ?string}
     */
    private static function counterparty(array $data, ?CreditDebit $direction): array
    {
        $empty = ['name' => null, 'account' => null, 'iban' => null];

        $role = match ($direction) {
            CreditDebit::Credit => 'debtor',
            CreditDebit::Debit => 'creditor',
            null => null,
        };

        if ($role === null) {
            return $empty;
        }

        $parties = 'entryDetails.transactionDetails.relatedParties.'.$role;
        $iban = self::nullableString(data_get($data, $parties.'Account.identification.iban'));
        $bban = self::nullableString(data_get($data, $parties.'Account.identification.other.identification'));

        return [
            'name' => self::nullableString(data_get($data, $parties.'.name')),
            'account' => $bban ?? $iban,
            'iban' => $iban,
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
