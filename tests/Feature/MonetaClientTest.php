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
}
