<?php

namespace FLAIRUK\BookingCom\Tests;

use FLAIRUK\BookingCom\BookingComServiceProvider;
use FLAIRUK\BookingCom\Facades\BookingCom;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected const BASE = 'https://demandapi.booking.com/3.2';

    protected function getPackageProviders($app): array
    {
        return [BookingComServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['BookingCom' => BookingCom::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('booking-com.token', 'test-token');
        $app['config']->set('booking-com.affiliate_id', '123456');
        $app['config']->set('booking-com.retry', [1, 0]);
    }

    protected function ok(mixed $data = [], array $metadata = []): PromiseInterface
    {
        return Http::response(array_filter([
            'request_id' => 'req-1',
            'data' => $data,
            'metadata' => $metadata,
        ], fn ($value) => $value !== []) + ['data' => $data]);
    }

    protected function fake(array $responses): void
    {
        Http::preventStrayRequests();
        Http::fake($responses);
    }

    protected function rebuild(array $config): void
    {
        foreach ($config as $key => $value) {
            config(["booking-com.{$key}" => $value]);
        }

        $this->app->forgetInstance(\FLAIRUK\BookingCom\BookingCom::class);
        BookingCom::clearResolvedInstances();
    }
}
