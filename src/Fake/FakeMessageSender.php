<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Fake;

use DateTimeImmutable;
use Neatous\SmsManager\Acceptance\AcceptedRecipient;
use Neatous\SmsManager\Acceptance\AcceptedRecipientList;
use Neatous\SmsManager\Acceptance\MessageAcceptance;
use Neatous\SmsManager\Acceptance\MessageId;
use Neatous\SmsManager\Acceptance\RejectedRecipientList;
use Neatous\SmsManager\Acceptance\RequestId;
use Neatous\SmsManager\Message\Flow\SmsFlowStep;
use Neatous\SmsManager\Message\Message;
use Neatous\SmsManager\Message\MessageBody;
use Neatous\SmsManager\MessageSender;

final class FakeMessageSender implements MessageSender
{

    private const int DIRECTORY_PERMISSIONS = 0777;

    private string $journalDirectory;

    public function __construct(string $journalDirectory)
    {
        $this->journalDirectory = $journalDirectory;
    }

    public function send(Message $message): MessageAcceptance
    {
        return $this->writeJournal($message, false);
    }

    public function sendPriority(Message $message): MessageAcceptance
    {
        return $this->writeJournal($message, true);
    }

    private function writeJournal(Message $message, bool $priority): MessageAcceptance
    {
        $requestId = RequestId::fromString(self::generateUuid());
        $sentAt = new DateTimeImmutable();
        $body = self::resolveBody($message);
        $acceptedRecipients = [];
        $recipientIndex = 0;

        foreach ($message->getRecipients() as $phoneNumber) {
            $messageId = MessageId::fromString(self::generateUuid());
            $this->writeEntry(
                JournalEntry::create(
                    $sentAt,
                    $requestId,
                    $messageId,
                    $phoneNumber,
                    $body,
                    $message->getSender(),
                    $message->getTags(),
                    $priority,
                    $message->getPayload()
                )
            );
            $acceptedRecipients[] = AcceptedRecipient::create($recipientIndex, $messageId);
            $recipientIndex++;
        }

        return MessageAcceptance::create(
            $requestId,
            AcceptedRecipientList::fromAcceptedRecipients(...$acceptedRecipients),
            RejectedRecipientList::fromRejectedRecipients()
        );
    }

    private function writeEntry(JournalEntry $entry): void
    {
        $this->createJournalDirectory();

        $file = $this->journalDirectory . DIRECTORY_SEPARATOR . $entry->getMessageId()->getValue() . '.json';
        $temporaryFile = $file . '.tmp';

        if (@file_put_contents($temporaryFile, $entry->toJson()) === false) {
            throw new \Neatous\SmsManager\Exception\JournalException(sprintf('Journal entry cannot be written to "%s".', $temporaryFile));
        }

        if (!@rename($temporaryFile, $file)) {
            throw new \Neatous\SmsManager\Exception\JournalException(sprintf('Journal entry cannot be renamed to "%s".', $file));
        }
    }

    private function createJournalDirectory(): void
    {
        if (is_dir($this->journalDirectory)) {
            return;
        }

        if (!@mkdir($this->journalDirectory, self::DIRECTORY_PERMISSIONS, true) && !is_dir($this->journalDirectory)) {
            throw new \Neatous\SmsManager\Exception\JournalException(sprintf('Journal directory "%s" cannot be created.', $this->journalDirectory));
        }
    }

    private static function resolveBody(Message $message): MessageBody
    {
        $body = $message->getBody();

        if ($body !== null) {
            return $body;
        }

        $flow = $message->getFlow();

        if ($flow !== null) {
            foreach ($flow as $flowStep) {
                if (!$flowStep instanceof SmsFlowStep) {
                    continue;
                }

                $stepBody = $flowStep->getBody();

                if ($stepBody !== null) {
                    return $stepBody;
                }
            }
        }

        throw new \Neatous\SmsManager\Exception\InvalidFlowException('Message does not provide any body to write into the journal.');
    }

    private static function generateUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0F | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3F | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
