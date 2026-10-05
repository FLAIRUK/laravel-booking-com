<?php

namespace FLAIRUK\BookingCom;

use ArrayAccess;
use ArrayIterator;
use Closure;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use IteratorAggregate;
use JsonSerializable;
use LogicException;
use Traversable;

/**
 * A Demand API response: `data`, `metadata` and the `request_id` to quote to Booking.com support.
 *
 * Paginated endpoints can be followed with next() or lazy().
 *
 * @implements ArrayAccess<array-key, mixed>
 * @implements IteratorAggregate<array-key, mixed>
 * @implements Arrayable<string, mixed>
 */
class Response implements Arrayable, ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * @param  array<string, mixed>  $body
     * @param  (Closure(string): Response)|null  $fetchPage
     */
    public function __construct(
        protected array $body,
        protected ?Closure $fetchPage = null,
    ) {}

    public function data(): mixed
    {
        return $this->body['data'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return (array) ($this->body['metadata'] ?? []);
    }

    public function requestId(): ?string
    {
        return $this->body['request_id'] ?? null;
    }

    public function total(): ?int
    {
        $total = $this->metadata()['total_results'] ?? null;

        return $total === null ? null : (int) $total;
    }

    /**
     * Token for the next page; valid for three hours. Null on the last page.
     */
    public function nextPage(): ?string
    {
        return $this->metadata()['next_page'] ?? null;
    }

    public function hasMorePages(): bool
    {
        return $this->nextPage() !== null;
    }

    /**
     * Fetch the next page, or null on the last page.
     */
    public function next(): ?self
    {
        if (! $this->hasMorePages()) {
            return null;
        }

        if ($this->fetchPage === null) {
            throw new LogicException('This response is not from a paginated endpoint.');
        }

        return ($this->fetchPage)($this->nextPage());
    }

    /**
     * Every item from this page onwards, fetching further pages only as they are needed.
     *
     * @return LazyCollection<int, mixed>
     */
    public function lazy(): LazyCollection
    {
        return LazyCollection::make(function () {
            $page = $this;

            while ($page !== null) {
                foreach ($page->items() as $item) {
                    yield $item;
                }

                $page = $page->next();
            }
        });
    }

    /**
     * The items on this page.
     *
     * @return Collection<array-key, mixed>
     */
    public function collect(): Collection
    {
        return new Collection($this->items());
    }

    /**
     * The full response body.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->body;
    }

    public function jsonSerialize(): array
    {
        return $this->body;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items());
    }

    public function count(): int
    {
        return count($this->items());
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items()[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items()[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('Booking.com responses are read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('Booking.com responses are read-only.');
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function items(): array
    {
        $data = $this->data();

        return is_array($data) ? $data : [];
    }
}
