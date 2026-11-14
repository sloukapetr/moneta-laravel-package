<?php

namespace SloukaPetr\Moneta\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use SloukaPetr\Moneta\Facades\Moneta;
use SloukaPetr\Moneta\Tests\TestCase;

class MonetaTransactionCursorTest extends TestCase
{
    public function test_cursor_fetches_three_pages_only_while_iterating(): void
    {
        Http::fake([
            'https://api.moneta.cz/api/v4/vip/aisp/my/accounts/acc-1/transactions*' => Http::sequence()
                ->push($this->page('trx-1', 1), 200)
                ->push($this->page('trx-2', 2), 200)
                ->push($this->page('trx-3', null), 200),
        ]);

        $cursor = Moneta::transactions()->cursor('acc-1');

        $this->assertCount(0, Http::recorded());

        $references = $cursor->map(fn ($transaction) => $transaction->entryReference)->all();

        $this->assertSame(['trx-1', 'trx-2', 'trx-3'], $references);

        $pages = collect(Http::recorded())->map(function (array $pair) {
            parse_str((string) parse_url($pair[0]->url(), PHP_URL_QUERY), $query);

            return (int) ($query['page'] ?? -1);
        })->all();

        $this->assertSame([0, 1, 2], $pages);
        $this->assertCount(3, Http::recorded());
    }

    public function test_between_sends_value_date_range_and_ascending_date_sort(): void
    {
        Http::fake([
            'https://api.moneta.cz/api/v4/vip/aisp/my/accounts/acc-1/transactions*' => Http::response(
                $this->page('trx-1', null),
                200,
            ),
        ]);

        $cursor = Moneta::transactions()->between(
            'acc-1',
            '2026-01-02',
            CarbonImmutable::parse('2026-03-06T11:38:09+01:00'),
        );

        $this->assertCount(0, Http::recorded());

        $this->assertSame('trx-1', $cursor->first()->entryReference);

        parse_str((string) parse_url(Http::recorded()[0][0]->url(), PHP_URL_QUERY), $query);

        $this->assertSame('2026-01-02', $query['fromDate']);
        $this->assertSame('2026-03-06', $query['toDate']);
        $this->assertSame('date', $query['sort']);
        $this->assertSame('ASC', $query['order']);
        $this->assertSame('100', $query['size']);
        $this->assertSame('0', $query['page']);
    }

    /**
     * @return array<string, mixed>
     */
    private function page(string $entryReference, ?int $nextPage): array
    {
        $page = [
            'transactions' => [
                [
                    'entryReference' => $entryReference,
                    'status' => 'BOOK',
                    'creditDebitIndicator' => 'CRDT',
                    'amount' => ['currency' => 'CZK', 'value' => 10],
                ],
            ],
            'pageNumber' => 0,
            'pageSize' => 100,
            'pageCount' => 3,
        ];

        if ($nextPage !== null) {
            $page['nextPage'] = $nextPage;
        }

        return $page;
    }
}
