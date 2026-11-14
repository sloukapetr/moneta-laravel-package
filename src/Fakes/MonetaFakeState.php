<?php

declare(strict_types=1);

namespace SloukaPetr\Moneta\Fakes;

use Carbon\CarbonInterface;
use SloukaPetr\Moneta\Enums\BatchStatus;

final class MonetaFakeState
{
    /**
     * @var array<int, array<string, mixed>>
     */
    public array $accounts = [];

    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    public array $transactions = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $balances = [];

    /**
     * @var array<string, array<int, array{summary: array<string, mixed>, transactions: array<int, array<string, mixed>>}>>
     */
    public array $statements = [];

    public ?CarbonInterface $tokenExpiration = null;

    public ?int $failureStatus = null;

    public string $failureCode = 'ERROR';

    public int $entrySequence = 0;

    public int $batchSequence = 0;

    public int $paymentSequence = 0;

    /**
     * @var array<string, BatchStatus>
     */
    public array $batchStatuses = [];

    /**
     * @var array<int, array{kind: string, accountId: ?string, query: array<string, mixed>, payload: ?array, profile: string}>
     */
    public array $calls = [];
}
