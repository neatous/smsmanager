<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Message\Flow;

use Neatous\SmsManager\Message\Flow\FlowStepList;
use Neatous\SmsManager\Message\Flow\SmsFlowStep;
use Neatous\SmsManager\Message\MessageBody;
use PHPUnit\Framework\TestCase;

final class FlowStepListTest extends TestCase
{

    public function testEveryStepHasBody(): void
    {
        $flow = FlowStepList::fromFlowSteps(
            SmsFlowStep::create(MessageBody::fromString('First')),
            SmsFlowStep::create(MessageBody::fromString('Second'))
        );
        self::assertCount(2, $flow);
        self::assertTrue($flow->everyStepHasBody());
    }

    public function testStepWithoutBody(): void
    {
        $flow = FlowStepList::fromFlowSteps(SmsFlowStep::create(MessageBody::fromString('First')), SmsFlowStep::create());
        self::assertFalse($flow->everyStepHasBody());
        self::assertCount(2, iterator_to_array($flow));
    }

    public function testRejectsEmptyFlow(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidFlowException::class);
        FlowStepList::fromFlowSteps();
    }
}
