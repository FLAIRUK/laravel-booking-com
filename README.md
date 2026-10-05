<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="art/logo-dark.svg">
    <img src="art/logo-light.svg" alt="Laravel Booking.com" width="420">
  </picture>
</p>

<h2 align="center">
  <a href="https://www.php.net/" target="_blank"><img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat&logo=php&logoColor=white" alt="PHP 8.2+"></a>&nbsp;
  <a href="https://laravel.com/docs/" target="_blank"><img src="https://img.shields.io/badge/Laravel-12%20%7C%2013-FF2D20?style=flat&logo=laravel&logoColor=white" alt="Laravel 12 or 13"></a>&nbsp;
  <a href="https://github.com/FLAIRUK/laravel-booking-com/actions/workflows/tests.yml" target="_blank"><img src="https://img.shields.io/badge/Lint-%E2%9C%93-2EA043?style=flat&logo=githubactions&logoColor=white" alt="Lint"></a>&nbsp;
  <a href="https://github.com/FLAIRUK/laravel-booking-com/actions/workflows/tests.yml" target="_blank"><img src="https://img.shields.io/badge/Tests-%E2%9C%93-2EA043?style=flat&logo=githubactions&logoColor=white" alt="Tests"></a>&nbsp;
  <a href="https://packagist.org/packages/flairuk/laravel-booking-com" target="_blank"><img src="https://img.shields.io/packagist/dt/flairuk/laravel-booking-com?style=flat&logo=packagist&logoColor=white&label=Downloads&color=F28D1A" alt="Downloads on Packagist"></a>&nbsp;
  <a href="https://github.com/FLAIRUK/laravel-booking-com/blob/master/LICENSE" target="_blank"><img src="https://img.shields.io/github/license/FLAIRUK/laravel-booking-com?style=flat&label=License&color=3DA639" alt="MIT licence"></a>&nbsp;
  <a href="https://developers.booking.com/demand/docs" target="_blank"><img src="https://img.shields.io/badge/Demand%20API-v3.2-E11D48?style=flat" alt="Demand API v3.2"></a>&nbsp;
  <br>&nbsp;
</h2>

**Laravel Booking.com** — A Laravel 12 and 13 client for the [Booking.com Demand API](https://developers.booking.com/demand/docs), for affiliates and travel sites that search, show and book Booking.com stays and car rentals.

- **Every stable endpoint.** Covers accommodation search, availability, content, reviews and changes; locations; orders (preview, create, modify, cancel); car rentals; and messaging.
- **Pagination built in.** Any paginated result can be walked with `->lazy()`, which fetches the next page only when you reach it.
- **Rate-limit friendly.** Reference data is cached. A `429` raises a `RateLimitException` that tells you how long to wait.
- **Safe retries.** Reads are retried on connection errors and 5xx responses. Order creation, changes and cancellations never are, so nothing is booked or cancelled twice.
- **Real errors.** Failures throw exceptions carrying Booking.com's error ids and the `request_id` to quote to their support.

> This is an unofficial package. It is not affiliated with or endorsed by Booking.com. You need your own Booking.com affiliate partner account and API key.

<p align="center">
  📦&nbsp;<a href="#-installation">Installation</a> ·
  🚀&nbsp;<a href="#-usage">Usage</a> ·
  🛏️&nbsp;<a href="#-booking-flow">Booking flow</a> ·
  ⚠️&nbsp;<a href="#%EF%B8%8F-errors-and-rate-limits">Errors</a> ·
  ⚙️&nbsp;<a href="#%EF%B8%8F-configuration">Configuration</a>
</p>

<br><br>

## 📦 Installation

```bash
composer require flairuk/laravel-booking-com
php artisan booking-com:install
```

`booking-com:install` publishes `config/booking-com.php` and adds these keys to `.env` and `.env.example`:

```dotenv
BOOKING_COM_TOKEN=            # API key from the Affiliate Partner Centre
BOOKING_COM_AFFILIATE_ID=     # affiliate ID of the API user
BOOKING_COM_SANDBOX=true      # use the sandbox until you go live
BOOKING_COM_BOOKER_COUNTRY=   # optional default booker country, e.g. gb
BOOKING_COM_CURRENCY=         # optional default currency, e.g. GBP
```

Then check the connection:

```bash
php artisan booking-com:status
```

The sandbox uses your normal credentials, with test properties such as `10507360` (Demand API Sandbox Hotel Orion, Amsterdam). Car rentals aren't available in the sandbox.

<br><br>

## 🚀 Usage

```php
use FLAIRUK\BookingCom\Facades\BookingCom;
```

You can also type-hint `FLAIRUK\BookingCom\BookingCom` to have it injected.

Request bodies are plain arrays in the shape the [API reference](https://developers.booking.com/demand/docs/open-api/3.2/demand-api) documents. Dates can be given as `DateTimeInterface` objects. Every call returns a `Response`:

```php
$response->data();         // the "data" array
$response->metadata();     // the "metadata" array
$response->requestId();    // quote this to Booking.com support
$response->total();        // metadata.total_results
$response->toArray();      // the whole body

foreach ($response as $item) { /* items in data */ }
count($response);
$response[0]['id'];
```

### Searching for stays

```php
$results = BookingCom::accommodations()->search([
    'city' => -2140479,                       // Amsterdam: see Locations below
    'checkin' => now()->addMonth(),
    'checkout' => now()->addMonth()->addDays(2),
    'guests' => ['number_of_adults' => 2, 'number_of_rooms' => 1],
    'booker' => ['country' => 'gb', 'platform' => 'desktop'],
    'currency' => 'GBP',
    'extras' => ['extra_charges', 'products'],
    'rows' => 20,
]);

$results->total();                           // e.g. 122

foreach ($results->lazy() as $property) {    // walks every page on demand
    // ...
}
```

`booker` and `currency` fall back to `BOOKING_COM_BOOKER_COUNTRY`, `BOOKING_COM_BOOKER_PLATFORM` and `BOOKING_COM_CURRENCY`. The booker's country decides the prices and how taxes are shown, so pass each visitor's real country when you know it.

You can also search by `country`, `region`, `district`, `landmark`, `airport`, `coordinates` (latitude, longitude and radius) or a list of `accommodations`.

### Availability, content and reviews

```php
BookingCom::accommodations()->availability([
    'accommodations' => [10507360],
    'checkin' => '2026-11-01',
    'checkout' => '2026-11-03',
    'guests' => ['number_of_adults' => 2, 'number_of_rooms' => 1],
]);

BookingCom::accommodations()->find(10507360, extras: ['description', 'facilities', 'photos', 'policies', 'rooms']);
BookingCom::accommodations()->details(['city' => -2140479, 'rows' => 100])->lazy();

BookingCom::accommodations()->reviews(['accommodations' => [10507360], 'languages' => ['en-gb']]);
BookingCom::accommodations()->reviewScores(['accommodations' => [10507360]]);

BookingCom::accommodations()->constants();   // facility, room type, meal plan ids… (cached)
BookingCom::accommodations()->chains();      // hotel chains and brands (cached)
```

### Keeping stored content up to date

Store property content and refresh only what changed:

```php
foreach (BookingCom::accommodations()->changesSince($lastSync, ['countries' => ['gb']]) as $batch) {
    $batch['changed'];   // ids to re-fetch with find()
    $batch['opened'];    // new properties
    $batch['closed'];    // properties to hide
    $lastSync = $batch['next'] ?? $batch['from'];
}
```

### Locations

Accommodation searches use Booking.com's own location ids:

```php
BookingCom::locations()->cities(['country' => 'nl', 'languages' => ['en-gb']])->lazy();
BookingCom::locations()->countries();
BookingCom::locations()->regions(['country' => 'gb']);
BookingCom::locations()->districts(['city' => -2140479]);
BookingCom::locations()->landmarks(['city' => -2140479]);
BookingCom::locations()->airports(['country' => 'gb']);

BookingCom::common()->languages();    // cached
BookingCom::common()->currencies();   // cached
BookingCom::common()->cards();        // cached
```

### Car rentals

```php
BookingCom::cars()->search([
    'route' => [
        'pickup' => ['datetime' => '2026-11-01T10:00:00', 'location' => ['airport' => 'AMS']],
        'dropoff' => ['datetime' => '2026-11-03T10:00:00', 'location' => ['airport' => 'AMS']],
    ],
    'driver' => ['age' => 35],
    'currency' => 'EUR',
]);

BookingCom::cars()->details();
BookingCom::cars()->depots();
BookingCom::cars()->suppliers();   // cached
BookingCom::cars()->constants();   // cached
```

### Anything else

For endpoints without a wrapper, such as Beta endpoints, call them directly. Authentication and error handling still apply:

```php
BookingCom::post('attractions/search', ['city' => -2140479]);
BookingCom::post('orders/modify/preview', $body, retry: false);   // pass retry: false for anything that changes state
```

<br><br>

## 🛏️ Booking flow

Preview the order to get the final price, policies and an `order_token`. Then create the order with that token:

```php
$preview = BookingCom::orders()->preview([
    'accommodation' => [
        'id' => 10507360,
        'checkin' => '2026-11-01',
        'checkout' => '2026-11-03',
        'products' => [['id' => $productId, 'allocation' => ['number_of_adults' => 2]]],
    ],
    'currency' => 'GBP',
]);

$order = BookingCom::orders()->create([
    'order_token' => $preview->data()['order_token'],
    'booker' => [
        'name' => ['first_name' => 'Jane', 'last_name' => 'Doe'],
        'email' => 'jane@example.com',
        'telephone' => '+441234567890',
        'address' => ['address_line' => '1 High Street', 'city' => 'London', 'country' => 'gb', 'post_code' => 'SW1A 1AA'],
    ],
    'payment' => ['timing' => 'pay_at_the_property'],
]);
```

After booking:

```php
BookingCom::orders()->find($orderId);
BookingCom::orders()->details(['created' => ['from' => '2026-10-01', 'to' => '2026-10-07']])->lazy();   // max 7-day range
BookingCom::orders()->accommodations(['orders' => [$orderId]]);
BookingCom::orders()->modify(['order' => $orderId, 'modification' => [/* ... */]]);
BookingCom::orders()->cancelAccommodation($reservationId, 'Guest changed plans');
```

`create()`, `modify()` and `cancel()` are never retried automatically. If one of them times out, check with `orders()->find()` before trying again.

> **Card data:** if your integration takes card payments, card details pass straight through to Booking.com in the `create()` payload. Make sure your own logging, error reporting and queues never record that payload, and check your PCI DSS obligations.

### Messaging

Guest–property messaging is available in API version 3.1, and in 3.2 for partners with Beta access:

```php
$messages = BookingCom::version('3.1')->messages();

$messages->latest();
$messages->confirm(['message-id']);
$messages->conversation(['accommodation' => 10507360, 'reservation' => $reservationId]);
$messages->send(['accommodation' => 10507360, 'conversation' => $conversationId, 'content' => 'What time is check-in?']);
```

<br><br>

## ⚠️ Errors and rate limits

```php
use FLAIRUK\BookingCom\Exceptions\AuthenticationException;
use FLAIRUK\BookingCom\Exceptions\BookingComException;
use FLAIRUK\BookingCom\Exceptions\RateLimitException;

try {
    BookingCom::accommodations()->search($query);
} catch (RateLimitException $e) {
    // per-minute quota exceeded: wait $e->retryAfter() seconds (usually 60)
} catch (AuthenticationException $e) {
    // missing or rejected API key / affiliate ID
} catch (BookingComException $e) {
    $e->errorIds();        // e.g. ['invalid_request']
    $e->errors;            // [['id' => ..., 'message' => ...]]
    $e->requestId;         // quote this to Booking.com support
    $e->response?->json(); // the full response
}
```

In queued jobs, release the job on a rate limit:

```php
catch (RateLimitException $e) {
    $this->release($e->retryAfter());
}
```

Your account's rate limit is set by Booking.com; your Account Manager can tell you what it is.

<br><br>

## ⚙️ Configuration

| Key | Env | Default |
| --- | --- | --- |
| `token` | `BOOKING_COM_TOKEN` | |
| `affiliate_id` | `BOOKING_COM_AFFILIATE_ID` | |
| `sandbox` | `BOOKING_COM_SANDBOX` | `false` |
| `version` | `BOOKING_COM_API_VERSION` | `3.2` |
| `base_url` | `BOOKING_COM_BASE_URL` | built from `sandbox` and `version` |
| `defaults.booker.country` | `BOOKING_COM_BOOKER_COUNTRY` | |
| `defaults.booker.platform` | `BOOKING_COM_BOOKER_PLATFORM` | `desktop` |
| `defaults.currency` | `BOOKING_COM_CURRENCY` | |
| `timeout` | `BOOKING_COM_TIMEOUT` | `30` seconds |
| `retry` | | 2 retries, 500 ms apart (reads only) |
| `cache.store` | `BOOKING_COM_CACHE_STORE` | the default cache store |
| `cache.ttl` | | 24 hours; `null` turns caching off |

Scope a single call differently without changing the defaults:

```php
BookingCom::sandbox()->accommodations()->search($query);
BookingCom::version('3.1')->accommodations()->bulkAvailability($query);
BookingCom::forAffiliate($otherAffiliateId, $otherToken)->orders()->find($orderId);
```

<br><br>

## 🧪 Testing

```bash
composer test
```

The client uses Laravel's HTTP client, so `Http::fake()` works in your own tests:

```php
Http::fake([
    'demandapi*.booking.com/*/accommodations/search' => Http::response(['request_id' => 'test', 'data' => []]),
]);
```

<br><br>

## 📄 License

The MIT License (MIT). See [LICENSE](LICENSE) for details.

Booking.com is a trademark of Booking.com B.V. This package is not affiliated with or endorsed by Booking.com.
