<h1 align="center">
	<img src="docs/img/neatous.png" alt="Logo" height="120" />
	<br/>
	SmsManager
</h1>
<p align="center">
    PHP client for the SmsManager JSON API v2.
</p>

A strictly typed client for [SmsManager](https://smsmanager.cz), a messaging platform for SMS, Viber, WhatsApp and RCS. Of the [JSON API v2](https://smsmanager.cz/docs/api-reference/json-v2/overview) the library covers sending over `/message` and `/message/priority` and parsing of the `sentMessage` delivery webhooks. On top of that it ships a fake sender with a local journal for development. API keys are managed in the [account settings](https://app.smsmanager.com/api-cloud).

## Installation

```shell
composer require neatous/smsmanager
```

Requires PHP 8.4+ and any [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client with [PSR-17](https://www.php-fig.org/psr/psr-17/) factories (for example `guzzlehttp/guzzle`).

## Sending messages

```php
use Neatous\SmsManager\ApiClient;
use Neatous\SmsManager\ApiKey;
use Neatous\SmsManager\Message\Message;
use Neatous\SmsManager\Message\MessageBody;
use Neatous\SmsManager\Message\PhoneNumber;
use Neatous\SmsManager\Message\RecipientList;
use Neatous\SmsManager\Message\Sender;
use Neatous\SmsManager\Message\Tag;
use Neatous\SmsManager\Message\TagList;

$httpFactory = new GuzzleHttp\Psr7\HttpFactory();
$messageSender = new ApiClient(
    ApiKey::fromString($apiKey),
    new GuzzleHttp\Client(),
    $httpFactory,
    $httpFactory,
);

$message = Message::create(
    MessageBody::fromString('Your verification code is 123456'),
    RecipientList::fromPhoneNumbers(PhoneNumber::fromString('420777123456')),
)
    ->withSender(Sender::fromString('Mojefirma'))
    ->withTags(TagList::fromTags(Tag::transactional()));

$messageId = $messageSender->sendPriority($message)->getSingleMessageId();
```

`send()` posts to `/message`, `sendPriority()` to `/message/priority`, a priority queue for time critical messages such as one time passwords. Both accept the same `Message` with up to 10 recipients per call, `RecipientList` refuses more; for more recipients split them and call `send()` repeatedly, the batch `/messages` endpoint is not covered by this library.

Optional message features, each a `with*()` method returning a new `Message` instance:

- `withSender`: the sender, an alphanumeric name of up to 11 characters (letters and digits, words separated by a single space or hyphen) or a virtual number of 3 to 15 digits; without it the platform applies the account default
- `withTags`: tags for reporting; several tags are sent comma separated in the API. Three tags are special: `transactional` (`Tag::transactional()`), `priority` (`Tag::priority()`) and the default `promotional` (`Tag::promotional()`). Messages carrying the default `promotional` tag are checked against the account opt-out list.
- `withScheduledAt`: scheduled sending time (UTC on the wire, any timezone accepted)
- `withDeliveryWindow`: allowed delivery days and hours
- `withCallbackUrl`: the webhook callback URL for delivery events
- `withPayload`: custom values returned unchanged in every webhook event

### Channel flows

A flow is an ordered list of channel steps. The platform tries the first step; when the message does not reach the step's `ttl_condition` (`sent`, `delivered` or `seen`) within `ttl` minutes, it moves to the next step. The API supports SMS, Viber, WhatsApp and RCS steps; the library composes SMS steps only. The body is either shared (passed to `createWithFlow`) or defined by every step.

```php
use Neatous\SmsManager\Message\Flow\FlowStepList;
use Neatous\SmsManager\Message\Flow\SmsFlowStep;
use Neatous\SmsManager\Message\Flow\SmsGateway;
use Neatous\SmsManager\Message\Flow\TtlCondition;

$message = Message::createWithFlow(
    FlowStepList::fromFlowSteps(
        SmsFlowStep::create(
            gateway: SmsGateway::LOWCOST,
            ttlMinutes: 10,
            ttlCondition: TtlCondition::SENT,
        ),
        SmsFlowStep::create(gateway: SmsGateway::HIGH),
    ),
    RecipientList::fromPhoneNumbers(PhoneNumber::fromString('420777123456')),
    MessageBody::fromString('New products in our store!'),
);
```

A single step flow is also how the SMS channel itself is configured: the gateway (including `SmsGateway::TEST` from the testing ladder below), the encoding (`SmsEncoding::UTF` keeps unicode at 70 characters per part, `SmsEncoding::SMS` strips it and fits 160) and a per step sender. A message without a flow omits the `flow` field, and a step created without a gateway omits the `gateway` key. In both cases the gateway is left to the platform, which resolves it from the registered sender (a dedicated virtual number selects `direct` with its pricing) and otherwise routes and prices the message like `high`.

### Acceptance is not delivery

A successful call returns a `MessageAcceptance`: the platform accepted the request for processing and assigned every recipient a `message_id`. It says nothing about the message being sent. The real outcome, including rejections for insufficient credit, arrives later through the `sentMessage` webhook. Store the `message_id` values; they are the key for pairing webhook events:

```php
$acceptance = $messageSender->send($message);

foreach ($acceptance->getAcceptedRecipients() as $accepted) {
    // persist $accepted->getMessageId() for the recipient at $accepted->getRecipientIndex()
}
```

For a message with a single recipient, `getSingleMessageId()` returns the id directly and throws `MessageNotAcceptedException` when the recipient was rejected, so a refused submission cannot pass silently.

### Error handling

- Every exception thrown by the library extends `Neatous\SmsManager\Exception\SmsManagerException`, with one deliberate exception: calling `getSingleMessageId()` on an acceptance with several accepted recipients is a programming error and throws `LogicException`.
- Invalid input is refused while constructing the value objects (`InvalidPhoneNumberException`, `InvalidMessageBodyException`, ...), so an invalid message cannot even be assembled.
- Sending throws `ApiRequestFailedException` for transport failures and non-200 responses (it carries `getStatusCode()`) and `InvalidResponseException` for unparseable responses.
- Webhook parsing throws `InvalidWebhookException` only when the body is not a JSON array; an item that cannot be parsed is returned by `getUnparsedItems()` together with its `InvalidWebhookException`. `CallbackSecret::fromString()` throws `InvalidCallbackSecretException` for an empty secret.

## Receiving delivery webhooks

SmsManager posts a JSON array of [`sentMessage` events](https://smsmanager.cz/docs/api-reference/json-v2/sent-message-webhook) to your callback URL whenever the status of an outgoing message changes (`sent`, `delivered`, `rejected`, ...). Parse the raw request body:

```php
use Neatous\SmsManager\Webhook\SentMessageWebhook;

$webhook = SentMessageWebhook::fromJson($rawRequestBody);

foreach ($webhook->getEvents() as $event) {
    $event->getMessageId();          // pair with your stored message id
    $event->getResult();             // DeliveryResult enum with isFinal(), isFailure(), ...
    $event->getResultInfo()?->isInsufficientCredit();
    $event->getDeduplicationKey();   // message id + result
    $event->getPhoneNumber();        // recipient exactly as sent by SmsManager
}

foreach ($webhook->getUnparsedItems() as $item) {
    $item->getRawJson();             // the item that could not be parsed, as JSON
    $item->getException();           // InvalidWebhookException with the reason
}
```

An item that cannot be parsed does not fail the batch: it is returned in `getUnparsedItems()` with its JSON and the exception, and the other events of the batch are parsed normally. The library does not log or store anything. The recipient phone number of an event is a plain string without format validation, so its format never makes an item unparsable. `fromJson()` throws `InvalidWebhookException` only when the body itself is not a JSON array.

`DeliveryResult::isFinal()` returns true for `delivered`, although Viber and WhatsApp messages may later report `seen`. Some operators never report delivery, so `sent` can be the last event of a message.

One callback URL receives every webhook, and SmsManager can combine several events into a single POST, including incoming messages and incoming replies (`incomingMessage`, `incomingReplyMessage`) mixed with delivery reports in the same batch. The `type` field is the message direction, not the event name: incoming events carry `type` `incoming` and no `result` field, so `fromJson()` reads only `outgoing` events, the `sentMessage` delivery reports, and silently skips the rest: skipped events appear neither in `getEvents()` nor in `getUnparsedItems()`. An event without a `type` field is read as `outgoing`, and a batch of nothing but skipped events yields an empty list of events.

Webhook delivery semantics:

- SmsManager waits at most 5 seconds for a response. Only a 2xx status counts as success and the response body is ignored. Redirects are not followed and count as a failure.
- A failed delivery is retried with growing delays (about 30 s, 60 s, 120 s and 240 s), at most 5 attempts in total, after which the event is dropped.
- Every request carries an `X-Request-Id` header that stays the same across retries of the same delivery.
- Events may arrive more than once and out of order. SmsManager documents that a logical event is identified by the `message_id` and `result` pair; `getDeduplicationKey()` returns that pair as `<message id>:<result>`, and `getOccurredAt()` carries the event time for ordering.
- Insufficient credit is reported here, as `rejected` with `[307] Insufficient credit`, not in the response to the send call. Credit is charged at send time, so a scheduled message with no credit at the scheduled moment is rejected.

### Webhook signatures

SmsManager signs webhook requests when the API key has `default_callback_secret` set, which is configured through the REST API (`POST https://rest-api.smsmngr.com/v1/apikey/update`). The `X-Request-Signature` header then carries the lowercase hex HMAC-SHA256 of the raw request body, keyed by the secret. Without a secret no header is sent, and GET callbacks are never signed. `CallbackSecret` computes and compares the signature in constant time, accepting the hex digits in any case:

```php
use Neatous\SmsManager\Webhook\CallbackSecret;

$isValid = CallbackSecret::fromString($callbackSecret)
    ->isValidSignature($rawRequestBody, $_SERVER['HTTP_X_REQUEST_SIGNATURE'] ?? '');
```

`CallbackSecret` keeps the secret byte exact, rejects an empty or whitespace only value and masks itself in `var_dump()` output.

## Development without the real API

`FakeMessageSender` implements the same `MessageSender` interface as `ApiClient` but writes every message into a JSON file journal instead of calling the network:

```php
use Neatous\SmsManager\Fake\FakeMessageSender;
use Neatous\SmsManager\Fake\MessageJournal;

$messageSender = new FakeMessageSender(__DIR__ . '/log/smsmanager');
$journal = new MessageJournal(__DIR__ . '/log/smsmanager');
$entries = $journal->getLatestEntries(20);
```

List the journal from a terminal:

```shell
vendor/bin/smsmanager journal log/smsmanager
```

`WebhookSimulator` builds a byte-compatible `sentMessage` webhook body from a journal entry, so the whole delivery cycle, including branches that are hard to trigger for real, can be tested locally:

```php
use Neatous\SmsManager\Fake\WebhookSimulator;
use Neatous\SmsManager\Webhook\DeliveryResult;
use Neatous\SmsManager\Webhook\ResultInfo;

foreach ($journal->getLatestEntries(1) as $entry) {
    $body = (new WebhookSimulator())->createWebhookBody(
        $entry,
        DeliveryResult::REJECTED,
        ResultInfo::fromString('[307] Insufficient credit'),
    );
    // post $body to your own webhook endpoint
}
```

### Testing ladder

The mock API, the test number and the test gateway come from the [official testing guide](https://smsmanager.cz/docs/guides/testing).

| Level | Delivers messages | Webhooks | Cost | Use for |
| --- | --- | --- | --- | --- |
| `FakeMessageSender` | no | via `WebhookSimulator` | free, offline | everyday development, application tests |
| Mock API `https://api-mock.smsmngr.com/v2` | no | no | free | request assembly against real HTTP, pass `BaseUri::fromString(...)` to `ApiClient` |
| Test number `420777777777` | no | no | free | authentication and request validation against the production API |
| `SmsGateway::TEST` flow gateway | no | yes, platform generated statuses posted to your callback URL | 0.01 CZK per message | end to end verification including webhook processing |
| Production sending | yes | yes | per price list | real traffic |

## Nette integration

```neon
extensions:
    smsManager: Neatous\SmsManager\Bridges\NetteDI\SmsManagerExtension

smsManager:
    apiKey: %smsManager.apiKey%
    fake: %debugMode%
    journalDir: %rootDir%/log/smsmanager
```

The extension registers a `MessageSender` service (an `ApiClient`, or a `FakeMessageSender` when `fake` is enabled) and a `MessageSenderFactory` for applications that manage multiple API keys and create senders per key at runtime. The PSR-18 client and PSR-17 factories are taken from your container by autowiring, so register your HTTP client there (a Guzzle client covers both roles). Fake mode also registers a `MessageJournal` service, and when Tracy is available, a bar panel with the latest journalled messages is added automatically. Optional keys: `baseUri` (target the mock API), `panel` (disable the Tracy panel).

## Security notes

- The API key is sent in the `x-api-key` header, always over https (`BaseUri` refuses anything else); `ApiKey` masks itself in `var_dump()` output and never appears in exception messages. Tracy's dumper ignores `__debugInfo` unless its `debuginfo` option is enabled, so a dumped `ApiClient` shows the key in a Tracy bar or bluescreen.
- The library does not configure timeouts; the injected PSR-18 client keeps its own.
- The library never retries a request.
- The sending API answers a missing API key with status 401 and an unknown API key with status 403 (the official error reference lists 400 for both), which surfaces as `ApiRequestFailedException` with the respective `getStatusCode()`.

## Not covered

- the batch `/messages` and legacy `/simple/*` endpoints
- flow steps for channels other than SMS (webhook parsing understands all channels)
- the `params` field (link shortening) and the object form of `callback` (custom webhook formats)
- incoming message webhooks
