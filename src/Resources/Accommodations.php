<?php

namespace FLAIRUK\BookingCom\Resources;

use DateTimeInterface;
use FLAIRUK\BookingCom\Response;
use Illuminate\Support\LazyCollection;

/**
 * @see https://developers.booking.com/demand/docs/accommodations/about-accommodation
 */
class Accommodations extends Resource
{
    /**
     * Search for available properties in a city, region, country, district,
     * landmark, airport area, coordinates radius or list of accommodation ids.
     *
     * Paginated: call ->lazy() on the result to walk every page.
     *
     * @param  array<string, mixed>  $query  e.g. ['city' => -2140479, 'checkin' => '2026-11-01', 'checkout' => '2026-11-03', 'guests' => ['number_of_adults' => 2, 'number_of_rooms' => 1]]
     */
    public function search(array $query): Response
    {
        return $this->client->post('accommodations/search', $this->withDefaults($query));
    }

    /**
     * Live products, prices and policies for specific properties.
     *
     * @param  array<string, mixed>  $query  e.g. ['accommodations' => [10507360], 'checkin' => ..., 'checkout' => ..., 'guests' => [...]]
     */
    public function availability(array $query): Response
    {
        return $this->client->post('accommodations/availability', $this->withDefaults($query));
    }

    /**
     * Availability for several properties in one call. API version 3.1 only;
     * in 3.2 use availability() or search() with an accommodations list.
     */
    public function bulkAvailability(array $query): Response
    {
        return $this->client->post('accommodations/bulk-availability', $this->withDefaults($query));
    }

    /**
     * Static content: names, descriptions, photos, facilities, policies, rooms.
     *
     * Paginated: call ->lazy() on the result to walk every page.
     *
     * @param  array<string, mixed>  $query  e.g. ['accommodations' => [10507360], 'extras' => ['description', 'photos'], 'languages' => ['en-gb']]
     */
    public function details(array $query): Response
    {
        return $this->client->post('accommodations/details', $query);
    }

    /**
     * Details for one or more accommodation ids.
     *
     * @param  int|list<int>  $ids
     * @param  list<string>  $extras  e.g. ['description', 'facilities', 'photos', 'policies', 'rooms']
     * @param  list<string>  $languages  e.g. ['en-gb']
     */
    public function find(int|array $ids, array $extras = [], array $languages = []): Response
    {
        return $this->details(array_filter([
            'accommodations' => array_values((array) $ids),
            'extras' => $extras,
            'languages' => $languages,
        ]));
    }

    /**
     * Ids of properties that changed, opened or closed since a moment in time.
     *
     * Follow `data.next` until it is absent; if nothing changed, try again in a minute.
     *
     * @param  array<string, mixed>  $filters  e.g. ['countries' => ['nl']]
     */
    public function changes(DateTimeInterface $since, array $filters = []): Response
    {
        return $this->client->post('accommodations/details/changes', array_filter([
            'last_change' => \DateTimeImmutable::createFromInterface($since)->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM),
            'filters' => $filters,
        ]));
    }

    /**
     * Every change since a moment in time, following the `next` timestamps.
     *
     * @return LazyCollection<int, array{changed: list<int>, closed: list<int>, opened: list<int>, from: string, next: ?string}>
     */
    public function changesSince(DateTimeInterface $since, array $filters = []): LazyCollection
    {
        return LazyCollection::make(function () use ($since, $filters) {
            $cursor = $since;

            do {
                $data = (array) $this->changes($cursor, $filters)->data();
                $next = $data['next'] ?? null;

                yield [
                    'changed' => $data['changes']['changed'] ?? [],
                    'closed' => $data['changes']['closed'] ?? [],
                    'opened' => $data['changes']['opened'] ?? [],
                    'from' => $data['from'] ?? null,
                    'next' => $next,
                ];

                $cursor = $next ? new \DateTimeImmutable($next) : null;
            } while ($cursor !== null);
        });
    }

    /**
     * Guest reviews.
     *
     * @param  array<string, mixed>  $query  e.g. ['accommodations' => [10507360], 'languages' => ['en-gb']]
     */
    public function reviews(array $query): Response
    {
        return $this->client->post('accommodations/reviews', $query);
    }

    /**
     * Review score breakdowns.
     *
     * @param  array<string, mixed>  $query  e.g. ['accommodations' => [10507360]]
     */
    public function reviewScores(array $query): Response
    {
        return $this->client->post('accommodations/reviews/scores', $query);
    }

    /**
     * Hotel chains and their brands. Cached.
     */
    public function chains(array $query = []): Response
    {
        return $this->client->remember('accommodations/chains', $query);
    }

    /**
     * Ids and names for facilities, property types, room types, meal plans and so on. Cached.
     *
     * @param  array<string, mixed>  $query  e.g. ['languages' => ['en-gb']]
     */
    public function constants(array $query = []): Response
    {
        return $this->client->remember('accommodations/constants', $query);
    }

    /**
     * Third-party inventory suppliers. API version 3.2 and later. Cached.
     */
    public function thirdPartySuppliers(array $query = []): Response
    {
        return $this->client->remember('accommodations/third-party-suppliers', $query);
    }
}
