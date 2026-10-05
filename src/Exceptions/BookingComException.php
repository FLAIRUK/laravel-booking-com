<?php

namespace FLAIRUK\BookingCom\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

class BookingComException extends RuntimeException
{
    /**
     * @param  list<array{id: string, message: string}>  $errors
     */
    public function __construct(
        string $message,
        public readonly ?Response $response = null,
        public readonly array $errors = [],
        public readonly ?string $requestId = null,
    ) {
        parent::__construct($message, $response?->status() ?? 0);
    }

    public static function fromResponse(Response $response): static
    {
        $errors = array_values(array_filter((array) $response->json('errors'), 'is_array'));
        $requestId = $response->json('request_id');

        $detail = $errors
            ? implode('; ', array_map(fn (array $error) => trim(($error['id'] ?? '').': '.($error['message'] ?? ''), ': '), $errors))
            : ($response->json('message') ?: $response->reason());

        $message = "Booking.com API request failed ({$response->status()}): {$detail}";

        if (is_string($requestId)) {
            $message .= " [request_id: {$requestId}]";
        }

        return new static($message, $response, $errors, is_string($requestId) ? $requestId : null);
    }

    /**
     * The error ids returned by Booking.com, e.g. ["invalid_request"].
     *
     * @return list<string>
     */
    public function errorIds(): array
    {
        return array_values(array_filter(array_column($this->errors, 'id')));
    }
}
