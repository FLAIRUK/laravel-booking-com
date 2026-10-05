<?php

namespace FLAIRUK\BookingCom;

use DateTimeInterface;
use FLAIRUK\BookingCom\Exceptions\AuthenticationException;
use FLAIRUK\BookingCom\Exceptions\BookingComException;
use FLAIRUK\BookingCom\Exceptions\RateLimitException;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response as HttpResponse;

/**
 * Booking.com Demand API client.
 *
 * @see https://developers.booking.com/demand/docs
 */
class BookingCom
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected Http $http,
        protected ?Cache $cache,
        protected array $config,
    ) {}

    // ---------------------------------------------------------------------
    // Resources
    // ---------------------------------------------------------------------

    public function accommodations(): Resources\Accommodations
    {
        return new Resources\Accommodations($this);
    }

    public function cars(): Resources\Cars
    {
        return new Resources\Cars($this);
    }

    public function common(): Resources\Common
    {
        return new Resources\Common($this);
    }

    public function locations(): Resources\Locations
    {
        return new Resources\Locations($this);
    }

    public function messages(): Resources\Messages
    {
        return new Resources\Messages($this);
    }

    public function orders(): Resources\Orders
    {
        return new Resources\Orders($this);
    }

    // ---------------------------------------------------------------------
    // Scoping
    // ---------------------------------------------------------------------

    /**
     * A copy of the client that sends requests as another API user.
     */
    public function forAffiliate(string|int $affiliateId, ?string $token = null): static
    {
        return $this->withConfig(array_filter(['affiliate_id' => (string) $affiliateId, 'token' => $token]));
    }

    /**
     * A copy of the client pointed at the sandbox (or, with false, production).
     */
    public function sandbox(bool $sandbox = true): static
    {
        return $this->withConfig(['sandbox' => $sandbox, 'base_url' => null]);
    }

    /**
     * A copy of the client using another API version, e.g. "3.1".
     */
    public function version(string $version): static
    {
        return $this->withConfig(['version' => $version, 'base_url' => null]);
    }

    public function baseUrl(): string
    {
        if (filled($this->config['base_url'] ?? null)) {
            return rtrim($this->config['base_url'], '/');
        }

        $host = ($this->config['sandbox'] ?? false) ? 'demandapi-sandbox.booking.com' : 'demandapi.booking.com';

        return "https://{$host}/".($this->config['version'] ?? '3.2');
    }

    public function isSandbox(): bool
    {
        return str_contains($this->baseUrl(), 'demandapi-sandbox.');
    }

    /**
     * @return array{booker: array{country: ?string, platform: ?string}, currency: ?string}
     */
    public function defaults(): array
    {
        return array_replace_recursive(
            ['booker' => ['country' => null, 'platform' => null], 'currency' => null],
            (array) ($this->config['defaults'] ?? []),
        );
    }

    // ---------------------------------------------------------------------
    // Requests
    // ---------------------------------------------------------------------

    /**
     * POST to an endpoint and wrap the result. Every Demand API endpoint is a POST.
     *
     * @param  bool  $retry  Whether connection errors and 5xx responses may be retried.
     *                       Pass false for anything that changes state.
     *
     * @throws BookingComException
     */
    public function post(string $path, array $body = [], bool $retry = true): Response
    {
        $path = ltrim($path, '/');
        $body = $this->normalise($body);

        $response = $this->prepare($retry)->post($path, $body === [] ? new \stdClass : $body);

        $this->throwIfFailed($response);

        return new Response(
            (array) $response->json(),
            fn (string $page) => $this->post($path, ['page' => $page] + array_intersect_key($body, ['extras' => true]), $retry),
        );
    }

    /**
     * Like post(), but cached for reference data that rarely changes.
     */
    public function remember(string $path, array $body = []): Response
    {
        $ttl = $this->config['cache']['ttl'] ?? null;

        if ($this->cache === null || $ttl === null) {
            return $this->post($path, $body);
        }

        $key = 'booking-com:'.sha1($this->baseUrl().'|'.($this->config['affiliate_id'] ?? '').'|'.$path.'|'.json_encode($this->normalise($body)));

        $cached = $this->cache->get($key);

        if (is_array($cached)) {
            return new Response($cached);
        }

        $response = $this->post($path, $body);
        $this->cache->put($key, $response->toArray(), $ttl);

        return $response;
    }

    /**
     * An authenticated request builder, for anything this client doesn't wrap.
     *
     * @throws AuthenticationException
     */
    public function request(): PendingRequest
    {
        foreach (['token' => 'BOOKING_COM_TOKEN', 'affiliate_id' => 'BOOKING_COM_AFFILIATE_ID'] as $key => $env) {
            if (blank($this->config[$key] ?? null)) {
                throw new AuthenticationException("Booking.com {$key} is not configured. Set {$env} in your .env file.");
            }
        }

        return $this->http->baseUrl($this->baseUrl())
            ->acceptJson()
            ->asJson()
            ->timeout((int) ($this->config['timeout'] ?? 30))
            ->withToken((string) $this->config['token'])
            ->withHeaders(['X-Affiliate-Id' => (string) $this->config['affiliate_id']]);
    }

    protected function prepare(bool $retry): PendingRequest
    {
        if (! $retry) {
            return $this->request();
        }

        [$times, $sleep] = $this->config['retry'] ?? [2, 500];

        return $this->request()->retry($times, $sleep, fn (\Throwable $e) => $e instanceof ConnectionException
            || ($e instanceof RequestException && $e->response->serverError()), throw: false);
    }

    protected function throwIfFailed(HttpResponse $response): void
    {
        if ($response->successful() && ! is_array($response->json('errors'))) {
            return;
        }

        throw match (true) {
            $response->status() === 429 => RateLimitException::fromResponse($response),
            in_array($response->status(), [401, 403], true) => AuthenticationException::fromResponse($response),
            default => BookingComException::fromResponse($response),
        };
    }

    /**
     * Format dates as the API expects (YYYY-MM-DD) and drop null values.
     */
    protected function normalise(array $body): array
    {
        $normalised = [];

        foreach ($body as $key => $value) {
            if ($value === null) {
                continue;
            }

            $normalised[$key] = match (true) {
                $value instanceof DateTimeInterface => $value->format('Y-m-d'),
                is_array($value) => $this->normalise($value),
                default => $value,
            };
        }

        return $normalised;
    }

    protected function withConfig(array $overrides): static
    {
        $clone = clone $this;
        $clone->config = array_replace($this->config, $overrides);

        return $clone;
    }
}
