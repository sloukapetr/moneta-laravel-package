<?php

namespace SloukaPetr\Moneta\Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use SloukaPetr\Moneta\Exceptions\ApiException;
use SloukaPetr\Moneta\Exceptions\NetworkException;
use SloukaPetr\Moneta\Exceptions\NotFoundException;
use SloukaPetr\Moneta\Exceptions\RateLimitException;
use SloukaPetr\Moneta\Exceptions\UnauthorizedException;
use SloukaPetr\Moneta\Exceptions\ValidationException;
use SloukaPetr\Moneta\Facades\Moneta;
use SloukaPetr\Moneta\Tests\TestCase;

class MonetaExceptionMappingTest extends TestCase
{
    public function test_401_is_mapped_to_unauthorized_exception(): void
    {
        Http::fake([
            '*' => Http::response(['errors' => [['error' => 'UNAUTHORIZED', 'message' => 'Unauthorized']]], 401),
        ]);

        $this->expectException(UnauthorizedException::class);

        Moneta::accounts()->all();
    }

    public function test_400_is_mapped_to_validation_exception(): void
    {
        Http::fake([
            '*' => Http::response(['errors' => [['error' => 'PARAMETER_INVALID', 'message' => 'Invalid parameter']]], 400),
        ]);

        $this->expectException(ValidationException::class);

        Moneta::accounts()->all();
    }

    public function test_404_is_mapped_to_not_found_exception(): void
    {
        Http::fake([
            '*' => Http::response(['errors' => [['error' => 'ID_NOT_FOUND', 'message' => 'Not found']]], 404),
        ]);

        $this->expectException(NotFoundException::class);

        Moneta::accounts()->all();
    }

    public function test_429_is_mapped_to_rate_limit_exception(): void
    {
        Http::fake([
            '*' => Http::response(['errors' => [['error' => 'RATE_LIMIT', 'message' => 'Too many requests']]], 429),
        ]);

        $this->expectException(RateLimitException::class);

        Moneta::accounts()->all();
    }

    public function test_client_errors_are_not_retried(): void
    {
        config(['moneta.retry' => 3, 'moneta.retry_sleep' => 0]);

        Http::fake([
            '*' => Http::response(['errors' => [['error' => 'PARAMETER_INVALID', 'message' => 'Invalid parameter']]], 400),
        ]);

        try {
            Moneta::accounts()->all();
            $this->fail('ValidationException was not thrown.');
        } catch (ValidationException) {
            $this->assertCount(1, Http::recorded());
        }
    }

    public function test_server_errors_are_retried_and_mapped_to_api_exception(): void
    {
        config(['moneta.retry' => 2, 'moneta.retry_sleep' => 0]);

        Http::fake([
            '*' => Http::response(['message' => 'unavailable'], 503),
        ]);

        try {
            Moneta::accounts()->all();
            $this->fail('ApiException was not thrown.');
        } catch (ApiException $exception) {
            $this->assertSame(503, $exception->statusCode());
            $this->assertNotInstanceOf(RateLimitException::class, $exception);
            $this->assertCount(2, Http::recorded());
        }
    }

    public function test_connection_errors_are_retried(): void
    {
        config(['moneta.retry' => 2, 'moneta.retry_sleep' => 0]);

        Http::fake([
            '*' => Http::failedConnection(),
        ]);

        try {
            Moneta::accounts()->all();
            $this->fail('NetworkException was not thrown.');
        } catch (NetworkException $exception) {
            $this->assertInstanceOf(ConnectionException::class, $exception->getPrevious());
            $this->assertCount(2, Http::recorded());
        }
    }
}
