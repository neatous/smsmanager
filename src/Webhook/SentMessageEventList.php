<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Webhook;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, SentMessageEvent> */
final readonly class SentMessageEventList implements IteratorAggregate, Countable
{

    /** @var list<SentMessageEvent> */
    private array $events;

    /** @param list<SentMessageEvent> $events */
    private function __construct(array $events)
    {
        $this->events = $events;
    }

    public static function fromEvents(SentMessageEvent ...$events): self
    {
        return new self(array_values($events));
    }

    public function isEmpty(): bool
    {
        return $this->events === [];
    }

    public function isNotEmpty(): bool
    {
        return $this->events !== [];
    }

    /** @return Traversable<int, SentMessageEvent> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->events);
    }

    public function count(): int
    {
        return count($this->events);
    }
}
