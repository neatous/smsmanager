<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Message\Flow;

use Neatous\SmsManager\Message\Flow\SmsEncoding;
use Neatous\SmsManager\Message\Flow\SmsFlowStep;
use Neatous\SmsManager\Message\Flow\SmsGateway;
use Neatous\SmsManager\Message\Flow\TtlCondition;
use Neatous\SmsManager\Message\MessageBody;
use Neatous\SmsManager\Message\Sender;
use PHPUnit\Framework\TestCase;

final class SmsFlowStepTest extends TestCase
{

    public function testMinimalStep(): void
    {
        $step = SmsFlowStep::create();
        self::assertFalse($step->hasBody());
        self::assertSame(['sms' => ['gateway' => 'high', 'type' => 'utf']], $step->toRequestData());
    }

    public function testFullStep(): void
    {
        $step = SmsFlowStep::create(
            MessageBody::fromString('Hello'),
            Sender::fromString('Neatous'),
            SmsGateway::LOWCOST,
            1.5,
            TtlCondition::DELIVERED,
            SmsEncoding::SMS
        );
        self::assertTrue($step->hasBody());
        self::assertSame(
            ['sms' => ['body' => 'Hello', 'sender' => 'Neatous', 'gateway' => 'lowcost', 'ttl' => 1.5, 'ttl_condition' => 'delivered', 'type' => 'sms']],
            $step->toRequestData()
        );
    }

    public function testAcceptsMinimalTtl(): void
    {
        self::assertSame(0.5, SmsFlowStep::create(null, null, SmsGateway::HIGH, 0.5)->getTtlMinutes());
    }

    public function testRejectsTooShortTtl(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidFlowException::class);
        SmsFlowStep::create(null, null, SmsGateway::HIGH, 0.4);
    }
}
