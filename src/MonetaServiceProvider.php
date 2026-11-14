<?php

namespace SloukaPetr\Moneta;

use Illuminate\Support\ServiceProvider;
use SloukaPetr\Moneta\Console\AccountsCommand;
use SloukaPetr\Moneta\Console\CheckCommand;

class MonetaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/moneta.php', 'moneta');

        $this->app->singleton(MonetaManager::class, function ($app) {
            return new MonetaManager($app['config']);
        });

        $this->app->alias(MonetaManager::class, 'moneta');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AccountsCommand::class,
                CheckCommand::class,
            ]);
        }

        $this->publishes([
            __DIR__.'/../config/moneta.php' => config_path('moneta.php'),
        ], 'moneta-config');
    }
}
