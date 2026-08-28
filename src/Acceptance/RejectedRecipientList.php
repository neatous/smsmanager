<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Acceptance;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, RejectedRecipient> */
final readonly class RejectedRecipientList implements IteratorAggregate, Countable
{

    /** @var list<RejectedRecipient> */
    private array $recipients;

    /** @param list<RejectedRecipient> $recipients */
    private function __construct(array $recipients)
    {
        $this->recipients = $recipients;
    }

    public static function fromRejectedRecipients(RejectedRecipient ...$recipients): self
    {
        return new self(array_values($recipients));
    }

    public function getByRecipientIndex(int $recipientIndex): ?RejectedRecipient
    {
        foreach ($this->recipients as $recipient) {
            if ($recipient->getRecipientIndex() === $recipientIndex) {
                return $recipient;
            }
        }

        return null;
    }

    public function isEmpty(): bool
    {
        return $this->recipients === [];
    }

    public function isNotEmpty(): bool
    {
        return $this->recipients !== [];
    }

    /** @return Traversable<int, RejectedRecipient> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->recipients);
    }

    public function count(): int
    {
        return count($this->recipients);
    }
}
