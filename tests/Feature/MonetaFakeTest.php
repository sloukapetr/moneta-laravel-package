<?php

namespace SloukaPetr\Moneta\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\AssertionFailedError;
use SloukaPetr\Moneta\Exceptions\TwoFactorRequiredException;
use SloukaPetr\Moneta\Facades\Moneta;
use SloukaPetr\Moneta\Tests\TestCase;

class MonetaFakeTest extends TestCase
{
    public function test_incoming_payment_is_a_settled_credit_with_minor_amount_and_symbol(): void
    {
        $fake = Moneta::fake();
        $fake->incomingPayment('acc-1', 150000, '2026001');

        $transactions = Moneta::transactions()->all('acc-1');

        $this->assertCount(1, $transactions);
        $this->assertSame(150000, $transactions[0]->amountMinor);
        $this->assertSame('2026001', $transactions[0]->symbols->vs);
        $this->assertTrue($transactions[0]->isSettledIncomingPayment());
        $this->assertCount(0, Http::recorded());
    }

    public function test_cursor_reads_250_transactions_as_three_pages(): void
    {
        $fake = Moneta::fake();

        for ($i = 0; $i < 250; $i++) {
            $fake->incomingPayment('acc-1', 100);
        }

        $transactions = Moneta::transactions()->cursor('acc-1')->all();

        $this->assertCount(250, $transactions);

        $pages = [];
        $fake->assertTransactionsFetched('acc-1', function (array $query) use (&$pages): bool {
            $pages[] = (int) $query['page'];

            return true;
        });

        $this->assertSame([0, 1, 2], $pages);
    }

    public function test_fail_with_narr_throws_two_factor_required_exception(): void
    {
        Moneta::fake()->failWith(401, 'NARR');

        $this->expectException(TwoFactorRequiredException::class);

        Moneta::transactions()->get('acc-1');
    }

    public function test_account_balance_and_token_use_api_shapes(): void
    {
        $expiresAt = CarbonImmutable::parse('2026-03-06T11:38:09+01:00');
        $fake = Moneta::fake();
        $fake->withAccount('acc-1', ['currency' => 'EUR'])
            ->withBalance('acc-1', 10000, 9000)
            ->withTokenExpiration($expiresAt);

        $this->assertSame('EUR', Moneta::accounts()->all()[0]->currency);

        $balances = Moneta::accounts()->getBalances('acc-1');

        $this->assertSame(10000, $balances[0]->amountMinor);
        $this->assertSame(9000, $balances[1]->amountMinor);
        $this->assertTrue(Moneta::token()->introspect()->expiresAt?->equalTo($expiresAt));
        $fake->assertAccountsFetched();
    }

    public function test_url_map_still_uses_http_fake(): void
    {
        Moneta::fake([
            'https://api.moneta.cz/api/v4/vip/aisp/my/accounts*' => Http::response([
                'accounts' => [],
                'pageNumber' => 0,
                'pageSize' => 10,
                'pageCount' => 0,
            ], 200),
        ]);

        $this->assertSame([], Moneta::accounts()->all());
        $this->assertCount(1, Http::recorded());
    }

    public function test_assertions_fail_with_a_clear_message(): void
    {
        $fake = Moneta::fake();

        try {
            $fake->assertAccountsFetched();
            $this->fail('Account assertion did not fail.');
        } catch (AssertionFailedError $exception) {
            $this->assertStringContainsString('Expected a Moneta account list request, but none was recorded.', $exception->getMessage());
        }

        $fake->incomingPayment('acc-1', 100);
        Moneta::transactions()->all('acc-1');

        try {
            $fake->assertNothingFetched();
            $this->fail('Empty assertion did not fail.');
        } catch (AssertionFailedError $exception) {
            $this->assertStringContainsString('Moneta recorded an API request, but none were expected.', $exception->getMessage());
        }

        try {
            $fake->assertTransactionsFetched('other-account');
            $this->fail('Transaction assertion did not fail.');
        } catch (AssertionFailedError $exception) {
            $this->assertStringContainsString('Expected a Moneta transaction request for account [other-account], but none was recorded.', $exception->getMessage());
        }
    }
}
