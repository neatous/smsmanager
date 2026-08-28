<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Acceptance;

final readonly class MessageAcceptance
{

    private RequestId $requestId;

    private AcceptedRecipientList $acceptedRecipients;

    private RejectedRecipientList $rejectedRecipients;

    private function __construct(
        RequestId $requestId,
        AcceptedRecipientList $acceptedRecipients,
        RejectedRecipientList $rejectedRecipients,
    )
    {
        $this->requestId = $requestId;
        $this->acceptedRecipients = $acceptedRecipients;
        $this->rejectedRecipients = $rejectedRecipients;
    }

    public static function create(
        RequestId $requestId,
        AcceptedRecipientList $acceptedRecipients,
        RejectedRecipientList $rejectedRecipients,
    ): self
    {
        return new self($requestId, $acceptedRecipients, $rejectedRecipients);
    }

    public function getRequestId(): RequestId
    {
        return $this->requestId;
    }

    public function getAcceptedRecipients(): AcceptedRecipientList
    {
        return $this->acceptedRecipients;
    }

    public function getRejectedRecipients(): RejectedRecipientList
    {
        return $this->rejectedRecipients;
    }

    public function isFullyAccepted(): bool
    {
        return $this->acceptedRecipients->isNotEmpty() && $this->rejectedRecipients->isEmpty();
    }

    public function hasRejectedRecipients(): bool
    {
        return $this->rejectedRecipients->isNotEmpty();
    }

    public function getSingleMessageId(): MessageId
    {
        if ($this->rejectedRecipients->isNotEmpty()) {
            throw new \Neatous\SmsManager\Exception\MessageNotAcceptedException(
                sprintf(
                    'Message was rejected for %d of %d recipients, request "%s".',
                    $this->rejectedRecipients->count(),
                    $this->rejectedRecipients->count() + $this->acceptedRecipients->count(),
                    $this->requestId->getValue()
                )
            );
        }

        if ($this->acceptedRecipients->count() > 1) {
            throw new \LogicException(
                sprintf(
                    'Acceptance of request "%s" holds %d accepted recipients, a single message id is not available.',
                    $this->requestId->getValue(),
                    $this->acceptedRecipients->count()
                )
            );
        }

        foreach ($this->acceptedRecipients as $acceptedRecipient) {
            return $acceptedRecipient->getMessageId();
        }

        throw new \Neatous\SmsManager\Exception\MessageNotAcceptedException(
            sprintf('Acceptance of request "%s" holds no accepted recipient.', $this->requestId->getValue())
        );
    }
}
