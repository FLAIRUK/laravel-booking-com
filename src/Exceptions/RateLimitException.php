<?php

namespace FLAIRUK\BookingCom\Exceptions;

/**
 * The partner account's per-minute request quota was exceeded (429).
 */
class RateLimitException extends BookingComException
{
    /**
     * Seconds to wait before retrying. Booking.com usually lifts the block after a minute.
     */
    public function retryAfter(): int
    {
        $header = $this->response?->header('Retry-After');

        return is_numeric($header) ? max(1, (int) $header) : 60;
    }
}
