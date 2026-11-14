<?php

declare(strict_types=1);

namespace SloukaPetr\Moneta\Console;

use Illuminate\Console\Command;
use SloukaPetr\Moneta\MonetaManager;

final class CheckCommand extends Command
{
    protected $signature = 'moneta:check {--profile=} {--warn-days=14}';

    protected $description = 'Check the Moneta connection, account list and static token expiration.';

    public function handle(): int
    {
        $failed = false;

        try {
            $moneta = $this->moneta();
        } catch (\Throwable $exception) {
            $this->line('CHYBA '.$this->safeMessage($exception));

            return self::FAILURE;
        }

        $profile = $this->profileName();
        $mode = $this->configured('sandbox', true) ? 'sandbox' : 'produkce';
        $baseUrl = (string) $this->configured('base_url', 'https://api.moneta.cz');
        $this->line('OK profil '.$profile.', režim '.$mode.', '.$baseUrl);

        $tokenSet = $this->isSet('api_token');
        $credentialsSet = $this->isSet('client_id') && $this->isSet('client_secret');
        $authFailed = ! $tokenSet && ! $credentialsSet;
        $failed = $authFailed || $failed;
        $this->line(
            ($authFailed ? 'CHYBA' : 'OK')
            .' autentizace: statický token '.($tokenSet ? 'nastaven' : 'nenastaven')
            .', client credentials '.($credentialsSet ? 'nastaveny' : 'nenastaveny'),
        );

        $started = hrtime(true);

        try {
            $count = count($moneta->accounts()->all());
            $milliseconds = (int) round((hrtime(true) - $started) / 1_000_000);
            $this->line('OK účty: '.$count.', '.$milliseconds.' ms');
        } catch (\Throwable $exception) {
            $failed = true;
            $this->line('CHYBA účty: '.$this->safeMessage($exception));
        }

        if ($tokenSet) {
            $failed = $this->reportToken($moneta) || $failed;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function reportToken(MonetaManager $moneta): bool
    {
        try {
            $token = $moneta->token()->introspect();
        } catch (\Throwable $exception) {
            $this->line('CHYBA token: '.$this->safeMessage($exception));

            return true;
        }

        $days = $token->daysUntilExpiry();
        $date = $token->expiresAt?->format('Y-m-d') ?? 'neznámé';

        if ($days === null) {
            $this->line('CHYBA token: expirace chybí');

            return true;
        }

        $warnDays = (int) $this->option('warn-days');

        if ($days <= $warnDays) {
            $this->line('VAROVÁNÍ token vyprší '.$date.' (za '.$days.' dní)');

            return false;
        }

        $this->line('OK token vyprší '.$date.' (za '.$days.' dní)');

        return false;
    }

    private function moneta(): MonetaManager
    {
        /** @var MonetaManager $moneta */
        $moneta = $this->laravel->make(MonetaManager::class);
        $profile = $this->option('profile');

        if (is_string($profile) && $profile !== '') {
            return $moneta->profile($profile);
        }

        return $moneta;
    }

    private function profileName(): string
    {
        $profile = $this->option('profile');

        if (is_string($profile) && $profile !== '') {
            return $profile;
        }

        $name = config('moneta.default_profile', 'default');

        return is_string($name) && trim($name) !== '' ? trim($name) : 'default';
    }

    private function isSet(string $key): bool
    {
        $value = $this->configured($key);

        return is_string($value) && trim($value) !== '';
    }

    private function configured(string $key, mixed $default = null): mixed
    {
        $name = $this->profileName();

        if ($name !== 'default') {
            $profiles = config('moneta.profiles', []);
            $values = is_array($profiles) && is_array($profiles[$name] ?? null) ? $profiles[$name] : [];

            if (array_key_exists($key, $values)) {
                return $values[$key];
            }
        }

        return config('moneta.'.$key, $default);
    }

    private function safeMessage(\Throwable $exception): string
    {
        $message = $exception->getMessage();

        foreach (['api_token', 'client_secret', 'client_id'] as $key) {
            $value = $this->configured($key);

            if (is_string($value) && $value !== '') {
                $message = str_replace($value, '***', $message);
            }
        }

        return $message;
    }
}
