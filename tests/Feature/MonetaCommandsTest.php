<?php

namespace SloukaPetr\Moneta\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use SloukaPetr\Moneta\Facades\Moneta;
use SloukaPetr\Moneta\Tests\TestCase;

class MonetaCommandsTest extends TestCase
{
    public function test_accounts_command_prints_the_fake_account_and_json(): void
    {
        $fake = Moneta::fake();
        $fake->withAccount('acc-1', [
            'nameI18N' => 'Provoz',
            'currency' => 'CZK',
            'identification' => [
                'iban' => 'CZ650600000000123456789',
                'other' => '123456789/0600',
            ],
        ]);
        $fake->withBalance('acc-1', 150000, -140000);

        $this->assertSame(0, Artisan::call('moneta:accounts'));
        $output = Artisan::output();

        $this->assertStringContainsString('acc-1', $output);
        $this->assertStringContainsString('123456789/0600', $output);
        $this->assertStringContainsString('CZ650600000000123456789', $output);
        $this->assertStringContainsString('1500.00', $output);
        $this->assertStringContainsString('-1400.00', $output);

        $this->assertSame(0, Artisan::call('moneta:accounts', ['--json' => true]));

        $decoded = json_decode(Artisan::output(), true);

        $this->assertIsArray($decoded);
        $this->assertSame('acc-1', $decoded[0]['id']);
        $this->assertSame('123456789/0600', $decoded[0]['accountNumber']);
        $this->assertSame('1500.00', $decoded[0]['booked']);
        $this->assertSame('-1400.00', $decoded[0]['available']);
    }

    public function test_check_warns_before_expiry_and_fails_on_unauthorized(): void
    {
        $fake = Moneta::fake();
        $fake->withAccount('acc-1');
        $fake->withTokenExpiration(CarbonImmutable::now()->addDays(5));

        $this->artisan('moneta:check')
            ->expectsOutputToContain('VAROVÁNÍ')
            ->assertSuccessful();

        Moneta::fake()->failWith(401);

        $this->artisan('moneta:check')
            ->expectsOutputToContain('CHYBA')
            ->assertExitCode(1);
    }

    public function test_command_output_does_not_contain_configured_secrets(): void
    {
        config([
            'moneta.api_token' => 'token-must-not-leak',
            'moneta.client_id' => 'client-id-must-not-leak',
            'moneta.client_secret' => 'secret-must-not-leak',
        ]);

        $fake = Moneta::fake();
        $fake->withAccount('acc-1');
        $fake->withTokenExpiration(CarbonImmutable::now()->addDays(40));

        $this->assertSame(0, Artisan::call('moneta:accounts', ['--json' => true]));
        $output = Artisan::output();

        $this->assertSame(0, Artisan::call('moneta:check'));
        $output .= Artisan::output();

        $this->assertStringNotContainsString('token-must-not-leak', $output);
        $this->assertStringNotContainsString('secret-must-not-leak', $output);
        $this->assertStringNotContainsString('client-id-must-not-leak', $output);
    }
}
