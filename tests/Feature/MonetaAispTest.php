<?php

namespace SloukaPetr\Moneta\Tests\Feature;

use Illuminate\Support\Facades\Http;
use SloukaPetr\Moneta\Facades\Moneta;
use SloukaPetr\Moneta\Tests\TestCase;

class MonetaAispTest extends TestCase
{
    public function test_fake_can_be_used_for_accounts(): void
    {
        Moneta::fake([
            'https://api.moneta.cz/api/v4/vip/aisp/my/accounts*' => Http::response([
                'accounts' => [
                    [
                        'id' => 'acc-fake',
                        'nameI18N' => 'Fake account',
                        'currency' => 'EUR',
                        'identification' => ['iban' => 'DE0000000000000000000000', 'other' => '2222'],
                        'ownersNames' => ['Fake Corp'],
                        'servicer' => ['bankCode' => '1000', 'bic' => 'DEUTDEFF', 'countryCode' => 'DE'],
                    ],
                ],
                'pageNumber' => 0,
                'pageSize' => 10,
                'pageCount' => 1,
            ], 200),
        ]);

        $accounts = Moneta::accounts()->all();

        $this->assertCount(1, $accounts);
        $this->assertSame('acc-fake', $accounts[0]->id);
        $this->assertSame('EUR', $accounts[0]->currency);
    }

    public function test_transactions_extract_symbols_from_creditor_reference(): void
    {
        Http::fake([
            'https://api.moneta.cz/api/v4/vip/aisp/my/accounts/acc-1/transactions*' => Http::response([
                'transactions' => [
                    [
                        'entryReference' => 'trx-1',
                        'status' => 'BOOK',
                        'creditDebitIndicator' => 'DBIT',
                        'amount' => ['currency' => 'CZK', 'value' => 101.5],
                        'bookingDate' => ['date' => '2026-08-01'],
                        'valueDate' => ['date' => '2026-08-01'],
                        'entryDetails' => [
                            'transactionDetails' => [
                                'remittanceInformation' => [
                                    'structured' => [
                                        'creditorReferenceInformation' => [
                                            'reference' => 'VS:123456 KS:0308 SS:0001',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'pageNumber' => 0,
                'pageSize' => 10,
                'pageCount' => 1,
            ], 200),
        ]);

        $transactions = Moneta::transactions()->get('acc-1');

        $this->assertCount(1, $transactions);
        $this->assertSame('123456', $transactions[0]->variableSymbol);
        $this->assertSame('0308', $transactions[0]->constantSymbol);
        $this->assertSame('0001', $transactions[0]->specificSymbol);
    }

    public function test_transactions_all_walks_every_page(): void
    {
        Http::fake([
            'https://api.moneta.cz/api/v4/vip/aisp/my/accounts/acc-1/transactions*' => Http::sequence()
                ->push([
                    'transactions' => [
                        $this->transaction('trx-1'),
                    ],
                    'pageNumber' => 0,
                    'pageSize' => 1,
                    'pageCount' => 2,
                    'nextPage' => 1,
                ])
                ->push([
                    'transactions' => [
                        $this->transaction('trx-2'),
                    ],
                    'pageNumber' => 1,
                    'pageSize' => 1,
                    'pageCount' => 2,
                ]),
        ]);

        $transactions = Moneta::transactions()->all('acc-1', ['fromDate' => '2026-08-01']);

        $this->assertSame(['trx-1', 'trx-2'], array_map(
            fn ($transaction) => $transaction->entryReference,
            $transactions,
        ));

        $pages = collect(Http::recorded())->map(function (array $pair) {
            parse_str((string) parse_url($pair[0]->url(), PHP_URL_QUERY), $query);

            return (int) ($query['page'] ?? -1);
        })->all();

        $this->assertSame([0, 1], $pages);
    }

    public function test_statement_file_download_returns_binary_body(): void
    {
        Http::fake([
            'https://api.moneta.cz/api/v4/vip/aisp/my/accounts/statement*' => Http::response('<xml>statement</xml>', 200, [
                'Content-Type' => 'application/octet-stream',
            ]),
        ]);

        $content = Moneta::statements()->downloadFile('file-123');

        $this->assertSame('<xml>statement</xml>', $content);
    }

    public function test_balance_accepts_single_balance_object_response(): void
    {
        Http::fake([
            'https://api.moneta.cz/api/v4/vip/aisp/my/accounts/acc-1/balance*' => Http::response([
                'amount' => ['currency' => 'CZK', 'value' => 125.25],
                'creditDebitIndicator' => 'CRDT',
                'date' => ['dateTime' => '2026-08-10T12:00:00+00:00'],
                'type' => ['codeOrProprietary' => ['code' => 'CLAV']],
            ], 200),
        ]);

        $balance = Moneta::accounts()->getBalance('acc-1');

        $this->assertSame('CZK', $balance->currency);
        $this->assertSame(125.25, $balance->value);
        $this->assertSame('CRDT', $balance->creditDebitIndicator);
        $this->assertSame('2026-08-10T12:00:00+00:00', $balance->dateTime);
        $this->assertSame('CLAV', $balance->balanceType);
    }

    public function test_statement_download_url_is_exposed_from_response(): void
    {
        Http::fake([
            'https://api.moneta.cz/api/v4/vip/aisp/my/accounts/acc-1/statement/download*' => Http::response([
                'dataDownloadURL' => 'https://example.test/statements/123.pdf',
                'exportStatement' => [],
                'transactions' => [],
            ], 200),
        ]);

        $url = Moneta::statements()->downloadUrl('acc-1', 'stmt-1');

        $this->assertSame('https://example.test/statements/123.pdf', $url);
    }

    public function test_context_override_changes_auth_and_business_headers(): void
    {
        Http::fake([
            '*' => Http::response(['accounts' => [], 'pageNumber' => 0, 'pageSize' => 10, 'pageCount' => 0], 200),
        ]);

        Moneta::withContext([
            'api_token' => 'override-token',
            'business_name' => 'Override Business',
            'identification_number' => '99999999',
        ])->accounts()->all();

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer override-token')
                && $request->hasHeader('business_name', 'Override Business')
                && $request->hasHeader('identification_number', '99999999');
        });
    }

    /** @return array<string, mixed> */
    protected function transaction(string $entryReference): array
    {
        return [
            'entryReference' => $entryReference,
            'status' => 'BOOK',
            'creditDebitIndicator' => 'CRDT',
            'amount' => ['currency' => 'CZK', 'value' => 10],
        ];
    }
}
