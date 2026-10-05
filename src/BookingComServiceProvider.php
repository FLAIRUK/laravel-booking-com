<?php

namespace FLAIRUK\BookingCom;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\ServiceProvider;

class BookingComServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/booking-com.php', 'booking-com');

        $this->app->singleton(BookingCom::class, fn (Application $app) => new BookingCom(
            $app->make(Http::class),
            $app['cache']->store($app['config']->get('booking-com.cache.store')),
            $app['config']->get('booking-com'),
        ));

        $this->app->alias(BookingCom::class, 'booking-com');
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/booking-com.php' => config_path('booking-com.php'),
        ], 'booking-com-config');

        $this->commands([
            Console\InstallCommand::class,
            Console\StatusCommand::class,
        ]);
    }
}
