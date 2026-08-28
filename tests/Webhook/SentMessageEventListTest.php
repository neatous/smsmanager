<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Webhook;

use DateTimeInterface;
use Neatous\SmsManager\Webhook\Channel;
use Neatous\SmsManager\Webhook\DeliveryResult;
use Neatous\SmsManager\Webhook\SentMessageEvent;
use Neatous\SmsManager\Webhook\SentMessageEventList;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SentMessageEventListTest extends TestCase
{

    public function testParsesDeliveredEvent(): void
    {
        $event = self::firstEvent(
            '[{"request_id":"f1a1b0f0-1b1a-4f0e-9c1a-0f0e1b1a4f0e","message_id":"e27ff0ac-87b5-4e1d-b644-5fc6029e2a11","gateway":"sms","timestamp":1700000000,"type":"outgoing","to":{"phone_number":"420777123456"},"result":"delivered","result_info":"[0] Delivered","payload":{"order_id":"A-1"}}]'
        );
        self::assertSame('e27ff0ac-87b5-4e1d-b644-5fc6029e2a11', $event->getMessageId()->getValue());
        self::assertSame(Channel::SMS, $event->getChannel());
        self::assertSame('2023-11-14T22:13:20+00:00', $event->getOccurredAt()->format(DateTimeInterface::ATOM));
        self::assertSame('420777123456', $event->getPhoneNumber()->getValue());
        self::assertSame(DeliveryResult::DELIVERED, $event->getResult());
        $resultInfo = $event->getResultInfo();
        self::assertNotNull($resultInfo);
        self::assertSame(0, $resultInfo->getCode());
        self::assertSame('Delivered', $resultInfo->getDescription());
        self::assertSame('A-1', $event->getPayload()?->get('order_id'));
    }

    public function testParsesRejectedEventWithInsufficientCredit(): void
    {
        $event = self::firstEvent(
            '[{"request_id":"f1a1b0f0-1b1a-4f0e-9c1a-0f0e1b1a4f0e","message_id":"e27ff0ac-87b5-4e1d-b644-5fc6029e2a11","gateway":"sms","timestamp":1700000000,"type":"outgoing","to":{"phone_number":"420777123456"},"result":"rejected","result_info":"[307] Insufficient credit"}]'
        );
        self::assertSame(DeliveryResult::REJECTED, $event->getResult());
        self::assertTrue($event->getResultInfo()?->isInsufficientCredit());
        self::assertSame('e27ff0ac-87b5-4e1d-b644-5fc6029e2a11:rejected', $event->getDeduplicationKey());
    }

    public function testParsesAllEventsOfSinglePost(): void
    {
        $events = SentMessageEventList::fromJson(
            '[{"request_id":"r-1","message_id":"m-1","gateway":"sms","timestamp":1700000000,"type":"outgoing","to":{"phone_number":"420777123456"},"result":"sent"},'
            . '{"request_id":"r-1","message_id":"m-1","gateway":"sms","timestamp":1700000060,"type":"outgoing","to":{"phone_number":"420777123456"},"result":"delivered"}]'
        );
        self::assertCount(2, $events);
        self::assertTrue($events->isNotEmpty());
    }

    public function testParsesSmsChannelDetail(): void
    {
        $channelDetail = self::firstEvent(
            '[{"request_id":"r-1","message_id":"m-1","gateway":"sms","timestamp":1700000000,"type":"outgoing","to":{"phone_number":"420777123456"},"result":"delivered",'
            . '"sms":{"sender":"NEATOUS","country":230,"operator":1,"price_czk":1.5,"price_eur":0.06,"count":2,"gateway":"high"}}]'
        )->getChannelDetail();
        self::assertNotNull($channelDetail);
        self::assertSame('NEATOUS', $channelDetail->getSender());
        self::assertSame(230, $channelDetail->getCountryCode());
        self::assertSame(1, $channelDetail->getOperatorCode());
        self::assertSame(1.5, $channelDetail->getPriceCzk());
        self::assertSame(0.06, $channelDetail->getPriceEur());
        self::assertSame(2, $channelDetail->getPartCount());
        self::assertSame('high', $channelDetail->getSmsGateway());
    }

    public function testReadsWhatsAppTextDetailFromWhatsAppBodyKey(): void
    {
        $event = self::firstEvent(
            '[{"request_id":"r-1","message_id":"m-1","gateway":"whatsapp_text","timestamp":1700000000,"type":"outgoing","to":{"phone_number":"420777123456"},"result":"seen",'
            . '"whatsapp_body":{"sender":"NEATOUS","count":1}}]'
        );
        self::assertSame(Channel::WHATSAPP_TEXT, $event->getChannel());
        self::assertSame('NEATOUS', $event->getChannelDetail()?->getSender());
    }

    public function testOptionalPartsOfEventAreNull(): void
    {
        $event = self::firstEvent(
            '[{"request_id":"r-1","message_id":"m-1","gateway":"sms","timestamp":1700000000,"type":"outgoing","to":{"phone_number":"420777123456"},"result":"sending"}]'
        );
        self::assertNull($event->getChannelDetail());
        self::assertNull($event->getResultInfo());
        self::assertNull($event->getPayload());
    }

    public function testEmptyWebhookBodyProducesEmptyList(): void
    {
        self::assertTrue(SentMessageEventList::fromJson('[]')->isEmpty());
    }

    public function testEventWithoutTypeIsReadAsOutgoing(): void
    {
        $event = self::firstEvent(
            '[{"request_id":"r-1","message_id":"m-1","gateway":"sms","timestamp":1700000000,"to":{"phone_number":"420777123456"},"result":"delivered"}]'
        );
        self::assertSame('m-1', $event->getMessageId()->getValue());
    }

    public function testSkipsEventsOfOtherTypes(): void
    {
        $events = SentMessageEventList::fromJson(
            '[{"request_id":"r-1","message_id":"m-1","gateway":"sms","timestamp":1700000000,"type":"incoming","to":{"phone_number":"420777123456"},"text":"YES"},'
            . '{"request_id":"r-2","message_id":"m-2","gateway":"sms","timestamp":1700000060,"type":"outgoing","to":{"phone_number":"420777123456"},"result":"delivered"}]'
        );
        self::assertCount(1, $events);
        self::assertSame('m-2', self::firstEventOf($events)->getMessageId()->getValue());
    }

    public function testWebhookBodyWithOnlyOtherTypesProducesEmptyList(): void
    {
        $events = SentMessageEventList::fromJson(
            '[{"gateway":"sms","timestamp":1700000000,"type":"incoming","to":{"phone_number":"420777123456"},"text":"YES"},'
            . '{"gateway":"sms","timestamp":1700000060,"type":"incomingReply","to":{"phone_number":"420777123456"},"text":"NO"}]'
        );
        self::assertTrue($events->isEmpty());
        self::assertCount(0, $events);
    }

    #[DataProvider('provideInvalidWebhookBodies')]
    public function testRejectsInvalidWebhookBody(string $webhookBody): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidWebhookPayloadException::class);
        SentMessageEventList::fromJson($webhookBody);
    }

    /** @return iterable<string, array{string}> */
    public static function provideInvalidWebhookBodies(): iterable
    {
        yield 'unparseable json' => ['not a json'];
        yield 'object root' => [
            '{"request_id":"r-1","message_id":"m-1","gateway":"sms","timestamp":1700000000,"type":"outgoing","to":{"phone_number":"420777123456"},"result":"delivered"}',
        ];

        yield 'unknown result' => [
            '[{"request_id":"r-1","message_id":"m-1","gateway":"sms","timestamp":1700000000,"type":"outgoing","to":{"phone_number":"420777123456"},"result":"expired"}]',
        ];

        yield 'unknown gateway' => [
            '[{"request_id":"r-1","message_id":"m-1","gateway":"telegram","timestamp":1700000000,"type":"outgoing","to":{"phone_number":"420777123456"},"result":"delivered"}]',
        ];

        yield 'missing message id' => [
            '[{"request_id":"r-1","gateway":"sms","timestamp":1700000000,"type":"outgoing","to":{"phone_number":"420777123456"},"result":"delivered"}]',
        ];

        yield 'non integer timestamp' => [
            '[{"request_id":"r-1","message_id":"m-1","gateway":"sms","timestamp":"1700000000","type":"outgoing","to":{"phone_number":"420777123456"},"result":"delivered"}]',
        ];

        yield 'outgoing event without result' => [
            '[{"request_id":"r-1","message_id":"m-1","gateway":"sms","timestamp":1700000000,"type":"outgoing","to":{"phone_number":"420777123456"}}]',
        ];

        yield 'non string type' => [
            '[{"request_id":"r-1","message_id":"m-1","gateway":"sms","timestamp":1700000000,"type":7,"to":{"phone_number":"420777123456"},"result":"delivered"}]',
        ];

        yield 'null type' => [
            '[{"request_id":"r-1","message_id":"m-1","gateway":"sms","timestamp":1700000000,"type":null,"to":{"phone_number":"420777123456"},"result":"delivered"}]',
        ];
    }

    private static function firstEvent(string $webhookBody): SentMessageEvent
    {
        return self::firstEventOf(SentMessageEventList::fromJson($webhookBody));
    }

    private static function firstEventOf(SentMessageEventList $events): SentMessageEvent
    {
        foreach ($events as $event) {
            return $event;
        }

        self::fail('The webhook body did not produce any event.');
    }
}
