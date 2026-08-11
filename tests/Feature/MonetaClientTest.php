<?php

namespace SloukaPetr\Moneta\Tests\Feature;

use Illuminate\Support\Facades\Http;
use SloukaPetr\Moneta\Facades\Moneta;
use SloukaPetr\Moneta\Tests\TestCase;

class MonetaClientTest extends TestCase
{
    public function test_fake_can_be_used_for_accounts(): void
    {
        Moneta::fake([
            'https://api.moneta.cz/api/v4/vip/aisp/my/accounts*' => Http::response([
                'accounts' => [
                    [
                        'id' => 'acc-fake',
                        'nameI18N' => 'Fake account',
                        'currency' => 'EUR',
                        'identification' => ['iban' => 'DE0000000000000000000000', 'other' => '2222'],
                        'ownersNames' => ['Fake Corp'],
                        'servicer' => ['bankCode' => '1000', 'bic' => 'DEUTDEFF', 'countryCode' => 'DE'],
                    ],
                ],
                'pageNumber' => 0,
                'pageSize' => 10,
                'pageCount' => 1,
            ], 200),
        ]);

        $accounts = Moneta::accounts()->all();

        $this->assertCount(1, $accounts);
        $this->assertSame('acc-fake', $accounts[0]->id);
        $this->assertSame('EUR', $accounts[0]->currency);
    }

    public function test_accounts_can_be_fetched(): void
    {
        Http::fake([
            'https://api.moneta.cz/api/v4/vip/aisp/my/accounts*' => Http::response([
                'accounts' => [
                    [
                        'id' => 'acc-1',
                        'nameI18N' => 'Main account',
                        'currency' => 'CZK',
                        'identification' => ['iban' => 'CZ0000000000000000000001', 'other' => '1111'],
                        'ownersNames' => ['Test Corp'],
                        'servicer' => ['bankCode' => '0600', 'bic' => 'MONOCZPP', 'countryCode' => 'CZ'],
                    ],
                ],
                'pageNumber' => 0,
                'pageSize' => 10,
                'pageCount' => 1,
            ], 200),
        ]);

        $accounts = Moneta::accounts()->all();

        $this->assertCount(1, $accounts);
        $this->assertSame('acc-1', $accounts[0]->id);
        $this->assertSame('CZK', $accounts[0]->currency);
    }

    public function test_client_credentials_token_can_be_used_for_follow_up_calls(): void
    {
        Http::fake([
            'https://api.moneta.cz/auth/oauth/v2/token' => Http::response([
                'access_token' => 'oauth-token-123',
                'token_type' => 'Bearer',
                'expires_in' => 1200,
                'scope' => 'vip_sandbox',
            ], 200),
            'https://api.moneta.cz/api/v4/vip/aisp/my/accounts*' => Http::response([
                'accounts' => [
                    [
                        'id' => 'acc-oauth',
                        'nameI18N' => 'OAuth account',
                        'currency' => 'CZK',
                        'identification' => ['iban' => 'CZ0000000000000000000009', 'other' => '9999'],
                        'ownersNames' => ['OAuth Corp'],
                        'servicer' => ['bankCode' => '0600', 'bic' => 'AGBACZPP', 'countryCode' => 'CZE'],
                    ],
                ],
                'pageNumber' => 0,
                'pageSize' => 10,
                'pageCount' => 1,
            ], 200),
        ]);

        $accounts = Moneta::withContext([
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'oauth_scope' => 'vip_sandbox',
        ])->withClientCredentials()->accounts()->all();

        $this->assertCount(1, $accounts);
        $this->assertSame('acc-oauth', $accounts[0]->id);

        Http::assertSent(function ($request) {
            if ($request->url() === 'https://api.moneta.cz/auth/oauth/v2/token') {
                return $request['client_id'] === 'client-id'
                    && $request['client_secret'] === 'client-secret'
                    && $request['grant_type'] === 'client_credentials'
                    && $request['scope'] === 'vip_sandbox';
            }

            if (str_contains($request->url(), '/api/v4/vip/aisp/my/accounts')) {
                return $request->hasHeader('Authorization', 'Bearer oauth-token-123');
            }

            return false;
        });
    }

    public function test_client_credentials_use_sandbox_scope_when_scope_is_not_explicitly_set(): void
    {
        Http::fake([
            'https://api.moneta.cz/auth/oauth/v2/token' => Http::response([
                'access_token' => 'oauth-token-456',
                'token_type' => 'Bearer',
                'expires_in' => 1200,
                'scope' => 'vip_sandbox',
            ], 200),
            'https://api.moneta.cz/api/v4/vip/aisp/my/accounts*' => Http::response([
                'accounts' => [],
                'pageNumber' => 0,
                'pageSize' => 10,
                'pageCount' => 0,
            ], 200),
        ]);

        Moneta::withContext([
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'oauth_scope' => null,
            'sandbox' => true,
        ])->withClientCredentials()->accounts()->all();

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://api.moneta.cz/auth/oauth/v2/token') {
                return false;
            }

            return $request['scope'] === 'vip_sandbox';
        });
    }
}
