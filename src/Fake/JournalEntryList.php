<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Fake;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, JournalEntry> */
final readonly class JournalEntryList implements IteratorAggregate, Countable
{

    /** @var list<JournalEntry> */
    private array $entries;

    /** @param list<JournalEntry> $entries */
    private function __construct(array $entries)
    {
        $this->entries = $entries;
    }

    public static function fromEntries(JournalEntry ...$entries): self
    {
        return new self(array_values($entries));
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    public function isNotEmpty(): bool
    {
        return $this->entries !== [];
    }

    /** @return Traversable<int, JournalEntry> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->entries);
    }

    public function count(): int
    {
        return count($this->entries);
    }
}
