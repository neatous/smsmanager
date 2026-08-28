<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Acceptance;

use Neatous\SmsManager\Acceptance\AcceptedRecipient;
use Neatous\SmsManager\Acceptance\AcceptedRecipientList;
use Neatous\SmsManager\Acceptance\MessageAcceptance;
use Neatous\SmsManager\Acceptance\MessageId;
use Neatous\SmsManager\Acceptance\RejectedRecipient;
use Neatous\SmsManager\Acceptance\RejectedRecipientList;
use Neatous\SmsManager\Acceptance\RequestId;
use PHPUnit\Framework\TestCase;

final class MessageAcceptanceTest extends TestCase
{

    public function testFullyAcceptedSingleRecipient(): void
    {
        $messageId = MessageId::fromString('m-1');
        $acceptance = self::acceptance(
            AcceptedRecipientList::fromAcceptedRecipients(AcceptedRecipient::create(0, $messageId)),
            RejectedRecipientList::fromRejectedRecipients()
        );
        self::assertSame('r-1', $acceptance->getRequestId()->getValue());
        self::assertTrue($acceptance->isFullyAccepted());
        self::assertFalse($acceptance->hasRejectedRecipients());
        self::assertSame($messageId, $acceptance->getSingleMessageId());
    }

    public function testRejectedRecipientsPreventSingleMessageId(): void
    {
        $acceptance = self::acceptance(
            AcceptedRecipientList::fromAcceptedRecipients(AcceptedRecipient::create(0, MessageId::fromString('m-1'))),
            RejectedRecipientList::fromRejectedRecipients(RejectedRecipient::create(1))
        );
        self::assertFalse($acceptance->isFullyAccepted());
        self::assertTrue($acceptance->hasRejectedRecipients());
        $this->expectException(\Neatous\SmsManager\Exception\MessageNotAcceptedException::class);
        $acceptance->getSingleMessageId();
    }

    public function testMultipleAcceptedRecipientsPreventSingleMessageId(): void
    {
        $acceptance = self::acceptance(
            AcceptedRecipientList::fromAcceptedRecipients(
                AcceptedRecipient::create(0, MessageId::fromString('m-1')),
                AcceptedRecipient::create(1, MessageId::fromString('m-2'))
            ),
            RejectedRecipientList::fromRejectedRecipients()
        );
        self::assertTrue($acceptance->isFullyAccepted());
        $this->expectException(\LogicException::class);
        $acceptance->getSingleMessageId();
    }

    public function testEmptyAcceptanceIsNotFullyAccepted(): void
    {
        $acceptance = self::acceptance(AcceptedRecipientList::fromAcceptedRecipients(), RejectedRecipientList::fromRejectedRecipients());
        self::assertFalse($acceptance->isFullyAccepted());
        $this->expectException(\Neatous\SmsManager\Exception\MessageNotAcceptedException::class);
        $acceptance->getSingleMessageId();
    }

    public function testCreatesFullyAcceptedAcceptanceFromJson(): void
    {
        $acceptance = MessageAcceptance::fromJson(
            '{"request_id":"req-1","accepted":[{"key":"0","message_id":"m-1"},{"key":"1","message_id":"m-2"}],"rejected":[]}'
        );
        self::assertSame('req-1', $acceptance->getRequestId()->getValue());
        self::assertCount(2, $acceptance->getAcceptedRecipients());
    }

    public function testCreatesPartiallyAcceptedAcceptanceFromJson(): void
    {
        $acceptance = MessageAcceptance::fromJson('{"request_id":"req-1","accepted":[{"key":"1","message_id":"m-2"}],"rejected":[{"key":"0"}]}');
        $accepted = $acceptance->getAcceptedRecipients()->getByRecipientIndex(1);
        self::assertNotNull($accepted);
        self::assertSame('m-2', $accepted->getMessageId()->getValue());
        self::assertNotNull($acceptance->getRejectedRecipients()->getByRecipientIndex(0));
        self::assertCount(1, $acceptance->getAcceptedRecipients());
        self::assertCount(1, $acceptance->getRejectedRecipients());
    }

    public function testCreatesEmptyAcceptanceFromJsonWithoutRecipientSections(): void
    {
        $acceptance = MessageAcceptance::fromJson('{"request_id":"req-1"}');
        self::assertTrue($acceptance->getAcceptedRecipients()->isEmpty());
        self::assertTrue($acceptance->getRejectedRecipients()->isEmpty());
    }

    public function testRejectsUnparseableJson(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidResponseException::class);
        MessageAcceptance::fromJson('not a json');
    }

    public function testRejectsJsonWithoutRequestId(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidResponseException::class);
        MessageAcceptance::fromJson('{"accepted":[]}');
    }

    public function testRejectsAcceptedRecipientWithoutMessageId(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidResponseException::class);
        MessageAcceptance::fromJson('{"request_id":"req-1","accepted":[{"key":"0"}]}');
    }

    public function testRejectsRecipientWithNonNumericKey(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidResponseException::class);
        MessageAcceptance::fromJson('{"request_id":"req-1","rejected":[{"key":"first"}]}');
    }

    public function testRejectsNonArrayRecipientSection(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidResponseException::class);
        MessageAcceptance::fromJson('{"request_id":"req-1","accepted":"none"}');
    }

    private static function acceptance(
        AcceptedRecipientList $accepted,
        RejectedRecipientList $rejected,
    ): MessageAcceptance
    {
        return MessageAcceptance::create(RequestId::fromString('r-1'), $accepted, $rejected);
    }
}
