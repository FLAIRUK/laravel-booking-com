<?php

namespace FLAIRUK\BookingCom\Tests;

use FLAIRUK\BookingCom\Exceptions\AuthenticationException;
use FLAIRUK\BookingCom\Exceptions\BookingComException;
use FLAIRUK\BookingCom\Exceptions\RateLimitException;
use FLAIRUK\BookingCom\Facades\BookingCom;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class ClientTest extends TestCase
{
    #[Test]
    public function it_authenticates_every_request_with_the_token_and_affiliate_id(): void
    {
        $this->fake([self::BASE.'/common/locations/countries' => $this->ok([['id' => 'nl']])]);

        BookingCom::locations()->countries();

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === self::BASE.'/common/locations/countries'
            && $r->hasHeader('Authorization', 'Bearer test-token')
            && $r->hasHeader('X-Affiliate-Id', '123456')
            && $r->body() === '{}');
    }

    #[Test]
    public function the_base_url_follows_the_sandbox_and_version_settings(): void
    {
        $this->assertSame('https://demandapi.booking.com/3.2', BookingCom::baseUrl());
        $this->assertSame('https://demandapi-sandbox.booking.com/3.2', BookingCom::sandbox()->baseUrl());
        $this->assertSame('https://demandapi.booking.com/3.1', BookingCom::version('3.1')->baseUrl());
        $this->assertTrue(BookingCom::sandbox()->isSandbox());
        $this->assertFalse(BookingCom::isSandbox());

        $this->rebuild(['sandbox' => true, 'version' => '3.1']);
        $this->assertSame('https://demandapi-sandbox.booking.com/3.1', BookingCom::baseUrl());

        $this->rebuild(['base_url' => 'https://proxy.test/booking/']);
        $this->assertSame('https://proxy.test/booking', BookingCom::baseUrl());
    }

    #[Test]
    public function for_affiliate_switches_the_api_user_without_changing_the_default(): void
    {
        $this->fake(['*' => $this->ok()]);

        BookingCom::forAffiliate(999, 'other-token')->locations()->countries();
        BookingCom::locations()->countries();

        $recorded = Http::recorded()->map(fn (array $pair) => $pair[0]->header('X-Affiliate-Id')[0])->values()->all();
        $this->assertSame(['999', '123456'], $recorded);
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer other-token'));
    }

    #[Test]
    public function missing_credentials_fail_before_any_request(): void
    {
        $this->rebuild(['token' => null]);
        Http::preventStrayRequests();

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('BOOKING_COM_TOKEN');

        BookingCom::locations()->countries();
    }

    #[Test]
    public function api_errors_carry_the_error_ids_and_request_id(): void
    {
        $this->fake([self::BASE.'/orders/preview' => Http::response([
            'request_id' => '01kjan7r7yvff5yg95gxy1cjhy',
            'errors' => [['id' => 'invalid_request', 'message' => 'Offer 123 is invalid']],
        ], 400)]);

        try {
            BookingCom::orders()->preview(['accommodation' => ['id' => 1]]);
            $this->fail('Expected exception');
        } catch (BookingComException $e) {
            $this->assertSame(400, $e->getCode());
            $this->assertSame(['invalid_request'], $e->errorIds());
            $this->assertSame('01kjan7r7yvff5yg95gxy1cjhy', $e->requestId);
            $this->assertStringContainsString('invalid_request: Offer 123 is invalid', $e->getMessage());
            $this->assertStringContainsString('01kjan7r7yvff5yg95gxy1cjhy', $e->getMessage());
        }
    }

    #[Test]
    public function rejected_credentials_raise_an_authentication_exception(): void
    {
        $this->fake(['*' => Http::response(['errors' => [['id' => 'unauthorised', 'message' => 'Invalid token']]], 401)]);

        $this->expectException(AuthenticationException::class);

        BookingCom::locations()->countries();
    }

    #[Test]
    public function rate_limits_raise_a_rate_limit_exception_with_a_retry_delay(): void
    {
        $this->fake([
            self::BASE.'/common/locations/cities' => Http::response(['errors' => [['id' => 'too_many_requests', 'message' => 'Slow down']]], 429, ['Retry-After' => '42']),
            self::BASE.'/common/locations/regions' => Http::response([], 429),
        ]);

        try {
            BookingCom::locations()->cities();
            $this->fail('Expected exception');
        } catch (RateLimitException $e) {
            $this->assertSame(42, $e->retryAfter());
        }

        try {
            BookingCom::locations()->regions();
            $this->fail('Expected exception');
        } catch (RateLimitException $e) {
            $this->assertSame(60, $e->retryAfter());
        }

        Http::assertSentCount(2);
    }

    #[Test]
    public function reads_are_retried_on_server_errors_but_orders_are_not(): void
    {
        $this->rebuild(['retry' => [3, 0]]);
        $this->fake([
            self::BASE.'/accommodations/details' => Http::sequence()->push([], 503)->push(['data' => [['id' => 1]]]),
            self::BASE.'/orders/create' => Http::response([], 503),
        ]);

        $this->assertSame([['id' => 1]], BookingCom::accommodations()->find(1)->data());

        try {
            BookingCom::orders()->create(['order_token' => 't']);
            $this->fail('Expected exception');
        } catch (BookingComException $e) {
            $this->assertSame(503, $e->getCode());
        }

        $this->assertCount(1, Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/orders/create')));
    }

    #[Test]
    public function dates_are_formatted_and_nulls_dropped(): void
    {
        $this->fake(['*' => $this->ok()]);

        BookingCom::accommodations()->search([
            'city' => -2140479,
            'checkin' => new \DateTimeImmutable('2026-11-01 15:00'),
            'checkout' => now()->setDate(2026, 11, 3),
            'district' => null,
            'guests' => ['number_of_adults' => 2, 'number_of_rooms' => 1, 'children' => []],
        ]);

        Http::assertSent(fn (Request $r) => $r['checkin'] === '2026-11-01'
            && $r['checkout'] === '2026-11-03'
            && ! array_key_exists('district', $r->data())
            && $r['guests']['children'] === []);
    }

    #[Test]
    public function reference_data_is_cached(): void
    {
        $this->fake(['*/common/languages' => $this->ok([['id' => 'en-gb']])]);

        $first = BookingCom::common()->languages();
        $second = BookingCom::common()->languages();

        $this->assertSame($first->toArray(), $second->toArray());
        Http::assertSentCount(1);

        BookingCom::sandbox()->common()->languages();
        Http::assertSentCount(2);
    }

    #[Test]
    public function the_cache_can_be_turned_off(): void
    {
        $this->rebuild(['cache.ttl' => null]);
        $this->fake([self::BASE.'/common/languages' => $this->ok([])]);

        BookingCom::common()->languages();
        BookingCom::common()->languages();

        Http::assertSentCount(2);
    }

    #[Test]
    public function unwrapped_endpoints_can_be_called_directly(): void
    {
        $this->fake([self::BASE.'/attractions/search' => $this->ok([['id' => 'a1']])]);

        $this->assertSame('a1', BookingCom::post('/attractions/search', ['city' => -2140479])[0]['id']);
    }
}
