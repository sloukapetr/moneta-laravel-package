<?php

namespace SloukaPetr\Moneta\Tests\Feature;

use Illuminate\Support\Facades\Http;
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
}
