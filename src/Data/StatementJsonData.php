<?php

declare(strict_types=1);

namespace SloukaPetr\Moneta\Data;

use SloukaPetr\Moneta\Enums\CreditDebit;
use SloukaPetr\Moneta\Exceptions\ApiException;

final readonly class StatementJsonData
{
    /**
     * @param  array<int, StatementTransactionData>  $transactions
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public StatementSummaryData $summary,
        public array $transactions,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $summary = $data['exportStatement'] ?? null;

        if (! is_array($summary)) {
            throw new ApiException('Moneta statement response does not contain exportStatement.');
        }

        $transactions = [];

        foreach ($data['transactions'] ?? [] as $transaction) {
            if (is_array($transaction)) {
                $transactions[] = StatementTransactionData::fromArray($transaction);
            }
        }

        return new self(
            StatementSummaryData::fromArray($summary),
            $transactions,
            $data,
        );
    }

    public function verify(): bool
    {
        return $this->summary->beginBalanceMinor
            + $this->summary->creditSumMinor
            - $this->summary->debitSumMinor
            === $this->summary->endBalanceMinor;
    }

    /**
     * @return array{credit: int, debit: int}
     */
    public function transactionTotals(): array
    {
        $credit = 0;
        $debit = 0;

        foreach ($this->transactions as $transaction) {
            $amount = abs($transaction->amountMinor);

            if ($transaction->direction === CreditDebit::Credit) {
                $credit += $amount;
            } elseif ($transaction->direction === CreditDebit::Debit) {
                $debit += $amount;
            }
        }

        return ['credit' => $credit, 'debit' => $debit];
    }
}
