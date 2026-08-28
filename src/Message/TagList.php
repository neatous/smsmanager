<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Message;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, Tag> */
final readonly class TagList implements IteratorAggregate, Countable
{

    /** @var list<Tag> */
    private array $tags;

    /** @param list<Tag> $tags */
    private function __construct(array $tags)
    {
        $this->tags = $tags;
    }

    public static function fromTags(Tag ...$tags): self
    {
        if ($tags === []) {
            throw new \Neatous\SmsManager\Exception\InvalidTagException('Tag list must not be empty.');
        }

        $values = [];

        foreach ($tags as $tag) {
            if (in_array($tag->getValue(), $values, true)) {
                throw new \Neatous\SmsManager\Exception\InvalidTagException(sprintf('Duplicate tag "%s".', $tag->getValue()));
            }

            $values[] = $tag->getValue();
        }

        return new self(array_values($tags));
    }

    public function toRequestValue(): string
    {
        $values = [];

        foreach ($this->tags as $tag) {
            $values[] = $tag->getValue();
        }

        return implode(',', $values);
    }

    /** @return Traversable<int, Tag> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->tags);
    }

    public function count(): int
    {
        return count($this->tags);
    }
}
