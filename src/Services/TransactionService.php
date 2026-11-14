<?php

namespace SloukaPetr\Moneta\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\LazyCollection;
use SloukaPetr\Moneta\Data\TransactionData;
use SloukaPetr\Moneta\MonetaManager;

class TransactionService
{
    public function __construct(protected MonetaManager $moneta)
    {
    }

    /** @return array<int, TransactionData> */
    public function get(string $accountId, array $filters = []): array
    {
        $response = $this->moneta->get('api/v4/vip/aisp/my/accounts/'.$accountId.'/transactions', $filters);

        return array_map(
            fn (array $transaction) => TransactionData::fromArray($transaction),
            $response['transactions'] ?? []
        );
    }

    /** @return array<int, TransactionData> */
    public function all(string $accountId, array $filters = []): array
    {
        return $this->cursor($accountId, $filters)->all();
    }

    /**
     * Pages through transactions in batches of at most 100. Requests run during iteration.
     *
     * Endpoint: `GET /api/v4/vip/aisp/my/accounts/{id}/transactions`.
     *
     * @param  array<string, mixed>  $filters
     * @return LazyCollection<int, TransactionData>
     */
    public function cursor(string $accountId, array $filters = []): LazyCollection
    {
        $filters['size'] = min(100, max(1, (int) ($filters['size'] ?? 100)));
        unset($filters['page']);

        return LazyCollection::make(function () use ($accountId, $filters) {
            $page = 0;
            $seenPages = [];

            do {
                if (isset($seenPages[$page]) || count($seenPages) >= 1000) {
                    break;
                }

                $seenPages[$page] = true;

                $response = $this->moneta->get(
                    'api/v4/vip/aisp/my/accounts/'.$accountId.'/transactions',
                    [...$filters, 'page' => $page],
                );

                foreach ($response['transactions'] ?? [] as $transaction) {
                    if (is_array($transaction)) {
                        yield TransactionData::fromArray($transaction);
                    }
                }

                if (! array_key_exists('nextPage', $response)) {
                    break;
                }

                $nextPage = (int) $response['nextPage'];

                if ($nextPage === $page) {
                    break;
                }

                $page = $nextPage;
            } while (true);
        });
    }

    /**
     * Transactions whose `valueDate` falls in the inclusive range. Dates are sent as `Y-m-d`.
     *
     * Endpoint: `GET /api/v4/vip/aisp/my/accounts/{id}/transactions`.
     *
     * @return LazyCollection<int, TransactionData>
     */
    public function between(string $accountId, CarbonInterface|string $from, CarbonInterface|string|null $to = null): LazyCollection
    {
        $filters = [
            'fromDate' => $this->dateFilter($from),
            'sort' => 'date',
            'order' => 'ASC',
        ];

        if ($to !== null) {
            $filters['toDate'] = $this->dateFilter($to);
        }

        return $this->cursor($accountId, $filters);
    }

    private function dateFilter(CarbonInterface|string $date): string
    {
        if ($date instanceof CarbonInterface) {
            return $date->format('Y-m-d');
        }

        return CarbonImmutable::parse($date)->format('Y-m-d');
    }
}
