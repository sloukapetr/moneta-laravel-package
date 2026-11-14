<?php

namespace SloukaPetr\Moneta\Tests\Feature;

use Illuminate\Support\Facades\Http;
use SloukaPetr\Moneta\Facades\Moneta;
use SloukaPetr\Moneta\Tests\TestCase;

class MonetaStatementJsonTest extends TestCase
{
    public function test_embedded_json_statement_converts_balances_and_verifies(): void
    {
        Moneta::fake()->withStatement('acc-1', [
            'accountPrefix' => '19',
            'accountNumber' => '2000145399',
            'bankCode' => '0600',
            'currency' => 'CZK',
            'begBalance' => '1000.00',
            'creditSum' => '150.50',
            'debitSum' => '20.00',
            'finBalance' => '1130.50',
            'period' => '2026-02',
            'sequenceNumber' => 2,
            'generateDate' => '2026-03-01',
        ], [
            [
                'entryReference' => 'trx-1',
                'creditDebitIndicator' => 'CRDT',
                'amount' => ['value' => '150.50', 'currency' => 'CZK'],
                'bookingDate' => '2026-02-10',
                'valueDate' => '2026-02-10',
                'entryDetails' => [
                    'transactionDetails' => [
                        'additionalTransactionInformation' => 'VS:2026001,KS:0308',
                        'relatedParties' => [
                            'debtor' => ['name' => 'Zakaznik'],
                            'debtorAccount' => [
                                'identification' => [
                                    'other' => ['identification' => '123456789/0800'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'entryReference' => 'trx-2',
                'creditDebitIndicator' => 'DBIT',
                'amount' => ['value' => '20.00', 'currency' => 'CZK'],
                'bookingDate' => '2026-02-11',
                'valueDate' => '2026-02-11',
            ],
        ]);

        $statement = Moneta::statements()->json('acc-1', 'stmt-1');

        $this->assertSame('19-2000145399', $statement->summary->accountNumber);
        $this->assertSame('0600', $statement->summary->bankCode);
        $this->assertSame(100000, $statement->summary->beginBalanceMinor);
        $this->assertSame(15050, $statement->summary->creditSumMinor);
        $this->assertSame(2000, $statement->summary->debitSumMinor);
        $this->assertSame(113050, $statement->summary->endBalanceMinor);
        $this->assertSame('2026-03-01', $statement->summary->generatedAt);
        $this->assertTrue($statement->verify());
        $this->assertSame(['credit' => 15050, 'debit' => 2000], $statement->transactionTotals());
        $this->assertSame('2026001', $statement->transactions[0]->symbols->vs);
        $this->assertSame('Zakaznik', $statement->transactions[0]->counterpartyName);
        $this->assertSame('123456789/0800', $statement->transactions[0]->counterpartyAccount);
        $this->assertSame(0, Http::recorded()->count());
    }

    public function test_verify_fails_when_the_closing_balance_does_not_add_up(): void
    {
        Moneta::fake()->withStatement('acc-1', [
            'accountNumber' => '2000145399',
            'bankCode' => '0600',
            'currency' => 'CZK',
            'begBalance' => '1000.00',
            'creditSum' => '150.50',
            'debitSum' => '20.00',
            'finBalance' => '1130.51',
            'period' => '2026-02',
            'sequenceNumber' => 2,
        ]);

        $statement = Moneta::statements()->json('acc-1', 'stmt-1');

        $this->assertFalse($statement->verify());
    }

    public function test_download_url_is_fetched_and_parsed_as_json(): void
    {
        $file = (string) json_encode([
            'exportStatement' => [
                'accountNumber' => '2000145399',
                'bankCode' => '0600',
                'currency' => 'CZK',
                'begBalance' => '10.00',
                'creditSum' => '1.00',
                'debitSum' => '0.00',
                'finBalance' => '11.00',
                'period' => '2026-02',
                'sequenceNumber' => 1,
            ],
            'transactions' => [],
        ], JSON_THROW_ON_ERROR);

        Http::fake([
            'https://api.moneta.cz/api/v4/vip/aisp/my/accounts/acc-1/statement/download*' => Http::response([
                'dataDownloadURL' => 'https://api.moneta.cz/api/v4/vip/aisp/my/accounts/statement?file=file-9',
            ], 200),
            'https://api.moneta.cz/api/v4/vip/aisp/my/accounts/statement*' => Http::response($file, 200, [
                'Content-Type' => 'application/json',
            ]),
        ]);

        $statement = Moneta::statements()->json('acc-1', 'stmt-9');

        $this->assertSame(1000, $statement->summary->beginBalanceMinor);
        $this->assertSame(1100, $statement->summary->endBalanceMinor);
        $this->assertTrue($statement->verify());

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/statement/download')
                && str_contains(urldecode($request->url()), 'format=JSON')
                && str_contains(urldecode($request->url()), 'feature=JSON$EMBD');
        });
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'file=file-9'));
    }

    public function test_list_for_february_sends_the_first_and_last_day(): void
    {
        Http::fake([
            'https://api.moneta.cz/api/v4/vip/aisp/my/accounts/acc-1/statements*' => Http::response([
                'accountStatements' => [],
            ], 200),
        ]);

        $this->assertSame([], Moneta::statements()->listFor('acc-1', 2026, 2));

        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['from'] ?? null) === '2026-02-01'
                && ($query['to'] ?? null) === '2026-02-28';
        });
    }
}
