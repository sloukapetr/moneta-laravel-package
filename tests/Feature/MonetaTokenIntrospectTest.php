<?php

namespace SloukaPetr\Moneta\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use SloukaPetr\Moneta\Exceptions\ApiException;
use SloukaPetr\Moneta\Facades\Moneta;
use SloukaPetr\Moneta\Tests\TestCase;

class MonetaTokenIntrospectTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_introspect_returns_the_token_expiration(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-01T08:00:00+01:00'));

        Http::fake([
            'https://api.moneta.cz/api/v1/vip/token/introspect' => Http::response([
                'expiration' => '2026-03-06T11:38:09+01:00',
            ], 200),
        ]);

        $token = Moneta::token()->introspect();

        $this->assertNotNull($token->expiresAt);
        $this->assertTrue($token->expiresAt->equalTo(CarbonImmutable::parse('2026-03-06T11:38:09+01:00')));
        $this->assertSame('+01:00', $token->expiresAt->format('P'));
        $this->assertSame(5, $token->daysUntilExpiry());
    }

    public function test_introspect_403_means_the_token_is_not_a_moneta_api_token(): void
    {
        Http::fake([
            'https://api.moneta.cz/api/v1/vip/token/introspect' => Http::response([
                'errors' => [['error' => 'FORBIDDEN', 'message' => 'Forbidden']],
            ], 403),
        ]);

        try {
            Moneta::token()->introspect();
            $this->fail('ApiException was not thrown.');
        } catch (ApiException $exception) {
            $this->assertSame(403, $exception->statusCode());
            $this->assertSame('The token is not a Moneta API token.', $exception->getMessage());
            $this->assertStringNotContainsString('test-token', $exception->getMessage());
            $this->assertStringNotContainsString('test-token', (string) json_encode($exception->payload));
        }
    }
}
