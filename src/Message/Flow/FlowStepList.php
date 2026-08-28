<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Message\Flow;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, FlowStep> */
final readonly class FlowStepList implements IteratorAggregate, Countable
{

    /** @var list<FlowStep> */
    private array $flowSteps;

    /** @param list<FlowStep> $flowSteps */
    private function __construct(array $flowSteps)
    {
        $this->flowSteps = $flowSteps;
    }

    public static function fromFlowSteps(FlowStep ...$flowSteps): self
    {
        if ($flowSteps === []) {
            throw new \Neatous\SmsManager\Exception\InvalidFlowException('Flow must contain at least one step.');
        }

        return new self(array_values($flowSteps));
    }

    public function everyStepHasBody(): bool
    {
        foreach ($this->flowSteps as $flowStep) {
            if (!$flowStep->hasBody()) {
                return false;
            }
        }

        return true;
    }

    /** @return Traversable<int, FlowStep> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->flowSteps);
    }

    public function count(): int
    {
        return count($this->flowSteps);
    }
}
