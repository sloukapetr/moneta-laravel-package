<?php

declare(strict_types=1);

namespace SloukaPetr\Moneta\Fakes;

use Carbon\CarbonInterface;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Config\Repository;
use Illuminate\Http\Client\Response;
use PHPUnit\Framework\Assert;
use SloukaPetr\Moneta\Enums\BatchStatus;
use SloukaPetr\Moneta\MonetaManager;

final class MonetaFake extends MonetaManager
{
    private MonetaFakeState $state;

    public function __construct(Repository $config)
    {
        parent::__construct($config);

        $this->state = new MonetaFakeState;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function withAccount(string $id, array $overrides = []): static
    {
        $this->state->accounts[] = array_replace_recursive([
            'id' => $id,
            'nameI18N' => 'Account '.$id,
            'currency' => 'CZK',
            'identification' => [
                'iban' => '',
                'other' => '',
            ],
            'ownersNames' => [],
            'servicer' => [
                'bankCode' => '0600',
                'bic' => 'AGBACZPP',
                'countryCode' => 'CZ',
            ],
        ], $overrides);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function withTransaction(string $accountId, array $overrides = []): static
    {
        $this->state->transactions[$accountId][] = array_replace_recursive([
            'entryReference' => $this->nextEntryReference(),
            'status' => 'BOOK',
            'creditDebitIndicator' => 'CRDT',
            'reversalIndicator' => false,
            'amount' => [
                'currency' => 'CZK',
                'value' => 0,
            ],
        ], $overrides);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function incomingPayment(string $accountId, int $amountMinor, ?string $vs = null, array $overrides = []): static
    {
        $transaction = [
            'entryReference' => $this->nextEntryReference(),
            'status' => 'BOOK',
            'creditDebitIndicator' => 'CRDT',
            'reversalIndicator' => false,
            'amount' => [
                'currency' => 'CZK',
                'value' => $this->minorToApiValue($amountMinor),
            ],
        ];

        if ($vs !== null) {
            $transaction['entryDetails'] = [
                'transactionDetails' => [
                    'remittanceInformation' => [
                        'structured' => [
                            'creditorReferenceInformation' => [
                                'reference' => 'VS:'.$vs,
                            ],
                        ],
                    ],
                ],
            ];
        }

        return $this->withTransaction($accountId, array_replace_recursive($transaction, $overrides));
    }

    public function withBalance(string $accountId, int $bookedMinor, ?int $availableMinor = null): static
    {
        $balances = [$this->balance('CLBD', $bookedMinor)];

        if ($availableMinor !== null) {
            $balances[] = $this->balance('CLAV', $availableMinor);
        }

        $this->state->balances[$accountId] = ['balances' => $balances];

        return $this;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<int, array<string, mixed>>  $transactions
     */
    public function withStatement(string $accountId, array $summary, array $transactions = []): static
    {
        $this->state->statements[$accountId][] = [
            'summary' => $summary,
            'transactions' => $transactions,
        ];

        return $this;
    }

    public function withTokenExpiration(CarbonInterface $at): static
    {
        $this->state->tokenExpiration = $at;

        return $this;
    }

    public function failWith(int $status, string $errorCode = 'ERROR'): static
    {
        $this->state->failureStatus = $status;
        $this->state->failureCode = $errorCode;

        return $this;
    }

    public function assertAccountsFetched(): void
    {
        Assert::assertNotEmpty(
            $this->callsOf('accounts'),
            'Expected a Moneta account list request, but none was recorded.',
        );
    }

    public function assertTransactionsFetched(string $accountId, ?callable $filters = null): void
    {
        $matches = array_filter(
            $this->callsOf('transactions', $accountId),
            function (array $call) use ($filters): bool {
                if ($filters === null) {
                    return true;
                }

                return $filters($call['query']) === true;
            },
        );

        Assert::assertNotEmpty(
            $matches,
            $filters === null
                ? 'Expected a Moneta transaction request for account ['.$accountId.'], but none was recorded.'
                : 'Expected a Moneta transaction request for account ['.$accountId.'] matching the given filters, but none was recorded.',
        );
    }

    public function assertNothingFetched(): void
    {
        Assert::assertSame(
            [],
            $this->state->calls,
            'Moneta recorded an API request, but none were expected.',
        );
    }

    public function withBatchStatus(string $batchId, BatchStatus $status): self
    {
        $this->state->batchStatuses[$batchId] = $status;

        return $this;
    }

    public function assertBatchCreated(?callable $callback = null): void
    {
        $matches = array_filter(
            $this->callsOf('batch'),
            function (array $call) use ($callback): bool {
                if ($callback === null) {
                    return true;
                }

                return $callback($call['payload'] ?? []) === true;
            },
        );

        Assert::assertNotEmpty(
            $matches,
            $callback === null
                ? 'Expected a Moneta payment batch, but none was recorded.'
                : 'Expected a Moneta payment batch matching the given callback, but none was recorded.',
        );
    }

    public function assertProfileUsed(string $name): void
    {
        $matches = array_filter(
            $this->state->calls,
            fn (array $call): bool => ($call['profile'] ?? null) === $name,
        );

        Assert::assertNotEmpty(
            $matches,
            'Expected a Moneta request for profile ['.$name.'], but none was recorded.',
        );
    }

    public function assertNoBatchCreated(): void
    {
        Assert::assertSame(
            [],
            $this->callsOf('batch'),
            'A Moneta payment batch was created, but none were expected.',
        );
    }

    public function rawRequest(string $method, string $path, array $options = []): Response
    {
        $query = is_array($options['query'] ?? null) ? $options['query'] : [];
        $payload = $this->payload($options);
        $normalized = trim($path, '/');
        $kind = 'other';
        $accountId = null;

        if ($normalized === 'api/v4/vip/aisp/my/accounts') {
            $kind = 'accounts';
        } elseif (preg_match('#^api/v4/vip/aisp/my/accounts/([^/]+)/transactions$#', $normalized, $matches) === 1) {
            $kind = 'transactions';
            $accountId = rawurldecode($matches[1]);
        } elseif (preg_match('#^api/v4/vip/aisp/my/accounts/([^/]+)/balance$#', $normalized, $matches) === 1) {
            $kind = 'balance';
            $accountId = rawurldecode($matches[1]);
        } elseif (preg_match('#^api/v4/vip/aisp/my/accounts/([^/]+)/statement/download$#', $normalized, $matches) === 1) {
            $kind = 'statement-download';
            $accountId = rawurldecode($matches[1]);
        } elseif ($normalized === 'api/v1/vip/token/introspect') {
            $kind = 'introspect';
        } elseif ($normalized === 'api/v4/vip/pisp/my/payments/batch' && strtolower($method) === 'post') {
            $kind = 'batch';
        } elseif ($normalized === 'api/v4/vip/pisp/my/payments/batch/onebyone' && strtolower($method) === 'post') {
            $kind = 'onebyone';
        } elseif (preg_match('#^api/v4/vip/pisp/my/payments/batch/onebyone/([^/]+)/status$#', $normalized, $matches) === 1) {
            $kind = 'onebyone-status';
            $accountId = rawurldecode($matches[1]);
        } elseif (preg_match('#^api/v4/vip/pisp/my/payments/batch/([^/]+)/status$#', $normalized, $matches) === 1) {
            $kind = 'batch-status';
            $accountId = rawurldecode($matches[1]);
        }

        $this->state->calls[] = [
            'kind' => $kind,
            'accountId' => $accountId,
            'query' => $query,
            'payload' => $payload,
            'profile' => $this->activeProfileName(),
        ];

        $response = $this->response($kind, $accountId, $query, $payload);

        if ($response->failed()) {
            $this->throwForStatus($response);
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>|null
     */
    private function payload(array $options): ?array
    {
        if (is_string($options['body'] ?? null)) {
            $decoded = json_decode($options['body'], true);

            return is_array($decoded) ? $decoded : null;
        }

        return is_array($options['json'] ?? null) ? $options['json'] : null;
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $payload
     */
    private function response(string $kind, ?string $accountId, array $query, ?array $payload): Response
    {
        if ($this->state->failureStatus !== null) {
            return $this->jsonResponse($this->state->failureStatus, [
                'errors' => [[
                    'error' => $this->state->failureCode,
                    'message' => $this->state->failureCode,
                ]],
            ]);
        }

        if ($kind === 'statement-download') {
            $statement = $this->statementBody($accountId ?? '', $query);

            if ($statement === null) {
                return $this->jsonResponse(404, [
                    'errors' => [[
                        'error' => 'ID_NOT_FOUND',
                        'message' => 'Moneta fake has no statement for this account.',
                    ]],
                ]);
            }

            return $this->jsonResponse(200, $statement);
        }

        $body = match ($kind) {
            'accounts' => $this->accountPage(),
            'transactions' => $this->transactionPage($accountId ?? '', $query),
            'balance' => $this->state->balances[$accountId ?? ''] ?? ['balances' => []],
            'introspect' => [
                'expiration' => $this->state->tokenExpiration?->format('Y-m-d\TH:i:sP'),
            ],
            'batch' => $this->batchCreated(),
            'batch-status', 'onebyone-status' => $this->statusPayload($accountId ?? ''),
            'onebyone' => $this->oneByOneCreated($payload ?? []),
            default => [
                'errors' => [[
                    'error' => 'ID_NOT_FOUND',
                    'message' => 'Moneta fake has no response for this request.',
                ]],
            ],
        };

        $status = $kind === 'other' ? 404 : 200;

        return $this->jsonResponse($status, $body);
    }

    /**
     * @return array<string, mixed>
     */
    private function accountPage(): array
    {
        return [
            'accounts' => $this->state->accounts,
            'pageNumber' => 0,
            'pageSize' => max(1, count($this->state->accounts)),
            'pageCount' => $this->state->accounts === [] ? 0 : 1,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function transactionPage(string $accountId, array $query): array
    {
        $size = min(100, max(1, (int) ($query['size'] ?? 100)));
        $page = max(0, (int) ($query['page'] ?? 0));
        $transactions = $this->state->transactions[$accountId] ?? [];
        $pageCount = (int) ceil(count($transactions) / $size);

        $body = [
            'transactions' => array_values(array_slice($transactions, $page * $size, $size)),
            'pageNumber' => $page,
            'pageSize' => $size,
            'pageCount' => $pageCount,
        ];

        if (($page + 1) < $pageCount) {
            $body['nextPage'] = $page + 1;
        }

        return $body;
    }

    /**
     * @return array<string, mixed>
     */
    private function balance(string $code, int $amountMinor): array
    {
        return [
            'amount' => [
                'currency' => 'CZK',
                'value' => $this->minorToApiValue($amountMinor),
            ],
            'creditDebitIndicator' => $amountMinor < 0 ? 'DBIT' : 'CRDT',
            'type' => [
                'codeOrProprietary' => [
                    'code' => $code,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function jsonResponse(int $status, array $body): Response
    {
        return new Response(new PsrResponse(
            $status,
            ['Content-Type' => 'application/json'],
            (string) json_encode($body, JSON_THROW_ON_ERROR),
        ));
    }

    private function nextEntryReference(): string
    {
        $this->state->entrySequence++;

        return 'entry-'.$this->state->entrySequence;
    }

    private function minorToApiValue(int $amountMinor): int|string
    {
        $sign = $amountMinor < 0 ? '-' : '';
        $absolute = abs($amountMinor);
        $major = intdiv($absolute, 100);
        $fraction = $absolute % 100;

        if ($fraction === 0) {
            return (int) ($sign.$major);
        }

        return $sign.$major.'.'.str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    private function statementBody(string $accountId, array $query): ?array
    {
        $requestedId = (string) ($query['accountStatementId'] ?? '');

        foreach ($this->state->statements[$accountId] ?? [] as $statement) {
            $storedId = (string) ($statement['summary']['accountStatementId'] ?? '');

            if ($requestedId === '' || $storedId === '' || $storedId === $requestedId) {
                return [
                    'exportStatement' => $statement['summary'],
                    'transactions' => $statement['transactions'],
                ];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function batchCreated(): array
    {
        $this->state->batchSequence++;

        return ['batchId' => 'batch-'.$this->state->batchSequence];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function oneByOneCreated(array $payload): array
    {
        $items = [];

        foreach ($payload['payments'] ?? [] as $index => $payment) {
            $instruction = is_array($payment)
                ? (string) ($payment['paymentIdentification']['instructionIdentification'] ?? ($index + 1))
                : (string) ($index + 1);

            $this->state->paymentSequence++;

            $items[] = [
                'instructionIdentification' => $instruction,
                'transactionIdentification' => 'tx-'.$this->state->paymentSequence,
            ];
        }

        return [
            'totalCount' => count($items),
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function statusPayload(string $id): array
    {
        $status = $this->state->batchStatuses[$id] ?? BatchStatus::AwaitingSignature;

        return ['instructionStatus' => $status->value];
    }

    /**
     * @return array<int, array{kind: string, accountId: ?string, query: array<string, mixed>, payload: ?array}>
     */
    private function callsOf(string $kind, ?string $accountId = null): array
    {
        return array_values(array_filter(
            $this->state->calls,
            function (array $call) use ($kind, $accountId): bool {
                if ($call['kind'] !== $kind) {
                    return false;
                }

                return $accountId === null || $call['accountId'] === $accountId;
            },
        ));
    }
}
