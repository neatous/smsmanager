<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Message;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, PhoneNumber> */
final readonly class RecipientList implements IteratorAggregate, Countable
{

    private const int MAX_RECIPIENTS = 10;

    /** @var list<PhoneNumber> */
    private array $phoneNumbers;

    /** @param list<PhoneNumber> $phoneNumbers */
    private function __construct(array $phoneNumbers)
    {
        $this->phoneNumbers = $phoneNumbers;
    }

    public static function fromPhoneNumbers(PhoneNumber ...$phoneNumbers): self
    {
        if ($phoneNumbers === [] || count($phoneNumbers) > self::MAX_RECIPIENTS) {
            throw new \Neatous\SmsManager\Exception\InvalidRecipientListException(
                sprintf('Recipient list must contain 1 to %d phone numbers, %d given.', self::MAX_RECIPIENTS, count($phoneNumbers))
            );
        }

        $unique = [];

        foreach ($phoneNumbers as $phoneNumber) {
            foreach ($unique as $known) {
                if ($known->equals($phoneNumber)) {
                    throw new \Neatous\SmsManager\Exception\InvalidRecipientListException(sprintf('Duplicate recipient "%s".', $phoneNumber->getValue()));
                }
            }

            $unique[] = $phoneNumber;
        }

        return new self($unique);
    }

    public function isSingle(): bool
    {
        return count($this->phoneNumbers) === 1;
    }

    /** @return Traversable<int, PhoneNumber> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->phoneNumbers);
    }

    public function count(): int
    {
        return count($this->phoneNumbers);
    }
}
