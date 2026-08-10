<?php

namespace SloukaPetr\Moneta\Services;

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
}
