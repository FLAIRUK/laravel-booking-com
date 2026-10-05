<?php

namespace FLAIRUK\BookingCom\Resources;

use FLAIRUK\BookingCom\Response;

/**
 * Booking.com's location ids, used in accommodation searches (e.g. city -2140479 is Amsterdam).
 *
 * Paginated: call ->lazy() on any result to walk every page.
 *
 * @see https://developers.booking.com/demand/docs/development-guide/common-autocomplete
 */
class Locations extends Resource
{
    /**
     * @param  array<string, mixed>  $query  e.g. ['languages' => ['en-gb']]
     */
    public function countries(array $query = []): Response
    {
        return $this->client->post('common/locations/countries', $query);
    }

    /**
     * @param  array<string, mixed>  $query  e.g. ['country' => 'nl', 'languages' => ['en-gb']]
     */
    public function cities(array $query = []): Response
    {
        return $this->client->post('common/locations/cities', $this->lowercaseCountry($query));
    }

    public function regions(array $query = []): Response
    {
        return $this->client->post('common/locations/regions', $this->lowercaseCountry($query));
    }

    public function districts(array $query = []): Response
    {
        return $this->client->post('common/locations/districts', $this->lowercaseCountry($query));
    }

    public function landmarks(array $query = []): Response
    {
        return $this->client->post('common/locations/landmarks', $this->lowercaseCountry($query));
    }

    public function airports(array $query = []): Response
    {
        return $this->client->post('common/locations/airports', $this->lowercaseCountry($query));
    }

    protected function lowercaseCountry(array $query): array
    {
        if (isset($query['country']) && is_string($query['country'])) {
            $query['country'] = strtolower($query['country']);
        }

        return $query;
    }
}
