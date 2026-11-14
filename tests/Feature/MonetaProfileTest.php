<?php

namespace SloukaPetr\Moneta\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use SloukaPetr\Moneta\Exceptions\ApiException;
use SloukaPetr\Moneta\Exceptions\TwoFactorRequiredException;
use SloukaPetr\Moneta\Exceptions\UnauthorizedException;
use SloukaPetr\Moneta\Facades\Moneta;
use SloukaPetr\Moneta\Tests\TestCase;

class MonetaProfileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_named_profile_sends_its_own_token_and_keeps_unset_flat_keys(): void
    {
        config([
            'moneta.api_token' => 'flat-token',
            'moneta.profiles' => [
                'b' => ['api_token' => 'profile-b-token'],
            ],
        ]);

        Http::fake([
            'https://api.moneta.cz/api/v4/vip/aisp/my/accounts*' => Http::response([
                'accounts' => [],
                'pageNumber' => 0,
                'pageSize' => 10,
                'pageCount' => 0,
            ], 200),
        ]);

        Moneta::profile('b')->accounts()->all();

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer profile-b-token')
                && $request->hasHeader('business_name', 'Test Business');
        });
    }

    public function test_without_profile_uses_flat_configuration(): void
    {
        config([
            'moneta.api_token' => 'flat-token',
            'moneta.profiles' => [
                'b' => ['api_token' => 'profile-b-token'],
            ],
        ]);

        Http::fake([
            'https://api.moneta.cz/api/v4/vip/aisp/my/accounts*' => Http::response([
                'accounts' => [],
                'pageNumber' => 0,
                'pageSize' => 10,
                'pageCount' => 0,
            ], 200),
        ]);

        Moneta::accounts()->all();

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer flat-token'));
    }

    public function test_unknown_profile_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Moneta::profile('missing');
    }

    public function test_sandbox_client_credentials_request_a_token_once_and_then_use_the_cache(): void
    {
        config([
            'moneta.api_token' => null,
            'moneta.sandbox' => true,
            'moneta.client_id' => 'sandbox-client',
            'moneta.client_secret' => 'sandbox-secret',
        ]);

        Http::fake([
            'https://api.moneta.cz/auth/oauth/v2/token' => Http::response([
                'access_token' => 'sandbox-access-token',
                'expires_in' => 1200,
            ], 200),
            'https://api.moneta.cz/api/v4/vip/aisp/my/accounts*' => Http::response([
                'accounts' => [],
                'pageNumber' => 0,
                'pageSize' => 10,
                'pageCount' => 0,
            ], 200),
        ]);

        Moneta::accounts()->all();
        Moneta::accounts()->all();

        $this->assertCount(1, $this->recorded('https://api.moneta.cz/auth/oauth/v2/token'));
        $apiCalls = $this->recorded('/api/v4/vip/aisp/my/accounts');
        $this->assertCount(2, $apiCalls);
        $this->assertTrue($apiCalls[0]->hasHeader('Authorization', 'Bearer sandbox-access-token'));
        $this->assertTrue($apiCalls[1]->hasHeader('Authorization', 'Bearer sandbox-access-token'));
    }

    public function test_sandbox_access_token_is_refreshed_once_after_401(): void
    {
        config([
            'moneta.api_token' => null,
            'moneta.sandbox' => true,
            'moneta.client_id' => 'sandbox-client',
            'moneta.client_secret' => 'sandbox-secret',
        ]);

        Http::fake([
            'https://api.moneta.cz/auth/oauth/v2/token' => Http::sequence()
                ->push(['access_token' => 'token-1', 'expires_in' => 1200], 200)
                ->push(['access_token' => 'token-2', 'expires_in' => 1200], 200),
            'https://api.moneta.cz/api/v4/vip/aisp/my/accounts*' => Http::sequence()
                ->push(['errors' => [['error' => 'UNAUTHORIZED', 'message' => 'Unauthorized']]], 401)
                ->push([
                    'accounts' => [],
                    'pageNumber' => 0,
                    'pageSize' => 10,
                    'pageCount' => 0,
                ], 200),
        ]);

        Moneta::accounts()->all();

        $this->assertCount(2, $this->recorded('https://api.moneta.cz/auth/oauth/v2/token'));
        $apiCalls = $this->recorded('/api/v4/vip/aisp/my/accounts');
        $this->assertCount(2, $apiCalls);
        $this->assertTrue($apiCalls[0]->hasHeader('Authorization', 'Bearer token-1'));
        $this->assertTrue($apiCalls[1]->hasHeader('Authorization', 'Bearer token-2'));
    }

    public function test_second_sandbox_401_is_thrown(): void
    {
        config([
            'moneta.api_token' => null,
            'moneta.sandbox' => true,
            'moneta.client_id' => 'sandbox-client',
            'moneta.client_secret' => 'sandbox-secret',
            'moneta.retry' => 1,
        ]);

        Http::fake([
            'https://api.moneta.cz/auth/oauth/v2/token' => Http::sequence()
                ->push(['access_token' => 'token-1', 'expires_in' => 1200], 200)
                ->push(['access_token' => 'token-2', 'expires_in' => 1200], 200)
                ->push(['access_token' => 'token-3', 'expires_in' => 1200], 200),
            'https://api.moneta.cz/api/v4/vip/aisp/my/accounts*' => Http::response([
                'errors' => [['error' => 'UNAUTHORIZED', 'message' => 'Unauthorized']],
            ], 401),
        ]);

        try {
            Moneta::accounts()->all();
            $this->fail('UnauthorizedException was not thrown.');
        } catch (UnauthorizedException $exception) {
            $this->assertNotInstanceOf(TwoFactorRequiredException::class, $exception);
            $this->assertCount(2, $this->recorded('https://api.moneta.cz/auth/oauth/v2/token'));
            $this->assertCount(2, $this->recorded('/api/v4/vip/aisp/my/accounts'));
        }
    }

    public function test_narr_is_mapped_to_two_factor_required_exception(): void
    {
        Http::fake([
            'https://api.moneta.cz/api/v4/vip/aisp/my/accounts/acc-1/transactions*' => Http::response([
                'errors' => [[
                    'error' => 'NARR',
                    'message' => 'Two factor verification is needed for requested transaction history.',
                ]],
            ], 401),
        ]);

        $this->expectException(TwoFactorRequiredException::class);

        Moneta::transactions()->get('acc-1');
    }

    public function test_exceptions_do_not_expose_client_secret_or_api_token(): void
    {
        $secret = 'client-secret-do-not-leak';
        $apiToken = 'api-token-do-not-leak';

        config([
            'moneta.api_token' => null,
            'moneta.sandbox' => true,
            'moneta.client_id' => 'client-id',
            'moneta.client_secret' => $secret,
        ]);

        Http::fake([
            'https://api.moneta.cz/auth/oauth/v2/token' => Http::response([
                'error' => 'invalid_client',
                'error_description' => 'Client authentication failed.',
            ], 401),
        ]);

        try {
            Moneta::accounts()->all();
            $this->fail('Exception was not thrown.');
        } catch (UnauthorizedException $exception) {
            $this->assertSecretsStayOutOfException($exception, $secret, $apiToken);
        }

        $this->assertSame($secret, $this->recorded('https://api.moneta.cz/auth/oauth/v2/token')[0]['client_secret']);

        config([
            'moneta.api_token' => $apiToken,
            'moneta.client_secret' => null,
        ]);

        Http::fake([
            'https://api.moneta.cz/api/v4/vip/aisp/my/accounts*' => Http::response([
                'errors' => [['error' => 'UNAUTHORIZED', 'message' => 'Unauthorized']],
            ], 401),
        ]);

        try {
            Moneta::accounts()->all();
            $this->fail('Exception was not thrown.');
        } catch (UnauthorizedException $exception) {
            $this->assertSecretsStayOutOfException($exception, $secret, $apiToken);
        }
    }

    private function assertSecretsStayOutOfException(ApiException $exception, string $secret, string $apiToken): void
    {
        $rendered = $exception->getMessage().json_encode($exception->payload);

        $this->assertStringNotContainsString($secret, $rendered);
        $this->assertStringNotContainsString($apiToken, $rendered);
    }

    /**
     * @return array<int, \Illuminate\Http\Client\Request>
     */
    private function recorded(string $urlPart): array
    {
        return collect(Http::recorded())
            ->map(fn (array $pair) => $pair[0])
            ->filter(fn ($request) => str_contains($request->url(), $urlPart))
            ->values()
            ->all();
    }
}
