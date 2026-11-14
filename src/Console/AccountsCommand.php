<?php

declare(strict_types=1);

namespace SloukaPetr\Moneta\Console;

use Illuminate\Console\Command;
use SloukaPetr\Moneta\Data\BalanceData;
use SloukaPetr\Moneta\Enums\BalanceType;
use SloukaPetr\Moneta\MonetaManager;
use SloukaPetr\Moneta\Support\MinorAmount;

final class AccountsCommand extends Command
{
    protected $signature = 'moneta:accounts {--profile=} {--json}';

    protected $description = 'List Moneta accounts with booked and available balances.';

    public function handle(): int
    {
        try {
            $rows = $this->rows($this->moneta());
        } catch (\Throwable $exception) {
            $this->line('CHYBA '.$this->safeMessage($exception));

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode(
                $rows,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ));

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Číslo účtu', 'IBAN', 'Měna', 'Název', 'Disponibilní', 'Účetní'],
            array_map(fn (array $row): array => [
                $row['id'],
                $row['accountNumber'] ?? '',
                $row['iban'],
                $row['currency'],
                $row['name'],
                $row['available'] ?? '',
                $row['booked'] ?? '',
            ], $rows),
        );

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{id: string, accountNumber: ?string, iban: string, currency: string, name: string, available: ?string, booked: ?string}>
     */
    private function rows(MonetaManager $moneta): array
    {
        $rows = [];

        foreach ($moneta->accounts()->all() as $account) {
            $balances = $moneta->accounts()->getBalances($account->id);

            $rows[] = [
                'id' => $account->id,
                'accountNumber' => $account->accountNumber,
                'iban' => $account->iban,
                'currency' => $account->currency,
                'name' => $account->name,
                'available' => $this->amount($balances, BalanceType::Available),
                'booked' => $this->amount($balances, BalanceType::Booked),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, BalanceData>  $balances
     */
    private function amount(array $balances, BalanceType $type): ?string
    {
        foreach ($balances as $balance) {
            if ($balance->balanceTypeEnum === $type) {
                return MinorAmount::toApi($balance->signedAmountMinor);
            }
        }

        return null;
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

    private function configured(string $key): mixed
    {
        $profile = $this->option('profile');
        $name = is_string($profile) && $profile !== '' ? $profile : config('moneta.default_profile', 'default');
        $name = is_string($name) && trim($name) !== '' ? trim($name) : 'default';

        if ($name !== 'default') {
            $profiles = config('moneta.profiles', []);
            $values = is_array($profiles) && is_array($profiles[$name] ?? null) ? $profiles[$name] : [];

            if (array_key_exists($key, $values)) {
                return $values[$key];
            }
        }

        return config('moneta.'.$key);
    }
}
