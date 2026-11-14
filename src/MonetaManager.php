<?php

namespace SloukaPetr\Moneta;

use Illuminate\Config\Repository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use SloukaPetr\Moneta\Exceptions\ApiException;
use SloukaPetr\Moneta\Exceptions\NetworkException;
use SloukaPetr\Moneta\Exceptions\NotFoundException;
use SloukaPetr\Moneta\Exceptions\RateLimitException;
use SloukaPetr\Moneta\Exceptions\TwoFactorRequiredException;
use SloukaPetr\Moneta\Exceptions\UnauthorizedException;
use SloukaPetr\Moneta\Exceptions\ValidationException;
use SloukaPetr\Moneta\Fakes\MonetaFake;
use SloukaPetr\Moneta\Services\AccountService;
use SloukaPetr\Moneta\Services\PaymentService;
use SloukaPetr\Moneta\Services\StatementService;
use SloukaPetr\Moneta\Services\TokenService;
use SloukaPetr\Moneta\Services\TransactionService;

class MonetaManager
{
    protected array $headers = [];

    protected array $overrides = [];

    protected ?string $profile = null;

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

    /**
     * Clone bound to a named connection. `default` is the flat configuration.
     * A named profile overrides only the keys it defines.
     */
    public function profile(string $name): static
    {
        $this->assertKnownProfile($name);

        $instance = clone $this;
        $instance->profile = $name;

        return $instance;
    }

    public function withClientCredentials(?string $scope = null): static
    {
        $tokenResponse = $this->issueAccessToken(scope: $scope);
        $token = (string) ($tokenResponse['access_token'] ?? '');

        if ($token === '') {
            throw new ApiException('Moneta OAuth token response does not contain access_token.');
        }

        return $this->withToken($token);
    }

    public function issueAccessToken(
        ?string $clientId = null,
        ?string $clientSecret = null,
        ?string $scope = null,
        string $grantType = 'client_credentials',
    ): array {
        $clientId ??= (string) $this->resolveConfig('client_id', '');
        $clientSecret ??= (string) $this->resolveConfig('client_secret', '');

        $scope = $scope ?? $this->configuredScope();

        $tokenUrl = (string) $this->resolveConfig('oauth_token_url', 'https://api.moneta.cz/auth/oauth/v2/token');

        if ($clientId === '' || $clientSecret === '') {
            throw new \InvalidArgumentException('Moneta OAuth client_id and client_secret must be configured.');
        }

        $cacheKey = $this->accessTokenCacheKey($tokenUrl, $clientId, $clientSecret, $scope, $grantType);

        $cached = Cache::get($cacheKey);

        if (is_array($cached) && is_string($cached['access_token'] ?? null) && $cached['access_token'] !== '') {
            return $cached;
        }

        try {
            $payload = [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => $grantType,
            ];

            if (is_string($scope) && trim($scope) !== '') {
                $payload['scope'] = $scope;
            }

            $response = $this->pendingRequest(idempotent: true)
                ->asForm()
                ->post($tokenUrl, $payload);
        } catch (ConnectionException $exception) {
            throw new NetworkException('Unable to connect to Moneta OAuth endpoint.', previous: $exception);
        }

        if ($response->failed()) {
            $this->throwForStatus($response);
        }

        $token = $response->json() ?? [];
        $expiresIn = (int) ($token['expires_in'] ?? 0);

        if (is_string($token['access_token'] ?? null) && $token['access_token'] !== '' && $expiresIn > 30) {
            Cache::put($cacheKey, $token, $expiresIn - 30);
        }

        return $token;
    }

    public function fake(array $responses = []): ?MonetaFake
    {
        if ($responses !== []) {
            Http::fake($responses);

            return null;
        }

        return new MonetaFake($this->config);
    }

    public function accounts(): AccountService
    {
        return new AccountService($this);
    }

    public function transactions(): TransactionService
    {
        return new TransactionService($this);
    }

    public function token(): TokenService
    {
        return new TokenService($this);
    }

    public function payments(): PaymentService
    {
        return new PaymentService($this);
    }

    public function statements(): StatementService
    {
        return new StatementService($this);
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
        return $this->dispatch($method, $path, $options, true);
    }

    private function dispatch(string $method, string $path, array $options, bool $refreshSandboxToken): Response
    {
        $query = $options['query'] ?? [];
        $json = $options['json'] ?? [];
        $headers = array_merge($this->defaultHeaders(), $options['headers'] ?? []);
        $issuedSandboxToken = false;

        if (! isset($headers['Authorization']) && $this->canIssueAccessToken()) {
            $accessToken = (string) ($this->issueAccessToken()['access_token'] ?? '');

            if ($accessToken === '') {
                throw new ApiException('Moneta OAuth token response does not contain access_token.');
            }

            $headers['Authorization'] = 'Bearer '.$accessToken;
            $issuedSandboxToken = (bool) $this->resolveConfig('sandbox', true);
        }

        $pendingRequest = $this->pendingRequest(strtolower($method) === 'get')
            ->baseUrl(rtrim($this->resolveConfig('base_url', 'https://api.moneta.cz'), '/'))
            ->withHeaders($headers);

        try {
            $response = match (strtolower($method)) {
                'get' => $pendingRequest->get($path, $query),
                'post' => is_string($options['body'] ?? null)
                    ? $pendingRequest->withBody($options['body'], 'application/json')->post($path)
                    : $pendingRequest->post($path, $json),
                'put' => $pendingRequest->put($path, $json),
                'patch' => $pendingRequest->patch($path, $json),
                'delete' => $pendingRequest->delete($path, $query),
                default => throw new \InvalidArgumentException('Unsupported HTTP method: '.$method),
            };
        } catch (ConnectionException $exception) {
            throw new NetworkException('Unable to connect to Moneta API.', previous: $exception);
        }

        if ($response->status() === 401 && $refreshSandboxToken && $issuedSandboxToken) {
            $this->forgetIssuedAccessToken();

            return $this->dispatch($method, $path, $options, false);
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
        $payload = is_array($payload) ? $payload : [];
        $status = $response->status();
        $message = $payload['errors'][0]['message']
            ?? $payload['error_description']
            ?? $payload['message']
            ?? 'Moneta API request failed.';
        $errorCode = $payload['errors'][0]['error'] ?? ($payload['error'] ?? null);
        $scope = $payload['errors'][0]['scope'] ?? null;
        $parameters = $payload['errors'][0]['parameters'] ?? [];

        if ($status === 401 && $errorCode === 'NARR') {
            throw new TwoFactorRequiredException($message, $status, $errorCode, $scope, is_array($parameters) ? $parameters : [], $payload);
        }

        if ($status === 401) {
            throw new UnauthorizedException($message, $status, $errorCode, $scope, is_array($parameters) ? $parameters : [], $payload);
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

        throw new ApiException($message, $status, $errorCode, $scope, is_array($parameters) ? $parameters : [], $payload);
    }

    protected function pendingRequest(bool $idempotent = false): PendingRequest
    {
        $request = Http::timeout((int) $this->resolveConfig('timeout', 15));

        if (! $idempotent) {
            return $request;
        }

        return $request->retry(
            max(1, (int) $this->resolveConfig('retry', 3)),
            fn (int $attempt, \Throwable $exception): int => $this->retryDelay($attempt, $exception),
            fn (\Throwable $exception): bool => $this->shouldRetry($exception),
            false,
        );
    }

    protected function retryDelay(int $attempt, \Throwable $exception): int
    {
        $cap = max(0, (int) $this->resolveConfig('retry_max_sleep', 5000));
        $fromHeader = $this->retryAfterMilliseconds($exception);

        if ($fromHeader !== null) {
            return min($cap, $fromHeader);
        }

        $base = max(0, (int) $this->resolveConfig('retry_sleep', 100));
        $shift = min(16, max(0, $attempt - 1));
        $delay = $base * (2 ** $shift);

        return (int) min($cap, $delay);
    }

    protected function retryAfterMilliseconds(\Throwable $exception): ?int
    {
        if (! $exception instanceof RequestException) {
            return null;
        }

        $header = trim($exception->response->header('Retry-After'));

        if ($header === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $header) === 1) {
            return (int) $header * 1000;
        }

        $date = \DateTimeImmutable::createFromFormat('!'.\DateTimeInterface::RFC7231, $header);

        if ($date === false) {
            return null;
        }

        return max(0, ($date->getTimestamp() - time()) * 1000);
    }

    protected function shouldRetry(\Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if (! $exception instanceof RequestException) {
            return false;
        }

        $status = $exception->response->status();

        return $status === 429 || $status >= 500;
    }

    protected function resolveConfig(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->overrides)) {
            return $this->overrides[$key];
        }

        $profileName = $this->activeProfileName();

        if ($profileName !== 'default') {
            $profile = $this->profileConfig($profileName);

            if (array_key_exists($key, $profile)) {
                return $profile[$key];
            }
        }

        return $this->config->get('moneta.'.$key, $default);
    }

    protected function activeProfileName(): string
    {
        $name = $this->profile ?? $this->config->get('moneta.default_profile', 'default');
        $name = is_string($name) && trim($name) !== '' ? $name : 'default';
        $this->assertKnownProfile($name);

        return $name;
    }

    protected function assertKnownProfile(string $name): void
    {
        if ($name === 'default' || array_key_exists($name, $this->profiles())) {
            return;
        }

        throw new \InvalidArgumentException('Unknown Moneta profile ['.$name.'].');
    }

    /**
     * @return array<string, mixed>
     */
    protected function profileConfig(string $name): array
    {
        $profile = $this->profiles()[$name] ?? [];

        return is_array($profile) ? $profile : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function profiles(): array
    {
        $profiles = $this->config->get('moneta.profiles', []);

        return is_array($profiles) ? $profiles : [];
    }

    protected function canIssueAccessToken(): bool
    {
        if (isset($this->headers['Authorization'])) {
            return false;
        }

        $apiToken = $this->resolveConfig('api_token');

        if (is_string($apiToken) && trim($apiToken) !== '') {
            return false;
        }

        $clientId = $this->resolveConfig('client_id');
        $clientSecret = $this->resolveConfig('client_secret');

        return is_string($clientId) && trim($clientId) !== ''
            && is_string($clientSecret) && trim($clientSecret) !== '';
    }

    protected function configuredScope(): ?string
    {
        $configuredScope = $this->resolveConfig('oauth_scope');

        if (is_string($configuredScope) && trim($configuredScope) !== '') {
            return $configuredScope;
        }

        if ((bool) $this->resolveConfig('sandbox', true)) {
            return 'vip_sandbox';
        }

        return null;
    }

    protected function forgetIssuedAccessToken(): void
    {
        $clientId = (string) $this->resolveConfig('client_id', '');
        $clientSecret = (string) $this->resolveConfig('client_secret', '');
        $tokenUrl = (string) $this->resolveConfig('oauth_token_url', 'https://api.moneta.cz/auth/oauth/v2/token');

        Cache::forget($this->accessTokenCacheKey(
            $tokenUrl,
            $clientId,
            $clientSecret,
            $this->configuredScope(),
            'client_credentials',
        ));
    }

    protected function accessTokenCacheKey(string $tokenUrl, string $clientId, string $clientSecret, ?string $scope, string $grantType): string
    {
        return 'moneta.oauth.'.hash('sha256', implode('|', [
            $tokenUrl,
            $clientId,
            $clientSecret,
            $scope ?? '',
            $grantType,
        ]));
    }
}
