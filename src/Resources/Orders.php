<?php

namespace FLAIRUK\BookingCom\Resources;

use FLAIRUK\BookingCom\Response;

/**
 * Booking flow: preview() returns an order_token, create() places the order with it.
 *
 * create(), modify() and cancel() are never retried automatically, so a timeout
 * can't book or cancel twice. Check orders()->find() before trying again.
 *
 * @see https://developers.booking.com/demand/docs/orders-api/overview
 */
class Orders extends Resource
{
    /**
     * Price and policies for the chosen products, plus the order_token needed by create().
     *
     * @param  array<string, mixed>  $order  e.g. ['accommodation' => ['id' => 10507360, 'checkin' => ..., 'checkout' => ..., 'products' => [...]]]
     */
    public function preview(array $order): Response
    {
        if (isset($order['accommodation']) && is_array($order['accommodation'])) {
            $order['accommodation'] = $this->withDefaults($order['accommodation'], currency: false);
        }

        return $this->client->post('orders/preview', $this->withDefaults($order, booker: false));
    }

    /**
     * Place the order. Card details pass straight through to Booking.com: never log this payload.
     *
     * @param  array<string, mixed>  $order  ['order_token' => ..., 'booker' => [...], 'payment' => ['timing' => 'pay_at_the_property']]
     */
    public function create(array $order): Response
    {
        return $this->client->post('orders/create', $order, retry: false);
    }

    /**
     * Orders by creation, update, start or end date range (max 7 days), or by order or reservation ids.
     *
     * Paginated: call ->lazy() on the result to walk every page.
     *
     * @param  array<string, mixed>  $query  e.g. ['created' => ['from' => '2026-10-01', 'to' => '2026-10-07']]
     */
    public function details(array $query): Response
    {
        return $this->client->post('orders/details', $this->withDefaults($query, booker: false));
    }

    /**
     * Orders by id.
     *
     * @param  string|list<string>  $orderIds
     */
    public function find(string|array $orderIds, ?string $currency = null, array $extras = []): Response
    {
        return $this->details(array_filter([
            'orders' => array_values((array) $orderIds),
            'currency' => $currency,
            'extras' => $extras,
        ]));
    }

    /**
     * Accommodation reservation details for orders or reservations.
     *
     * @param  array<string, mixed>  $query  ['orders' => [...]] or ['reservations' => [...]]
     */
    public function accommodations(array $query): Response
    {
        return $this->client->post('orders/details/accommodations', $query);
    }

    /**
     * @param  array<string, mixed>  $query  ['orders' => [...]] or ['reservations' => [...]]
     */
    public function cars(array $query): Response
    {
        return $this->client->post('orders/details/cars', $query);
    }

    /**
     * @param  array<string, mixed>  $query  ['orders' => [...]] or ['reservations' => [...]]
     */
    public function flights(array $query): Response
    {
        return $this->client->post('orders/details/flights', $query);
    }

    /**
     * Change an order (dates, guests, room, or payment details).
     *
     * @param  array<string, mixed>  $modification  ['order' => ..., 'modification' => [...]]
     */
    public function modify(array $modification): Response
    {
        return $this->client->post('orders/modify', $modification, retry: false);
    }

    /**
     * Cancel a travel service in an order.
     *
     * @param  array<string, mixed>  $cancellation  e.g. ['order' => ..., 'accommodation' => ['reservation' => ..., 'reason' => ...]]
     */
    public function cancel(array $cancellation): Response
    {
        return $this->client->post('orders/cancel', $cancellation, retry: false);
    }

    /**
     * Cancel an accommodation reservation.
     */
    public function cancelAccommodation(string|int $reservation, string $reason, ?string $order = null): Response
    {
        return $this->cancel(array_filter([
            'order' => $order,
            'accommodation' => ['reservation' => (string) $reservation, 'reason' => $reason],
        ]));
    }
}
