<?php

namespace SloukaPetr\Moneta\Services;

use SloukaPetr\Moneta\Data\AccountData;
use SloukaPetr\Moneta\Data\BalanceData;
use SloukaPetr\Moneta\MonetaManager;

class AccountService
{
    public function __construct(protected MonetaManager $moneta)
    {
    }

    /** @return array<int, AccountData> */
    public function all(array $filters = []): array
    {
        $response = $this->moneta->get('api/v4/vip/aisp/my/accounts', $filters);

        return array_map(
            fn (array $account) => AccountData::fromArray($account),
            $response['accounts'] ?? []
        );
    }

    public function find(string $id): ?AccountData
    {
        $accounts = $this->all();

        foreach ($accounts as $account) {
            if ($account->id === $id) {
                return $account;
            }
        }

        return null;
    }

    public function getBalance(string $id, array $filters = []): BalanceData
    {
        $balances = $this->mapBalances(
            $this->moneta->get('api/v4/vip/aisp/my/accounts/'.$id.'/balance', $filters)
        );

        return $balances[0] ?? BalanceData::fromArray([]);
    }

    /**
     * All balances of one account, including booked (CLBD) and available (CLAV).
     *
     * Endpoint: `GET /api/v4/vip/aisp/my/accounts/{id}/balance`.
     *
     * @return array<int, BalanceData>
     */
    public function getBalances(string $id): array
    {
        return $this->mapBalances(
            $this->moneta->get('api/v4/vip/aisp/my/accounts/'.$id.'/balance')
        );
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<int, BalanceData>
     */
    private function mapBalances(array $response): array
    {
        $balances = $response['balances'] ?? $response;

        if (! is_array($balances)) {
            return [];
        }

        if (! array_is_list($balances)) {
            $balances = [$balances];
        }

        $mapped = [];

        foreach ($balances as $balance) {
            if (is_array($balance)) {
                $mapped[] = BalanceData::fromArray($balance);
            }
        }

        return $mapped;
    }
}
