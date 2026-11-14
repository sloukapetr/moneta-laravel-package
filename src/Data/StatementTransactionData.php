<?php

declare(strict_types=1);

namespace SloukaPetr\Moneta\Data;

use SloukaPetr\Moneta\Enums\CreditDebit;
use SloukaPetr\Moneta\Support\MinorAmount;
use SloukaPetr\Moneta\Support\PaymentSymbols;

final readonly class StatementTransactionData
{
    public function __construct(
        public string $entryReference,
        public int $amountMinor,
        public ?CreditDebit $direction,
        public ?string $bookingDate,
        public ?string $valueDate,
        public PaymentSymbols $symbols,
        public ?string $counterpartyName,
        public ?string $counterpartyAccount,
        public ?string $counterpartyIban,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $indicator = (string) ($data['creditDebitIndicator'] ?? '');
        $direction = $indicator === '' ? null : CreditDebit::tryFrom($indicator);
        $details = data_get($data, 'entryDetails.transactionDetails');
        $details = is_array($details) ? $details : [];
        $counterparty = self::counterparty($details, $direction);

        return new self(
            (string) ($data['entryReference'] ?? ''),
            MinorAmount::fromApi(self::amount(data_get($data, 'amount.value', 0))),
            $direction,
            self::nullableString($data['bookingDate'] ?? null),
            self::nullableString($data['valueDate'] ?? null),
            self::symbols($details),
            $counterparty['name'],
            $counterparty['account'],
            $counterparty['iban'],
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private static function symbols(array $details): PaymentSymbols
    {
        $information = self::nullableString($details['additionalTransactionInformation'] ?? null);
        $symbols = PaymentSymbols::fromReference($information);

        if ($symbols->vs !== null || $symbols->ks !== null || $symbols->ss !== null) {
            return $symbols;
        }

        $references = $details['references'] ?? [];

        if (! is_array($references)) {
            return $symbols;
        }

        $text = implode(' ', array_filter($references, is_string(...)));

        return PaymentSymbols::fromReference($text === '' ? null : $text);
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array{name: ?string, account: ?string, iban: ?string}
     */
    private static function counterparty(array $details, ?CreditDebit $direction): array
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

        $parties = is_array($details['relatedParties'] ?? null) ? $details['relatedParties'] : [];
        $party = is_array($parties[$role] ?? null) ? $parties[$role] : [];
        $account = is_array($parties[$role.'Account'] ?? null) ? $parties[$role.'Account'] : [];
        $iban = self::nullableString(data_get($account, 'identification.iban'));
        $bban = self::nullableString(data_get($account, 'identification.other.identification'));

        return [
            'name' => self::nullableString($party['name'] ?? null),
            'account' => $bban ?? $iban,
            'iban' => $iban,
        ];
    }

    private static function amount(mixed $value): int|float|string
    {
        return is_int($value) || is_float($value) || is_string($value) ? $value : 0;
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
