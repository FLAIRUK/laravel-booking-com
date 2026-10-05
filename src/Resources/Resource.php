<?php

namespace FLAIRUK\BookingCom\Resources;

use FLAIRUK\BookingCom\BookingCom;

abstract class Resource
{
    public function __construct(protected BookingCom $client) {}

    /**
     * Fill in the configured booker and currency where the request doesn't set them.
     */
    /**
     * @param  bool|list<string>  $booker  false to skip, or the default booker fields the endpoint accepts
     */
    protected function withDefaults(array $body, bool|array $booker = true, bool $currency = true): array
    {
        $defaults = $this->client->defaults();

        if ($booker !== false) {
            $fallback = array_filter($defaults['booker'], fn ($value) => $value !== null);

            if (is_array($booker)) {
                $fallback = array_intersect_key($fallback, array_flip($booker));
            }

            $body['booker'] = array_filter(
                array_replace($fallback, (array) ($body['booker'] ?? [])),
                fn ($value) => $value !== null,
            );

            if (isset($body['booker']['country'])) {
                $body['booker']['country'] = strtolower($body['booker']['country']);
            }
        }

        if ($currency && ! array_key_exists('currency', $body) && filled($defaults['currency'])) {
            $body['currency'] = strtoupper($defaults['currency']);
        }

        return $body;
    }
}
