<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Acceptance;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, AcceptedRecipient> */
final readonly class AcceptedRecipientList implements IteratorAggregate, Countable
{

    /** @var list<AcceptedRecipient> */
    private array $recipients;

    /** @param list<AcceptedRecipient> $recipients */
    private function __construct(array $recipients)
    {
        $this->recipients = $recipients;
    }

    public static function fromAcceptedRecipients(AcceptedRecipient ...$recipients): self
    {
        return new self(array_values($recipients));
    }

    public function getByRecipientIndex(int $recipientIndex): ?AcceptedRecipient
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

    /** @return Traversable<int, AcceptedRecipient> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->recipients);
    }

    public function count(): int
    {
        return count($this->recipients);
    }
}
