<?php

declare(strict_types=1);

namespace SloukaPetr\Moneta\Fakes;

use Carbon\CarbonInterface;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Config\Repository;
use Illuminate\Http\Client\Response;
use PHPUnit\Framework\Assert;
use SloukaPetr\Moneta\MonetaManager;

final class MonetaFake extends MonetaManager
{
    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $accounts = [];

    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    protected array $transactions = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $balances = [];

    protected ?CarbonInterface $tokenExpiration = null;

    protected ?int $failureStatus = null;

    protected string $failureCode = 'ERROR';

    protected int $entrySequence = 0;

    /**
     * @var array<int, array{kind: string, accountId: ?string, query: array<string, mixed>}>
     */
    protected array $calls = [];

    public function __construct(Repository $config)
    {
        parent::__construct($config);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function withAccount(string $id, array $overrides = []): static
    {
        $this->accounts[] = array_replace_recursive([
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
        $this->transactions[$accountId][] = array_replace_recursive([
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

        $this->balances[$accountId] = ['balances' => $balances];

        return $this;
    }

    public function withTokenExpiration(CarbonInterface $at): static
    {
        $this->tokenExpiration = $at;

        return $this;
    }

    public function failWith(int $status, string $errorCode = 'ERROR'): static
    {
        $this->failureStatus = $status;
        $this->failureCode = $errorCode;

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
            $this->calls,
            'Moneta recorded an API request, but none were expected.',
        );
    }

    public function rawRequest(string $method, string $path, array $options = []): Response
    {
        $query = is_array($options['query'] ?? null) ? $options['query'] : [];
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
        } elseif ($normalized === 'api/v1/vip/token/introspect') {
            $kind = 'introspect';
        }

        $this->calls[] = [
            'kind' => $kind,
            'accountId' => $accountId,
            'query' => $query,
        ];

        $response = $this->response($kind, $accountId, $query);

        if ($response->failed()) {
            $this->throwForStatus($response);
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function response(string $kind, ?string $accountId, array $query): Response
    {
        if ($this->failureStatus !== null) {
            return $this->jsonResponse($this->failureStatus, [
                'errors' => [[
                    'error' => $this->failureCode,
                    'message' => $this->failureCode,
                ]],
            ]);
        }

        $body = match ($kind) {
            'accounts' => $this->accountPage(),
            'transactions' => $this->transactionPage($accountId ?? '', $query),
            'balance' => $this->balances[$accountId ?? ''] ?? ['balances' => []],
            'introspect' => [
                'expiration' => $this->tokenExpiration?->format('Y-m-d\TH:i:sP'),
            ],
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
            'accounts' => $this->accounts,
            'pageNumber' => 0,
            'pageSize' => max(1, count($this->accounts)),
            'pageCount' => $this->accounts === [] ? 0 : 1,
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
        $transactions = $this->transactions[$accountId] ?? [];
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
        $this->entrySequence++;

        return 'entry-'.$this->entrySequence;
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
     * @return array<int, array{kind: string, accountId: ?string, query: array<string, mixed>}>
     */
    private function callsOf(string $kind, ?string $accountId = null): array
    {
        return array_values(array_filter(
            $this->calls,
            function (array $call) use ($kind, $accountId): bool {
                if ($call['kind'] !== $kind) {
                    return false;
                }

                return $accountId === null || $call['accountId'] === $accountId;
            },
        ));
    }
}
