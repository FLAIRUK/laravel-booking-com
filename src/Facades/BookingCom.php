<?php

namespace FLAIRUK\BookingCom\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \FLAIRUK\BookingCom\Resources\Accommodations accommodations()
 * @method static \FLAIRUK\BookingCom\Resources\Cars cars()
 * @method static \FLAIRUK\BookingCom\Resources\Common common()
 * @method static \FLAIRUK\BookingCom\Resources\Locations locations()
 * @method static \FLAIRUK\BookingCom\Resources\Messages messages()
 * @method static \FLAIRUK\BookingCom\Resources\Orders orders()
 * @method static \FLAIRUK\BookingCom\BookingCom forAffiliate(string|int $affiliateId, ?string $token = null)
 * @method static \FLAIRUK\BookingCom\BookingCom sandbox(bool $sandbox = true)
 * @method static \FLAIRUK\BookingCom\BookingCom version(string $version)
 * @method static string baseUrl()
 * @method static bool isSandbox()
 * @method static array defaults()
 * @method static \FLAIRUK\BookingCom\Response post(string $path, array $body = [], bool $retry = true)
 * @method static \FLAIRUK\BookingCom\Response remember(string $path, array $body = [])
 * @method static \Illuminate\Http\Client\PendingRequest request()
 *
 * @see \FLAIRUK\BookingCom\BookingCom
 */
class BookingCom extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \FLAIRUK\BookingCom\BookingCom::class;
    }
}
