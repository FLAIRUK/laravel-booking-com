<?php

namespace FLAIRUK\BookingCom\Tests;

use FLAIRUK\BookingCom\Facades\BookingCom;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class ResourcesTest extends TestCase
{
    #[Test]
    public function searches_fill_in_the_configured_booker_and_currency(): void
    {
        $this->rebuild(['defaults' => ['booker' => ['country' => 'GB', 'platform' => 'desktop'], 'currency' => 'gbp']]);
        $this->fake(['*' => $this->ok()]);

        BookingCom::accommodations()->search(['city' => -2140479]);
        BookingCom::accommodations()->availability(['accommodations' => [10507360], 'booker' => ['platform' => 'mobile'], 'currency' => 'EUR']);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/accommodations/search')
            && $r['booker'] === ['country' => 'gb', 'platform' => 'desktop']
            && $r['currency'] === 'GBP');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/accommodations/availability')
            && $r['booker'] === ['country' => 'gb', 'platform' => 'mobile']
            && $r['currency'] === 'EUR');
    }

    #[Test]
    public function car_searches_only_take_the_default_booker_country(): void
    {
        $this->rebuild(['defaults' => ['booker' => ['country' => 'NL', 'platform' => 'desktop'], 'currency' => 'EUR']]);
        $this->fake(['*' => $this->ok()]);

        BookingCom::cars()->search(['driver' => ['age' => 36]]);

        Http::assertSent(fn (Request $r) => $r['booker'] === ['country' => 'nl'] && $r['currency'] === 'EUR');
    }

    #[Test]
    public function search_results_can_be_walked_page_by_page(): void
    {
        $this->fake([self::BASE.'/accommodations/search' => Http::sequence()
            ->push(['request_id' => 'r1', 'data' => [['id' => 1], ['id' => 2]], 'metadata' => ['next_page' => 'token-2', 'total_results' => 3]])
            ->push(['request_id' => 'r2', 'data' => [['id' => 3]], 'metadata' => ['total_results' => 3]]),
        ]);

        $first = BookingCom::accommodations()->search(['city' => -2140479, 'extras' => ['products']]);

        $this->assertSame(3, $first->total());
        $this->assertSame('token-2', $first->nextPage());
        $this->assertCount(2, $first);
        $this->assertSame('r1', $first->requestId());
        $this->assertSame([1, 2, 3], $first->lazy()->pluck('id')->all());

        Http::assertSent(fn (Request $r) => $r->data() === ['page' => 'token-2', 'extras' => ['products']]);
    }

    #[Test]
    public function next_returns_null_on_the_last_page(): void
    {
        $this->fake(['*' => $this->ok([['id' => 1]])]);

        $page = BookingCom::locations()->cities(['country' => 'NL']);

        $this->assertNull($page->next());
        $this->assertFalse($page->hasMorePages());
        Http::assertSent(fn (Request $r) => $r['country'] === 'nl');
    }

    #[Test]
    public function accommodation_details_by_id(): void
    {
        $this->fake(['*' => $this->ok([['id' => 10507360]])]);

        BookingCom::accommodations()->find(10507360, ['photos', 'facilities'], ['en-gb']);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/accommodations/details')
            && $r->data() === ['accommodations' => [10507360], 'extras' => ['photos', 'facilities'], 'languages' => ['en-gb']]);
    }

    #[Test]
    public function changes_are_followed_until_there_is_no_next_timestamp(): void
    {
        $this->fake([self::BASE.'/accommodations/details/changes' => Http::sequence()
            ->push(['data' => ['changes' => ['changed' => [1], 'closed' => [], 'opened' => [2]], 'from' => '2026-10-01T12:00:00+00:00', 'next' => '2026-10-01T12:24:42+00:00']])
            ->push(['data' => ['changes' => ['changed' => [3], 'closed' => [4], 'opened' => []], 'from' => '2026-10-01T12:24:42+00:00']]),
        ]);

        $batches = BookingCom::accommodations()
            ->changesSince(new \DateTimeImmutable('2026-10-01 13:00', new \DateTimeZone('Europe/London')), ['countries' => ['nl']])
            ->all();

        $this->assertCount(2, $batches);
        $this->assertSame([1], $batches[0]['changed']);
        $this->assertSame([4], $batches[1]['closed']);
        Http::assertSent(fn (Request $r) => $r['last_change'] === '2026-10-01T12:00:00+00:00' && $r['filters'] === ['countries' => ['nl']]);
        Http::assertSent(fn (Request $r) => $r['last_change'] === '2026-10-01T12:24:42+00:00');
    }

    #[Test]
    public function order_preview_puts_the_booker_inside_the_accommodation(): void
    {
        $this->rebuild(['defaults' => ['booker' => ['country' => 'nl', 'platform' => 'desktop'], 'currency' => 'EUR']]);
        $this->fake(['*' => $this->ok(['order_token' => 'tok'])]);

        $token = BookingCom::orders()->preview(['accommodation' => [
            'id' => 10507360,
            'checkin' => '2026-11-01',
            'checkout' => '2026-11-03',
            'products' => [['id' => 'p1', 'allocation' => ['number_of_adults' => 2]]],
        ]])->data()['order_token'];

        $this->assertSame('tok', $token);
        Http::assertSent(fn (Request $r) => $r['accommodation']['booker'] === ['country' => 'nl', 'platform' => 'desktop']
            && $r['currency'] === 'EUR'
            && ! isset($r['booker']));
    }

    #[Test]
    public function orders_can_be_found_and_cancelled(): void
    {
        $this->rebuild(['defaults' => ['booker' => ['country' => null, 'platform' => 'desktop'], 'currency' => 'EUR']]);
        $this->fake(['*' => $this->ok()]);

        BookingCom::orders()->find('order-1');
        BookingCom::orders()->cancelAccommodation(4000123456, 'Guest changed plans');

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/orders/details')
            && $r->data() === ['orders' => ['order-1'], 'currency' => 'EUR']);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/orders/cancel')
            && $r->data() === ['accommodation' => ['reservation' => '4000123456', 'reason' => 'Guest changed plans']]);
    }

    #[Test]
    public function messaging_can_target_version_3_1(): void
    {
        $this->fake(['https://demandapi.booking.com/3.1/*' => $this->ok()]);

        BookingCom::version('3.1')->messages()->confirm(['m1', 'm2']);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://demandapi.booking.com/3.1/messages/latest/confirm'
            && $r['messages'] === ['m1', 'm2']);
    }

    #[Test]
    public function every_resource_method_posts_to_a_documented_endpoint(): void
    {
        $this->fake(['*' => $this->ok()]);

        $calls = [
            'accommodations/chains' => fn () => BookingCom::accommodations()->chains(),
            'accommodations/constants' => fn () => BookingCom::accommodations()->constants(),
            'accommodations/reviews' => fn () => BookingCom::accommodations()->reviews(['accommodations' => [1]]),
            'accommodations/reviews/scores' => fn () => BookingCom::accommodations()->reviewScores(['accommodations' => [1]]),
            'accommodations/third-party-suppliers' => fn () => BookingCom::accommodations()->thirdPartySuppliers(),
            'cars/search' => fn () => BookingCom::cars()->search(['route' => []]),
            'cars/details' => fn () => BookingCom::cars()->details(),
            'cars/depots' => fn () => BookingCom::cars()->depots(),
            'cars/depots/reviews/scores' => fn () => BookingCom::cars()->depotScores(),
            'cars/suppliers' => fn () => BookingCom::cars()->suppliers(),
            'cars/constants' => fn () => BookingCom::cars()->constants(),
            'common/payments/currencies' => fn () => BookingCom::common()->currencies(),
            'common/payments/cards' => fn () => BookingCom::common()->cards(),
            'common/locations/districts' => fn () => BookingCom::locations()->districts(),
            'common/locations/landmarks' => fn () => BookingCom::locations()->landmarks(),
            'common/locations/airports' => fn () => BookingCom::locations()->airports(),
            'orders/details/accommodations' => fn () => BookingCom::orders()->accommodations(['orders' => ['o']]),
            'orders/details/cars' => fn () => BookingCom::orders()->cars(['orders' => ['o']]),
            'orders/details/flights' => fn () => BookingCom::orders()->flights(['orders' => ['o']]),
            'orders/modify' => fn () => BookingCom::orders()->modify(['order' => 'o', 'modification' => []]),
        ];

        foreach ($calls as $path => $call) {
            $call();
            Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->url() === self::BASE.'/'.$path);
        }
    }
}
