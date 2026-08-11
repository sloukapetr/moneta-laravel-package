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
        $response = $this->moneta->get('api/v4/vip/aisp/my/accounts/'.$id.'/balance', $filters);

        $balances = $response['balances'] ?? $response;

        if (is_array($balances) && array_is_list($balances)) {
            $balance = $balances[0] ?? [];
        } else {
            $balance = $balances;
        }

        return BalanceData::fromArray($balance);
    }
}
