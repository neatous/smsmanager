<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Webhook;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, UnparsedWebhookItem> */
final readonly class UnparsedWebhookItemList implements IteratorAggregate, Countable
{

    /** @var list<UnparsedWebhookItem> */
    private array $items;

    /** @param list<UnparsedWebhookItem> $items */
    private function __construct(array $items)
    {
        $this->items = $items;
    }

    public static function fromItems(UnparsedWebhookItem ...$items): self
    {
        return new self(array_values($items));
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function isNotEmpty(): bool
    {
        return $this->items !== [];
    }

    /** @return Traversable<int, UnparsedWebhookItem> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }
}
