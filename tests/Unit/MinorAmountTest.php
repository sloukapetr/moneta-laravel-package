<?php

namespace SloukaPetr\Moneta\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SloukaPetr\Moneta\Support\MinorAmount;

class MinorAmountTest extends TestCase
{
    public function test_from_api_converts_currency_units_to_halere(): void
    {
        $this->assertSame(10, MinorAmount::fromApi(0.1));
        $this->assertSame(123450, MinorAmount::fromApi(1234.5));
        $this->assertSame(123456, MinorAmount::fromApi(1234.56));
        $this->assertSame(-1000, MinorAmount::fromApi(-10));
        $this->assertSame(9999, MinorAmount::fromApi('99.99'));
        $this->assertSame(10000, MinorAmount::fromApi(100));
    }

    public function test_from_api_rejects_more_than_two_decimal_places(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MinorAmount::fromApi('1.234');
    }
}
