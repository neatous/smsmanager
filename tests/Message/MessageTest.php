<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Message;

use DateTimeImmutable;
use Neatous\SmsManager\Message\CallbackUrl;
use Neatous\SmsManager\Message\DeliveryWindow;
use Neatous\SmsManager\Message\Flow\FlowStepList;
use Neatous\SmsManager\Message\Flow\SmsFlowStep;
use Neatous\SmsManager\Message\Message;
use Neatous\SmsManager\Message\MessageBody;
use Neatous\SmsManager\Message\Payload;
use Neatous\SmsManager\Message\PhoneNumber;
use Neatous\SmsManager\Message\RecipientList;
use Neatous\SmsManager\Message\Sender;
use Neatous\SmsManager\Message\Tag;
use Neatous\SmsManager\Message\TagList;
use Neatous\SmsManager\Message\TimeOfDay;
use Neatous\SmsManager\Message\WeekDay;
use Neatous\SmsManager\Message\WeekDayList;
use PHPUnit\Framework\TestCase;

final class MessageTest extends TestCase
{

    public function testCreatesSimpleMessage(): void
    {
        $body = MessageBody::fromString('Hello');
        $recipients = self::recipients();
        $message = Message::create($body, $recipients);
        self::assertSame($body, $message->getBody());
        self::assertSame($recipients, $message->getRecipients());
        self::assertNull($message->getFlow());
        self::assertNull($message->getSender());
        self::assertNull($message->getTags());
        self::assertNull($message->getCallbackUrl());
        self::assertNull($message->getScheduledAt());
        self::assertNull($message->getDeliveryWindow());
        self::assertNull($message->getPayload());
    }

    public function testCreatesMessageWithFlowUsingStepBodies(): void
    {
        $flow = FlowStepList::fromFlowSteps(SmsFlowStep::create(MessageBody::fromString('Hello')));
        $message = Message::createWithFlow($flow, self::recipients());
        self::assertSame($flow, $message->getFlow());
        self::assertNull($message->getBody());
    }

    public function testCreatesMessageWithFlowUsingSharedBody(): void
    {
        $body = MessageBody::fromString('Hello');
        $message = Message::createWithFlow(FlowStepList::fromFlowSteps(SmsFlowStep::create()), self::recipients(), $body);
        self::assertSame($body, $message->getBody());
    }

    public function testRejectsFlowWithoutAnyBody(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidFlowException::class);
        Message::createWithFlow(FlowStepList::fromFlowSteps(SmsFlowStep::create()), self::recipients());
    }

    public function testWithersReturnNewInstances(): void
    {
        $message = Message::create(MessageBody::fromString('Hello'), self::recipients());
        $sender = Sender::fromString('Neatous');
        $tags = TagList::fromTags(Tag::transactional(), Tag::fromString('winter-sale'));
        $callbackUrl = CallbackUrl::fromString('https://example.com/callback');
        $scheduledAt = new DateTimeImmutable('2026-01-01 10:00:00');
        $deliveryWindow = DeliveryWindow::create(WeekDayList::fromWeekDays(WeekDay::MONDAY), TimeOfDay::fromString('08:00'), TimeOfDay::fromString('18:00'));
        $payload = Payload::fromArray(['orderId' => 42]);
        $modified = $message
            ->withSender($sender)
            ->withTags($tags)
            ->withCallbackUrl($callbackUrl)
            ->withScheduledAt($scheduledAt)
            ->withDeliveryWindow($deliveryWindow)
            ->withPayload($payload);
        self::assertNotSame($message, $modified);
        self::assertSame($sender, $modified->getSender());
        self::assertSame($tags, $modified->getTags());
        self::assertSame($callbackUrl, $modified->getCallbackUrl());
        self::assertSame($scheduledAt, $modified->getScheduledAt());
        self::assertSame($deliveryWindow, $modified->getDeliveryWindow());
        self::assertSame($payload, $modified->getPayload());
        self::assertNull($message->getSender());
        self::assertNull($message->getTags());
        self::assertNull($message->getCallbackUrl());
        self::assertNull($message->getScheduledAt());
        self::assertNull($message->getDeliveryWindow());
        self::assertNull($message->getPayload());
    }

    public function testSerializesSimpleMessageToRequestData(): void
    {
        $message = Message::create(MessageBody::fromString('Hello'), self::recipients());
        self::assertSame(['body' => 'Hello', 'to' => [['phone_number' => '420777123456']]], $message->toRequestData());
    }

    public function testSerializesTagsAsCommaSeparatedValue(): void
    {
        $message = Message::create(MessageBody::fromString('Hello'), self::recipients())
            ->withTags(TagList::fromTags(Tag::transactional(), Tag::fromString('winter-sale')));
        self::assertSame(
            ['body' => 'Hello', 'to' => [['phone_number' => '420777123456']], 'tag' => 'transactional,winter-sale'],
            $message->toRequestData()
        );
    }

    public function testSerializesFlowMessageToRequestData(): void
    {
        $flow = FlowStepList::fromFlowSteps(
            SmsFlowStep::create(MessageBody::fromString('First')),
            SmsFlowStep::create(MessageBody::fromString('Second'))
        );
        self::assertSame(
            ['to' => [['phone_number' => '420777123456']], 'flow' => [['sms' => ['body' => 'First', 'gateway' => 'high', 'type' => 'utf']], ['sms' => ['body' => 'Second', 'gateway' => 'high', 'type' => 'utf']]]],
            Message::createWithFlow($flow, self::recipients())->toRequestData()
        );
    }

    private static function recipients(): RecipientList
    {
        return RecipientList::fromPhoneNumbers(PhoneNumber::fromString('420777123456'));
    }
}
