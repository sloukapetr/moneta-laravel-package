<?php

namespace SloukaPetr\Moneta;

use Illuminate\Config\Repository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use SloukaPetr\Moneta\Exceptions\NetworkException;
use SloukaPetr\Moneta\Exceptions\NotFoundException;
use SloukaPetr\Moneta\Exceptions\RateLimitException;
use SloukaPetr\Moneta\Exceptions\UnauthorizedException;
use SloukaPetr\Moneta\Exceptions\ValidationException;
use SloukaPetr\Moneta\Services\AccountService;
use SloukaPetr\Moneta\Services\PaymentService;
use SloukaPetr\Moneta\Services\StatementService;
use SloukaPetr\Moneta\Services\TransactionService;

class MonetaManager
{
    protected array $headers = [];
    protected array $overrides = [];

    public function __construct(protected Repository $config)
    {
    }

    public function withToken(string $token): static
    {
        $instance = clone $this;
        $instance->headers['Authorization'] = 'Bearer '.$token;

        return $instance;
    }

    public function withContext(array $context): static
    {
        $instance = clone $this;
        $instance->overrides = array_merge($this->overrides, $context);

        return $instance;
    }

    public function fake(array $responses = []): void
    {
        Http::fake($responses);
    }

    public function accounts(): AccountService
    {
        return new AccountService($this);
    }

    public function transactions(): TransactionService
    {
        return new TransactionService($this);
    }

    public function statements(): StatementService
    {
        return new StatementService($this);
    }

    public function payments(): PaymentService
    {
        return new PaymentService($this);
    }

    public function get(string $path, array $query = [], array $headers = []): array
    {
        return $this->request('get', $path, ['query' => $query, 'headers' => $headers]);
    }

    public function post(string $path, array $data = [], array $headers = []): array
    {
        return $this->request('post', $path, ['json' => $data, 'headers' => $headers]);
    }

    public function put(string $path, array $data = [], array $headers = []): array
    {
        return $this->request('put', $path, ['json' => $data, 'headers' => $headers]);
    }

    public function getRaw(string $path, array $query = [], array $headers = []): Response
    {
        return $this->rawRequest('get', $path, ['query' => $query, 'headers' => $headers]);
    }

    public function request(string $method, string $path, array $options = []): array
    {
        $response = $this->rawRequest($method, $path, $options);

        return $response->json() ?? [];
    }

    public function rawRequest(string $method, string $path, array $options = []): Response
    {
        $query = $options['query'] ?? [];
        $json = $options['json'] ?? [];
        $pendingRequest = Http::baseUrl(rtrim($this->resolveConfig('base_url', 'https://api.moneta.cz'), '/'))
            ->withHeaders(array_merge($this->defaultHeaders(), $options['headers'] ?? []))
            ->timeout((int) $this->resolveConfig('timeout', 15))
            ->retry((int) $this->resolveConfig('retry', 3), 100, null, false);

        try {
            $response = match (strtolower($method)) {
                'get' => $pendingRequest->get($path, $query),
                'post' => $pendingRequest->post($path, $json),
                'put' => $pendingRequest->put($path, $json),
                'patch' => $pendingRequest->patch($path, $json),
                'delete' => $pendingRequest->delete($path, $query),
                default => throw new \InvalidArgumentException('Unsupported HTTP method: '.$method),
            };
        } catch (ConnectionException $exception) {
            throw new NetworkException('Unable to connect to Moneta API.', 0, $exception);
        }

        if ($response->failed()) {
            $this->throwForStatus($response);
        }

        return $response;
    }

    public function defaultHeaders(): array
    {
        $token = $this->resolveConfig('api_token');

        return array_filter([
            'Authorization' => $this->headers['Authorization'] ?? ($token ? 'Bearer '.$token : null),
            'Accept' => 'application/json',
            'identification_number' => $this->resolveConfig('identification_number'),
            'business_name' => $this->resolveConfig('business_name'),
            'application_name' => $this->resolveConfig('application_name'),
        ], fn ($value) => $value !== null);
    }

    protected function throwForStatus(Response $response): void
    {
        $payload = $response->json();
        $status = $response->status();
        $message = $payload['errors'][0]['message'] ?? 'Moneta API request failed.';
        $errorCode = $payload['errors'][0]['error'] ?? null;
        $scope = $payload['errors'][0]['scope'] ?? null;
        $parameters = $payload['errors'][0]['parameters'] ?? [];

        if ($status === 401) {
            throw new UnauthorizedException($message, $status, $errorCode, $scope, $parameters, $payload);
        }

        if ($status === 400) {
            throw new ValidationException($message, $status, $errorCode, $scope, $parameters, $payload);
        }

        if ($status === 404) {
            throw new NotFoundException($message, $status, $errorCode, $scope, $parameters, $payload);
        }

        if ($status === 429) {
            throw new RateLimitException($message, $status, $errorCode, $scope, $parameters, $payload);
        }

        throw new \RuntimeException('Moneta API request failed with status '.$status);
    }

    protected function resolveConfig(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->overrides)) {
            return $this->overrides[$key];
        }

        return $this->config->get('moneta.'.$key, $default);
    }
}
