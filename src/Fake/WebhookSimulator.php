<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Fake;

use Neatous\SmsManager\Webhook\Channel;
use Neatous\SmsManager\Webhook\DeliveryResult;
use Neatous\SmsManager\Webhook\ResultInfo;

final readonly class WebhookSimulator
{

    private const string EVENT_TYPE = 'outgoing';

    public function createWebhookBody(
        JournalEntry $entry,
        DeliveryResult $result,
        ?ResultInfo $resultInfo = null,
    ): string
    {
        $event = [];
        $event['request_id'] = $entry->getRequestId()->getValue();
        $event['message_id'] = $entry->getMessageId()->getValue();
        $event['gateway'] = Channel::SMS->value;
        $event['timestamp'] = $entry->getSentAt()->getTimestamp();
        $event['type'] = self::EVENT_TYPE;
        $event['to'] = ['phone_number' => $entry->getPhoneNumber()->getValue()];
        $event['result'] = $result->value;

        if ($resultInfo !== null) {
            $event['result_info'] = $resultInfo->getValue();
        }

        $payload = $entry->getPayload();

        if ($payload !== null && !$payload->isEmpty()) {
            $event['payload'] = $payload->toArray();
        }

        try {
            return json_encode([$event], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $exception) {
            throw new \Neatous\SmsManager\Exception\JournalException('The simulated sentMessage webhook body cannot be encoded to JSON.', 0, $exception);
        }
    }
}
