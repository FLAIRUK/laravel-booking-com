<?php

namespace FLAIRUK\BookingCom\Resources;

use FLAIRUK\BookingCom\Response;

/**
 * Reference data shared by every travel service. All cached.
 */
class Common extends Resource
{
    /**
     * Supported language codes, e.g. "en-gb".
     */
    public function languages(array $query = []): Response
    {
        return $this->client->remember('common/languages', $query);
    }

    /**
     * Supported currency codes.
     */
    public function currencies(array $query = []): Response
    {
        return $this->client->remember('common/payments/currencies', $query);
    }

    /**
     * Supported payment card types.
     */
    public function cards(array $query = []): Response
    {
        return $this->client->remember('common/payments/cards', $query);
    }
}
