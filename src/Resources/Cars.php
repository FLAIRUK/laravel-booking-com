<?php

namespace FLAIRUK\BookingCom\Resources;

use FLAIRUK\BookingCom\Response;

/**
 * Car rentals. Not available in the sandbox.
 *
 * @see https://developers.booking.com/demand/docs/cars/overview
 */
class Cars extends Resource
{
    /**
     * Search for car rentals on a route. Paginated: call ->lazy() on the result.
     *
     * @param  array<string, mixed>  $query  e.g. ['route' => [...], 'driver' => ['age' => 30], 'currency' => 'EUR']
     */
    public function search(array $query): Response
    {
        return $this->client->post('cars/search', $this->withDefaults($query, booker: ['country']));
    }

    public function details(array $query = []): Response
    {
        return $this->client->post('cars/details', $query);
    }

    public function depots(array $query = []): Response
    {
        return $this->client->post('cars/depots', $query);
    }

    public function depotScores(array $query = []): Response
    {
        return $this->client->post('cars/depots/reviews/scores', $query);
    }

    /**
     * Cached.
     */
    public function suppliers(array $query = []): Response
    {
        return $this->client->remember('cars/suppliers', $query);
    }

    /**
     * Cached.
     */
    public function constants(array $query = []): Response
    {
        return $this->client->remember('cars/constants', $query);
    }
}
