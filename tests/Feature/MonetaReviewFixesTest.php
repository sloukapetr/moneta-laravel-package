<?php

namespace SloukaPetr\Moneta\Tests\Feature;

use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use SloukaPetr\Moneta\Data\BalanceData;
use SloukaPetr\Moneta\Data\PaymentOrderData;
use SloukaPetr\Moneta\Exceptions\RateLimitException;
use SloukaPetr\Moneta\Facades\Moneta;
use SloukaPetr\Moneta\Tests\TestCase;

class MonetaReviewFixesTest extends TestCase
{
    protected function tearDown(): void
    {
        Sleep::fake(false);

        parent::tearDown();
    }

    public function test_profile_clone_shares_fake_payments_and_recorded_calls(): void
    {
        $fake = Moneta::fake();
        $fake->incomingPayment('acc-1', 100);

        $transactions = Moneta::profile('default')->transactions()->all('acc-1');

        $this->assertCount(1, $transactions);
        $this->assertSame(100, $transactions[0]->amountMinor);
        Moneta::assertTransactionsFetched('acc-1');
        Moneta::assertProfileUsed('default');

        $profile = Moneta::profile('default');
        $fake->incomingPayment('acc-1', 200);

        $this->assertCount(2, $profile->transactions()->all('acc-1'));
        Moneta::assertTransactionsFetched('acc-1');
    }

    public function test_debit_balance_keeps_absolute_amount_and_exposes_the_sign(): void
    {
        $balance = BalanceData::fromArray([
            'amount' => ['currency' => 'CZK', 'value' => 150.00],
            'creditDebitIndicator' => 'DBIT',
        ]);

        $this->assertSame(15000, $balance->amountMinor);
        $this->assertSame(-15000, $balance->signedAmountMinor);
        $this->assertTrue($balance->isNegative());
    }

    public function test_429_waits_for_retry_after_and_post_is_not_retried(): void
    {
        Sleep::fake();
        config(['moneta.retry' => 2, 'moneta.retry_sleep' => 100]);

        Http::fake([
            'https://api.moneta.cz/api/v4/vip/aisp/my/accounts*' => Http::sequence()
                ->push(['errors' => [['error' => 'RATE_LIMIT', 'message' => 'Too many requests']]], 429, ['Retry-After' => '2'])
                ->push(['accounts' => [], 'pageNumber' => 0, 'pageSize' => 10, 'pageCount' => 0], 200),
        ]);

        Moneta::accounts()->all();

        $this->assertCount(2, Http::recorded());
        Sleep::assertSlept(function (CarbonInterval $duration): bool {
            return (int) $duration->totalMilliseconds === 2000;
        });

        Sleep::fake();
        Http::fake([
            'https://api.moneta.cz/api/v4/vip/pisp/my/payments/batch' => Http::response(
                ['errors' => [['error' => 'RATE_LIMIT', 'message' => 'Too many requests']]],
                429,
                ['Retry-After' => '2'],
            ),
        ]);

        try {
            Moneta::payments()->createBatch([
                new PaymentOrderData(100, 'CZK', '123456789/0600', '19-2000145399/0800'),
            ]);
            $this->fail('RateLimitException was not thrown.');
        } catch (RateLimitException) {
            $this->assertCount(1, Http::recorded());
            Sleep::assertNeverSlept();
        }
    }

    public function test_token_endpoint_is_retried_after_429(): void
    {
        Sleep::fake();
        config([
            'moneta.api_token' => null,
            'moneta.sandbox' => true,
            'moneta.client_id' => 'sandbox-client',
            'moneta.client_secret' => 'sandbox-secret',
            'moneta.retry' => 2,
            'moneta.retry_sleep' => 100,
        ]);

        Http::fake([
            'https://api.moneta.cz/auth/oauth/v2/token' => Http::sequence()
                ->push(['errors' => [['message' => 'Too many requests']]], 429, ['Retry-After' => '2'])
                ->push(['access_token' => 'token-1', 'expires_in' => 1200], 200),
            'https://api.moneta.cz/api/v4/vip/aisp/my/accounts*' => Http::response([
                'accounts' => [],
                'pageNumber' => 0,
                'pageSize' => 10,
                'pageCount' => 0,
            ], 200),
        ]);

        Moneta::accounts()->all();

        $tokenCalls = Http::recorded()->filter(
            fn (array $pair): bool => str_contains($pair[0]->url(), '/auth/oauth/v2/token'),
        );

        $this->assertCount(2, $tokenCalls);
        Sleep::assertSlept(function (CarbonInterval $duration): bool {
            return (int) $duration->totalMilliseconds === 2000;
        });
    }

    public function test_invalid_amount_does_not_stop_the_cursor_and_missing_reference_is_visible(): void
    {
        Http::fake([
            'https://api.moneta.cz/api/v4/vip/aisp/my/accounts/acc-1/transactions*' => Http::response([
                'transactions' => [
                    [
                        'status' => 'PDNG',
                        'creditDebitIndicator' => 'DBIT',
                        'amount' => ['currency' => 'CZK', 'value' => 1.234],
                    ],
                    [
                        'entryReference' => 'trx-ok',
                        'status' => 'BOOK',
                        'creditDebitIndicator' => 'CRDT',
                        'amount' => ['currency' => 'CZK', 'value' => 10],
                    ],
                ],
                'pageNumber' => 0,
                'pageSize' => 100,
                'pageCount' => 1,
            ], 200),
        ]);

        $transactions = Moneta::transactions()->cursor('acc-1')->all();

        $this->assertCount(2, $transactions);
        $this->assertTrue($transactions[0]->amountInvalid);
        $this->assertSame(0, $transactions[0]->amountMinor);
        $this->assertSame(1.234, $transactions[0]->raw['amount']['value']);
        $this->assertFalse($transactions[0]->hasReference());
        $this->assertFalse($transactions[1]->amountInvalid);
        $this->assertSame(1000, $transactions[1]->amountMinor);
        $this->assertTrue($transactions[1]->hasReference());
    }
}
