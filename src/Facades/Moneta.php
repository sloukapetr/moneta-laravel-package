<?php

namespace SloukaPetr\Moneta\Facades;

use Illuminate\Support\Facades\Facade;
use SloukaPetr\Moneta\MonetaManager;

class Moneta extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MonetaManager::class;
    }
}
