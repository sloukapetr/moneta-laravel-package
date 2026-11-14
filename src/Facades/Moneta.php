<?php

namespace SloukaPetr\Moneta\Facades;

use Illuminate\Support\Facades\Facade;
use SloukaPetr\Moneta\Fakes\MonetaFake;
use SloukaPetr\Moneta\MonetaManager;

/**
 * @method static MonetaManager profile(string $name)
 * @method static \SloukaPetr\Moneta\Services\TransactionService transactions()
 * @method static \SloukaPetr\Moneta\Services\TokenService token()
 * @method static \SloukaPetr\Moneta\Services\PaymentService payments()
 * @method static \SloukaPetr\Moneta\Services\StatementService statements()
 */
class Moneta extends Facade
{
    /**
     * @param  array<string, mixed>  $responses
     */
    public static function fake(array $responses = []): ?MonetaFake
    {
        /** @var MonetaManager $manager */
        $manager = static::getFacadeRoot();
        $fake = $manager->fake($responses);

        if ($fake instanceof MonetaFake) {
            static::swap($fake);
        }

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return MonetaManager::class;
    }
}
