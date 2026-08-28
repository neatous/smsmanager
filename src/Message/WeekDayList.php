<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Message;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, WeekDay> */
final readonly class WeekDayList implements IteratorAggregate, Countable
{

    /** @var list<WeekDay> */
    private array $weekDays;

    /** @param list<WeekDay> $weekDays */
    private function __construct(array $weekDays)
    {
        $this->weekDays = $weekDays;
    }

    public static function fromWeekDays(WeekDay ...$weekDays): self
    {
        if ($weekDays === []) {
            throw new \Neatous\SmsManager\Exception\InvalidDeliveryWindowException('Week day list must not be empty.');
        }

        $unique = [];

        foreach ($weekDays as $weekDay) {
            if (in_array($weekDay, $unique, true)) {
                throw new \Neatous\SmsManager\Exception\InvalidDeliveryWindowException(sprintf('Duplicate week day "%s".', $weekDay->value));
            }

            $unique[] = $weekDay;
        }

        return new self($unique);
    }

    public function contains(WeekDay $weekDay): bool
    {
        return in_array($weekDay, $this->weekDays, true);
    }

    /** @return Traversable<int, WeekDay> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->weekDays);
    }

    public function count(): int
    {
        return count($this->weekDays);
    }
}
