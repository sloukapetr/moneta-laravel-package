<?php

namespace SloukaPetr\Moneta\Tests\Feature;

use Illuminate\Support\Facades\Http;
use SloukaPetr\Moneta\Data\BalanceData;
use SloukaPetr\Moneta\Data\TransactionData;
use SloukaPetr\Moneta\Enums\BalanceType;
use SloukaPetr\Moneta\Enums\CreditDebit;
use SloukaPetr\Moneta\Facades\Moneta;
use SloukaPetr\Moneta\Support\PaymentSymbols;
use SloukaPetr\Moneta\Tests\TestCase;

class MonetaTransactionDetailsTest extends TestCase
{
    public function test_booked_incoming_payment_exposes_minor_amount_symbols_and_debtor(): void
    {
        Http::fake([
            'https://api.moneta.cz/api/v4/vip/aisp/my/accounts/acc-1/transactions*' => Http::response([
                'transactions' => [
                    $this->transaction('CRDT', 'BOOK', 'VS:2026001 KS:0308 SS:12'),
                ],
            ], 200),
        ]);

        $transaction = Moneta::transactions()->get('acc-1')[0];

        $this->assertSame(150050, $transaction->amountMinor);
        $this->assertSame('2026001', $transaction->symbols->vs);
        $this->assertSame('0308', $transaction->symbols->ks);
        $this->assertSame('12', $transaction->symbols->ss);
        $this->assertSame(CreditDebit::Credit, $transaction->direction);
        $this->assertTrue($transaction->isSettledIncomingPayment());
        $this->assertSame('Payer s.r.o.', $transaction->counterpartyName);
        $this->assertSame('123456789/0600', $transaction->counterpartyAccount);
        $this->assertSame('CZ650600000000123456789', $transaction->counterpartyIban);
        $this->assertSame('Faktura 2026001', $transaction->message);
        $this->assertSame('PAYMENT_ORDER_DOMESTIC', $transaction->paymentType);
        $this->assertSame('E2E-1', $transaction->endToEndIdentification);
        $this->assertSame('trx-in', $transaction->raw['entryReference']);
    }

    public function test_outgoing_payment_uses_creditor_as_counterparty(): void
    {
        $transaction = TransactionData::fromArray($this->transaction('DBIT', 'BOOK', 'VS:9'));

        $this->assertSame(CreditDebit::Debit, $transaction->direction);
        $this->assertSame('Payee a.s.', $transaction->counterpartyName);
        $this->assertSame('999888777/0100', $transaction->counterpartyAccount);
        $this->assertFalse($transaction->isIncoming());
    }

    public function test_reversal_is_not_a_settled_incoming_payment(): void
    {
        $transaction = TransactionData::fromArray($this->transaction('CRDT', 'BOOK', 'VS:1', true));

        $this->assertTrue($transaction->isReversal);
        $this->assertFalse($transaction->isSettledIncomingPayment());
    }

    public function test_pending_payment_is_not_booked(): void
    {
        $transaction = TransactionData::fromArray($this->transaction('CRDT', 'PDNG', 'VS:1'));

        $this->assertFalse($transaction->isBooked());
        $this->assertFalse($transaction->isSettledIncomingPayment());
    }

    public function test_unknown_status_does_not_fail_mapping(): void
    {
        $transaction = TransactionData::fromArray($this->transaction('CRDT', 'XYZ', null));

        $this->assertNull($transaction->statusEnum);
        $this->assertSame('XYZ', $transaction->status);
        $this->assertNull(TransactionData::fromArray($this->transaction('NOPE', 'BOOK', null))->direction);
    }

    public function test_symbol_reference_with_commas_matches_spaces(): void
    {
        $spaces = PaymentSymbols::fromReference('VS:2026001 KS:0308 SS:12');
        $commas = PaymentSymbols::fromReference('VS:2026001,KS:0308,SS:12');

        $this->assertSame($spaces->vs, $commas->vs);
        $this->assertSame($spaces->ks, $commas->ks);
        $this->assertSame($spaces->ss, $commas->ss);
    }

    public function test_get_balances_returns_booked_and_available_amounts(): void
    {
        Http::fake([
            'https://api.moneta.cz/api/v4/vip/aisp/my/accounts/acc-1/balance*' => Http::response([
                'balances' => [
                    [
                        'amount' => ['currency' => 'CZK', 'value' => 1500.5],
                        'creditDebitIndicator' => 'CRDT',
                        'type' => ['codeOrProprietary' => ['code' => 'CLBD']],
                    ],
                    [
                        'amount' => ['currency' => 'CZK', 'value' => 1400],
                        'creditDebitIndicator' => 'CRDT',
                        'type' => ['codeOrProprietary' => ['code' => 'CLAV']],
                    ],
                ],
            ], 200),
        ]);

        $balances = Moneta::accounts()->getBalances('acc-1');

        $this->assertSame(BalanceType::Booked, $balances[0]->balanceTypeEnum);
        $this->assertSame(150050, $balances[0]->amountMinor);
        $this->assertSame('CLBD', $balances[0]->balanceType);
        $this->assertSame(BalanceType::Available, $balances[1]->balanceTypeEnum);
        $this->assertSame(140000, $balances[1]->amountMinor);
        $this->assertSame('CLAV', $balances[1]->balanceType);
        $this->assertNull(BalanceData::fromArray([
            'amount' => ['currency' => 'CZK', 'value' => 1],
            'type' => ['codeOrProprietary' => ['code' => 'XXXX']],
        ])->balanceTypeEnum);
    }

    public function test_account_exposes_bban_as_account_number(): void
    {
        Http::fake([
            'https://api.moneta.cz/api/v4/vip/aisp/my/accounts*' => Http::response([
                'accounts' => [
                    [
                        'id' => 'acc-1',
                        'nameI18N' => 'Provoz',
                        'currency' => 'CZK',
                        'identification' => [
                            'iban' => 'CZ650600000000123456789',
                            'other' => '123456789/0600',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $account = Moneta::accounts()->all()[0];

        $this->assertSame('123456789/0600', $account->accountNumber);
    }

    /**
     * @return array<string, mixed>
     */
    private function transaction(string $indicator, string $status, ?string $reference, bool $reversal = false): array
    {
        return [
            'entryReference' => 'trx-in',
            'status' => $status,
            'creditDebitIndicator' => $indicator,
            'reversalIndicator' => $reversal,
            'amount' => ['currency' => 'CZK', 'value' => 1500.5],
            'bookingDate' => ['date' => '2026-08-01'],
            'entryDetails' => [
                'transactionDetails' => [
                    'remittanceInformation' => [
                        'unstructured' => 'Faktura 2026001',
                        'structured' => [
                            'creditorReferenceInformation' => [
                                'reference' => $reference,
                            ],
                        ],
                    ],
                    'references' => [
                        'clearingSystemReference' => 'PAYMENT_ORDER_DOMESTIC',
                        'endToEndIdentification' => 'E2E-1',
                    ],
                    'relatedParties' => [
                        'debtor' => ['name' => 'Payer s.r.o.'],
                        'debtorAccount' => [
                            'identification' => [
                                'iban' => 'CZ650600000000123456789',
                                'other' => ['identification' => '123456789/0600'],
                            ],
                        ],
                        'creditor' => ['name' => 'Payee a.s.'],
                        'creditorAccount' => [
                            'identification' => [
                                'iban' => 'CZ1101000000000999888777',
                                'other' => ['identification' => '999888777/0100'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
