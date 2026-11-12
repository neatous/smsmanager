<h1 align="center">
	<img src="docs/img/neatous.png" alt="Logo" height="120" />
	<br/>
	SmsManager
</h1>
<p align="center">
    PHP client for the SmsManager JSON API v2.
</p>

## What it is

A strictly typed PHP client for [SmsManager](https://smsmanager.cz), a messaging platform for SMS, Viber, WhatsApp and RCS.

Of the [JSON API v2](https://smsmanager.cz/docs/api-reference/json-v2/overview) the library covers:

- sending messages over `/message` and `/message/priority`,
- parsing of the `sentMessage` delivery webhooks (a webhook is an HTTP request that SmsManager sends to your application when something happens),
- a fake client with a local journal, so you can develop without sending anything.

API keys are managed in the [account settings](https://app.smsmanager.com/api-cloud).

## Requirements

- PHP 8.4 or newer with the `json` and `mbstring` extensions.
- A PSR-18 HTTP client. [PSR-18](https://www.php-fig.org/psr/psr-18/) is a standard PHP interface for sending HTTP requests. The library does not send requests itself; you give it any client that implements this interface.
- PSR-17 factories. [PSR-17](https://www.php-fig.org/psr/psr-17/) is a standard interface for creating HTTP request objects.

The package `guzzlehttp/guzzle` provides all of this: `GuzzleHttp\Client` is the PSR-18 client and `GuzzleHttp\Psr7\HttpFactory` is the PSR-17 factory. The examples below use Guzzle.

## Installation

```shell
composer require neatous/smsmanager guzzlehttp/guzzle
```

Leave out `guzzlehttp/guzzle` if your project already has another PSR-18 client and PSR-17 factories.

## Quick start

This script sends one SMS. Replace the API key and the phone number.

```php
<?php declare(strict_types = 1);

require __DIR__ . '/vendor/autoload.php';

use Neatous\SmsManager\ApiClient;
use Neatous\SmsManager\ApiKey;
use Neatous\SmsManager\Message\Message;
use Neatous\SmsManager\Message\MessageBody;
use Neatous\SmsManager\Message\PhoneNumber;
use Neatous\SmsManager\Message\RecipientList;

$httpClient = new GuzzleHttp\Client([
    'timeout' => 15,         // seconds for the whole request
    'connect_timeout' => 5,  // seconds for opening the connection
]);
$httpFactory = new GuzzleHttp\Psr7\HttpFactory();

$client = new ApiClient(
    ApiKey::fromString('your-api-key'),
    $httpClient,
    $httpFactory, // creates requests
    $httpFactory, // creates request bodies
);

$message = Message::create(
    MessageBody::fromString('Your verification code is 123456'),
    RecipientList::fromPhoneNumbers(PhoneNumber::fromString('420777123456')),
);

$messageId = $client->send($message)->getSingleMessageId();

echo $messageId->getValue() . PHP_EOL;
```

Notes:

- **Always set a timeout.** The library does not configure timeouts, and Guzzle has no total timeout by default. A request without a timeout can hang for a long time. The caller (a user, a queue worker, a cron job) then often gives up and tries again, although SmsManager may already have accepted the first request. The recipient gets the SMS twice. With a timeout the call ends with an exception after a known time and your code decides what to do.
- **Phone numbers** are written in the international format with the country code, for example `420777123456`. A leading `+` or `00`, spaces, dots, hyphens and parentheses are removed. The result must be 7 to 15 digits and must not start with `0`. The library cannot tell that a country code is missing, so always include it.
- **The message body** must be 1 to 1000 characters of valid UTF-8.
- Store the returned message id. You need it later to pair delivery reports with the message, see [Acceptance is not delivery](#acceptance-is-not-delivery).

## Sending messages

`ApiClient` has two methods. Both take the same `Message` and return a `MessageAcceptance`:

- `send()` posts to `/message`.
- `sendPriority()` posts to `/message/priority`, a priority queue for time critical messages such as one time passwords.

One message has 1 to 10 recipients. `RecipientList` refuses more, and it refuses duplicate numbers. For more recipients split them and call `send()` repeatedly; the batch `/messages` endpoint is not covered by this library.

### Optional message features

Each feature is a `with*()` method. It returns a new `Message` and leaves the original unchanged, so use the returned value.

```php
<?php declare(strict_types = 1);

use Neatous\SmsManager\Message\CallbackUrl;
use Neatous\SmsManager\Message\DeliveryWindow;
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

$message = Message::create(
    MessageBody::fromString('Your order 42 is ready.'),
    RecipientList::fromPhoneNumbers(
        PhoneNumber::fromString('420777123456'),
        PhoneNumber::fromString('420777654321'),
    ),
)
    ->withSender(Sender::fromString('Mojefirma'))
    ->withTags(TagList::fromTags(Tag::transactional(), Tag::fromString('orders')))
    ->withScheduledAt(new DateTimeImmutable('2030-01-15 09:00:00', new DateTimeZone('Europe/Prague')))
    ->withDeliveryWindow(DeliveryWindow::create(
        WeekDayList::fromWeekDays(WeekDay::MONDAY, WeekDay::TUESDAY, WeekDay::WEDNESDAY, WeekDay::THURSDAY, WeekDay::FRIDAY),
        TimeOfDay::fromString('08:00'),
        TimeOfDay::fromString('20:00'),
        new DateTimeZone('Europe/Prague'),
    ))
    ->withCallbackUrl(CallbackUrl::fromString('https://example.com/smsmanager/webhook'))
    ->withPayload(Payload::fromArray(['orderId' => 42]));
```

| Method | Meaning |
| --- | --- |
| `withSender` | The sender shown to the recipient. Either an alphanumeric name of up to 11 characters (letters and digits, words separated by a single space or hyphen) or a virtual number of 3 to 15 digits. |
| `withTags` | Tags for reporting. A tag may contain letters, digits, `.`, `_` and `-`; it is converted to lower case. Several tags are sent comma separated. Three tags are special: `Tag::transactional()`, `Tag::priority()` and the default `Tag::promotional()`. Messages carrying the default `promotional` tag are checked against the account opt-out list. |
| `withScheduledAt` | Scheduled sending time. Any timezone is accepted; the library sends it in UTC. |
| `withDeliveryWindow` | Allowed delivery days and hours. At least one of days, hours and timezone must be given. Start and end time must be given together, in the `HH:MM` format. |
| `withCallbackUrl` | The URL for delivery webhooks of this message. It must be an absolute `https` URL. |
| `withPayload` | Custom values returned unchanged in every webhook event of this message. Keys are non-empty strings; values are `int`, `float`, `string` or `bool`. |

### Default sender

When a message has no sender, the client applies its default sender, if it has one. The default sender is the last constructor argument of `ApiClient`, `ApiClientFactory`, `FakeClient` and `FakeClientFactory` (or the `defaultSender` option of the Nette extension):

```php
<?php declare(strict_types = 1);

use Neatous\SmsManager\ApiClient;
use Neatous\SmsManager\ApiKey;
use Neatous\SmsManager\Message\Sender;

$httpFactory = new GuzzleHttp\Psr7\HttpFactory();

$client = new ApiClient(
    ApiKey::fromString('your-api-key'),
    new GuzzleHttp\Client(['timeout' => 15, 'connect_timeout' => 5]),
    $httpFactory,
    $httpFactory,
    null, // base URI, null means the production API
    Sender::fromString('Mojefirma'),
);
```

A sender set by `withSender()` always wins. Without any sender the platform applies the account default.

### Channel flows

A flow is an ordered list of channel steps. The platform tries the first step. When the message does not reach the step's `ttl_condition` (`sent`, `delivered` or `seen`) within `ttl` minutes, the platform moves to the next step. The API supports SMS, Viber, WhatsApp and RCS steps; the library composes SMS steps only.

```php
<?php declare(strict_types = 1);

use Neatous\SmsManager\Message\Flow\FlowStepList;
use Neatous\SmsManager\Message\Flow\SmsFlowStep;
use Neatous\SmsManager\Message\Flow\SmsGateway;
use Neatous\SmsManager\Message\Flow\TtlCondition;
use Neatous\SmsManager\Message\Message;
use Neatous\SmsManager\Message\MessageBody;
use Neatous\SmsManager\Message\PhoneNumber;
use Neatous\SmsManager\Message\RecipientList;

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

- The body is either shared (the third argument of `createWithFlow`) or defined by every step (`SmsFlowStep::create(body: ...)`). A flow without a shared body, where a step has no body, is refused with `InvalidFlowException`.
- `ttlMinutes` must be at least 0.5. `ttlCondition` defaults to `TtlCondition::SENT` and is sent only together with `ttlMinutes`.
- A single step flow is also how the SMS channel itself is configured: the gateway (`SmsGateway::HIGH`, `LOWCOST`, `DIRECT`, `CUSTOM`, `SIMHOST`, `GSM`, and `TEST` from the [testing ladder](#testing-ladder)), the encoding and a per step sender.
- The encoding defaults to `SmsEncoding::UTF`, which keeps unicode at 70 characters per SMS part. `SmsEncoding::SMS` strips unicode and fits 160 characters per part.
- A message without a flow omits the `flow` field, and a step created without a gateway omits the `gateway` key. In both cases the gateway is left to the platform. The platform resolves it from the registered sender (a dedicated virtual number selects `direct` with its pricing) and otherwise routes and prices the message like `high`.
- The client's default sender fills only the sender of the message, not the sender of a flow step.

## Acceptance is not delivery

A successful call returns a `MessageAcceptance`. It means that the platform accepted the request for processing and assigned every accepted recipient a `message_id`. It says nothing about the message being sent or delivered.

The real outcome arrives later through the `sentMessage` webhook. This includes rejections for insufficient credit. Store the `message_id` values; they are the key for pairing webhook events with your messages.

```php
<?php declare(strict_types = 1);

// $client and $message are the objects from the Quick start.
$acceptance = $client->send($message);

foreach ($acceptance->getAcceptedRecipients() as $accepted) {
    $recipientIndex = $accepted->getRecipientIndex();   // position in the recipient list, starting at 0
    $messageId = $accepted->getMessageId()->getValue(); // store it for this recipient
}

foreach ($acceptance->getRejectedRecipients() as $rejected) {
    $recipientIndex = $rejected->getRecipientIndex();   // this recipient was refused
}
```

Other helpers: `getRequestId()`, `isFullyAccepted()`, `hasRejectedRecipients()`.

For a message with a single recipient, `getSingleMessageId()` returns the id directly. It throws `MessageNotAcceptedException` when the recipient was rejected or nothing was accepted, so a refused submission cannot pass silently.

## Error handling

Every exception thrown by the library extends `Neatous\SmsManager\Exception\SmsManagerException`. There is one deliberate exception: calling `getSingleMessageId()` on an acceptance with several accepted recipients is a programming error and throws `LogicException`.

| When | Exception |
| --- | --- |
| Invalid input while building value objects | `InvalidPhoneNumberException`, `InvalidMessageBodyException`, `InvalidRecipientListException`, `InvalidSenderException`, `InvalidTagException`, `InvalidCallbackUrlException`, `InvalidDeliveryWindowException`, `InvalidPayloadException`, `InvalidFlowException`, `InvalidApiKeyException`, `InvalidBaseUriException`, `InvalidIdentifierException` (an empty message or request id, a negative recipient index) |
| The HTTP client failed (timeout, DNS, connection) | `ApiRequestFailedException`, `getStatusCode()` returns `0`, the original exception is in `getPrevious()` |
| The API answered with a status other than 200 | `ApiRequestFailedException`, `getStatusCode()` returns the HTTP status; the message contains the response body, shortened to 500 characters |
| The API answered 200 with a body that cannot be read | `InvalidResponseException` |
| `getSingleMessageId()` on a rejected message | `MessageNotAcceptedException` |
| Webhook body is not a JSON array | `InvalidWebhookException` |
| Empty or whitespace only webhook secret | `InvalidCallbackSecretException` |
| The fake journal cannot be written or read | `JournalException` |
| Invalid configuration of the Nette extension | `InvalidExtensionConfigurationException` |

Because invalid input is refused while constructing the value objects, an invalid message cannot even be assembled.

```php
<?php declare(strict_types = 1);

use Neatous\SmsManager\Exception\ApiRequestFailedException;
use Neatous\SmsManager\Exception\InvalidResponseException;
use Neatous\SmsManager\Exception\MessageNotAcceptedException;

// $client and $message are the objects from the Quick start.
try {
    $messageId = $client->send($message)->getSingleMessageId();
} catch (MessageNotAcceptedException $exception) {
    // The platform refused the recipient. Nothing will be sent.
} catch (ApiRequestFailedException $exception) {
    if ($exception->getStatusCode() === 0) {
        // Transport failure, for example a timeout. The outcome is unknown:
        // the platform may have accepted the message. Do not resend blindly.
    } else {
        // The API refused the request, for example 401 or 403 for a wrong API key.
    }
} catch (InvalidResponseException $exception) {
    // The API answered 200, but the answer cannot be read.
}
```

The library never retries a request. A retry is your decision.

## Receiving delivery webhooks

SmsManager posts a JSON array of [`sentMessage` events](https://smsmanager.cz/docs/api-reference/json-v2/sent-message-webhook) to your callback URL whenever the status of an outgoing message changes (`sent`, `delivered`, `rejected`, ...). A callback URL can be set per message with `withCallbackUrl()`.

A complete endpoint in plain PHP. It verifies the signature (explained [below](#webhook-signatures)), parses the body and answers. If your API key has no callback secret, requests are not signed; remove the signature check then.

```php
<?php declare(strict_types = 1);

require __DIR__ . '/vendor/autoload.php';

use Neatous\SmsManager\Exception\InvalidWebhookException;
use Neatous\SmsManager\Webhook\CallbackSecret;
use Neatous\SmsManager\Webhook\SentMessageWebhook;

$rawRequestBody = (string) file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_REQUEST_SIGNATURE'] ?? '';

if (!CallbackSecret::fromString('your-callback-secret')->isValidSignature($rawRequestBody, $signature)) {
    http_response_code(401);

    exit;
}

try {
    $webhook = SentMessageWebhook::fromJson($rawRequestBody);
} catch (InvalidWebhookException $exception) {
    http_response_code(400);

    exit;
}

foreach ($webhook->getEvents() as $event) {
    $messageId = $event->getMessageId()->getValue(); // pair with your stored message id
    $result = $event->getResult();                   // DeliveryResult enum
    $key = $event->getDeduplicationKey();            // "<message id>:<result>"

    if ($result->isFailure() && $event->getResultInfo()?->isInsufficientCredit() === true) {
        // top up the credit and send again
    }
}

foreach ($webhook->getUnparsedItems() as $item) {
    $item->getRawJson();                   // the item that could not be parsed, as JSON
    $item->getException()->getMessage();   // the reason
}

http_response_code(204);
```

What an event offers:

| Method | Returns |
| --- | --- |
| `getMessageId()`, `getRequestId()` | The ids from the acceptance. |
| `getResult()` | `DeliveryResult`: `SENDING`, `SENT`, `DELIVERED`, `SEEN`, `UNDELIVERED`, `REJECTED`, `FAILED`. Helpers: `isFinal()`, `isSuccessful()`, `isFailure()`, `isRejectedBeforeSending()`. |
| `getResultInfo()` | `ResultInfo` or `null`: `getValue()`, `getCode()`, `getDescription()` and `isInvalidPhoneNumber()` (302), `isNotInAllowList()` (303), `isBlacklisted()` (304), `isDuplicateMessage()` (306), `isInsufficientCredit()` (307). |
| `getOccurredAt()` | The event time in UTC. |
| `getPhoneNumber()` | The recipient exactly as sent by SmsManager, a plain string. |
| `getChannel()` | `Channel`: `SMS`, `VIBER`, `WHATSAPP_TEXT`, `WHATSAPP_TEMPLATE`, `RCS`. |
| `getChannelDetail()` | `ChannelDetail` or `null`: sender, country code, operator code, price in CZK and EUR, part count, SMS gateway. |
| `getPayload()` | The `Payload` you attached with `withPayload()`, or `null`. |
| `getDeduplicationKey()` | `<message id>:<result>`. |

Parsing rules:

- An item that cannot be parsed does not fail the batch. It is returned by `getUnparsedItems()` with its JSON and an `InvalidWebhookException`. The other events of the batch are parsed normally.
- `fromJson()` itself throws `InvalidWebhookException` only when the body is not a JSON array.
- The recipient phone number is not validated for format, so its format never makes an item unparsable. It only has to be a non-empty string.
- The library does not log or store anything.
- `DeliveryResult::isFinal()` returns true for `delivered`, although Viber and WhatsApp messages may later report `seen`. Some operators never report delivery, so `sent` can be the last event of a message.

One callback URL receives every webhook, and SmsManager can combine several events into a single POST. This includes incoming messages and incoming replies (`incomingMessage`, `incomingReplyMessage`) mixed with delivery reports in the same batch. The `type` field is the message direction, not the event name: incoming events carry `type` `incoming` and no `result` field. Therefore `fromJson()` reads only `outgoing` events, the `sentMessage` delivery reports, and silently skips the rest. Skipped events appear neither in `getEvents()` nor in `getUnparsedItems()`. An event without a `type` field is read as `outgoing`. A batch of nothing but skipped events yields an empty list of events.

Webhook delivery semantics of SmsManager:

- SmsManager waits at most 5 seconds for a response. Only a 2xx status counts as success and the response body is ignored. Redirects are not followed and count as a failure.
- A failed delivery is retried with growing delays (about 30 s, 60 s, 120 s and 240 s), at most 5 attempts in total. After that the event is dropped.
- Every request carries an `X-Request-Id` header that stays the same across retries of the same delivery.
- Events may arrive more than once and out of order. SmsManager documents that a logical event is identified by the `message_id` and `result` pair. `getDeduplicationKey()` returns that pair, and `getOccurredAt()` carries the event time for ordering.
- Insufficient credit is reported here, as `rejected` with `[307] Insufficient credit`, not in the response to the send call. Credit is charged at send time, so a scheduled message with no credit at the scheduled moment is rejected.

### Webhook signatures

A signature proves that a webhook request really comes from SmsManager.

SmsManager signs webhook requests when the API key has `default_callback_secret` set. The secret is configured through the REST API (`POST https://rest-api.smsmngr.com/v1/apikey/update`). The `X-Request-Signature` header then carries the lowercase hex HMAC-SHA256 of the raw request body, keyed by the secret. Without a secret no header is sent, and GET callbacks are never signed.

```php
<?php declare(strict_types = 1);

use Neatous\SmsManager\Webhook\CallbackSecret;

$rawRequestBody = (string) file_get_contents('php://input');

$isValid = CallbackSecret::fromString('your-callback-secret')
    ->isValidSignature($rawRequestBody, $_SERVER['HTTP_X_REQUEST_SIGNATURE'] ?? '');
```

- Verify the raw body exactly as received, before any JSON decoding.
- `CallbackSecret` computes and compares the signature in constant time and accepts the hex digits in any case.
- It keeps the secret byte exact, rejects an empty or whitespace only value and masks itself in `var_dump()` output.

## Development without the real API

Fake mode means that your application uses `FakeClient` instead of `ApiClient`. `FakeClient` implements the same `Client` interface, but it never calls the network. It writes every message into a journal. The journal is a directory of JSON files, one file per recipient.

```php
<?php declare(strict_types = 1);

require __DIR__ . '/vendor/autoload.php';

use Neatous\SmsManager\Fake\FakeClient;
use Neatous\SmsManager\Fake\MessageJournal;
use Neatous\SmsManager\Message\Message;
use Neatous\SmsManager\Message\MessageBody;
use Neatous\SmsManager\Message\PhoneNumber;
use Neatous\SmsManager\Message\RecipientList;

$journalDirectory = __DIR__ . '/log/smsmanager';

$client = new FakeClient($journalDirectory);
$client->send(Message::create(
    MessageBody::fromString('Your verification code is 123456'),
    RecipientList::fromPhoneNumbers(PhoneNumber::fromString('420777123456')),
));

$journal = new MessageJournal($journalDirectory);

foreach ($journal->getLatestEntries(20) as $entry) {
    echo $entry->getPhoneNumber()->getValue() . ': ' . $entry->getBody()->getValue() . PHP_EOL;
}
```

- `FakeClient` creates the directory when needed, accepts every recipient and returns generated ids.
- An entry holds the time, request id, message id, phone number, body, sender, tags, priority flag and payload.
- `MessageJournal` offers `getLatestEntries($limit)` (newest first), `countEntries()` and `clear()`.

List the journal from a terminal. The optional last argument is the number of entries, 20 by default:

```shell
vendor/bin/smsmanager journal log/smsmanager
vendor/bin/smsmanager journal log/smsmanager 50
```

### Simulating webhooks

`WebhookSimulator` builds a `sentMessage` webhook body from a journal entry. You can then test the whole delivery cycle locally, including branches that are hard to trigger for real:

```php
<?php declare(strict_types = 1);

require __DIR__ . '/vendor/autoload.php';

use Neatous\SmsManager\Fake\MessageJournal;
use Neatous\SmsManager\Fake\WebhookSimulator;
use Neatous\SmsManager\Webhook\DeliveryResult;
use Neatous\SmsManager\Webhook\ResultInfo;

$journal = new MessageJournal(__DIR__ . '/log/smsmanager');

foreach ($journal->getLatestEntries(1) as $entry) {
    $body = (new WebhookSimulator())->createWebhookBody(
        $entry,
        DeliveryResult::REJECTED,
        ResultInfo::fromString('[307] Insufficient credit'),
    );

    // post $body to your own webhook endpoint, or pass it to SentMessageWebhook::fromJson()
}
```

The third argument is optional. The simulated event is always an SMS event and carries the payload of the entry.

### Testing ladder

The mock API, the test number and the test gateway come from the [official testing guide](https://smsmanager.cz/docs/guides/testing).

| Level | Delivers messages | Webhooks | Cost | Use for |
| --- | --- | --- | --- | --- |
| `FakeClient` | no | via `WebhookSimulator` | free, offline | everyday development, application tests |
| Mock API `https://api-mock.smsmngr.com/v2` | no | no | free | request assembly against real HTTP |
| Test number `420777777777` | no | no | free | authentication and request validation against the production API |
| `SmsGateway::TEST` flow gateway | no | yes, platform generated statuses posted to your callback URL | 0.01 CZK per message | end to end verification including webhook processing |
| Production sending | yes | yes | per price list | real traffic |

To use the mock API, pass a `BaseUri` as the fifth argument of `ApiClient` (it must be an absolute `https` URL):

```php
<?php declare(strict_types = 1);

use Neatous\SmsManager\ApiClient;
use Neatous\SmsManager\ApiKey;
use Neatous\SmsManager\BaseUri;

$httpFactory = new GuzzleHttp\Psr7\HttpFactory();

$client = new ApiClient(
    ApiKey::fromString('your-api-key'),
    new GuzzleHttp\Client(['timeout' => 15, 'connect_timeout' => 5]),
    $httpFactory,
    $httpFactory,
    BaseUri::fromString('https://api-mock.smsmngr.com/v2'),
);
```

To use the test gateway, send a single step flow: `SmsFlowStep::create(gateway: SmsGateway::TEST)`.

## Nette integration

The library ships an extension for [Nette DI](https://doc.nette.org/en/dependency-injection). It needs `nette/di` 3.2.

### Minimal configuration

```neon
extensions:
    smsManager: Neatous\SmsManager\Bridges\NetteDI\SmsManagerExtension

services:
    - GuzzleHttp\Client([timeout: 15, connect_timeout: 5])
    - GuzzleHttp\Psr7\HttpFactory

smsManager:
    apiKey: %smsManager.apiKey%
    fake: %debugMode%
    journalDir: %rootDir%/log/smsmanager
```

`%smsManager.apiKey%` is a parameter that you define yourself, for example in a local configuration file that is not committed.

What the extension registers:

- **Real mode** (`fake: false`): a `ClientFactory` service (`ApiClientFactory`) and, when `apiKey` is set, a `Client` service (`ApiClient`). The PSR-18 client and the PSR-17 factories are taken from your container by autowiring, so they must be registered there, as in the `services` section above.
- **Fake mode** (`fake: true`): a `Client` service (`FakeClient`), a `ClientFactory` service (`FakeClientFactory`) and a `MessageJournal` service. No HTTP services are needed. `journalDir` is required.
- In fake mode, when the container has a Tracy `Bar` service, a bar panel with the 20 latest journalled messages is added automatically.

Ask for `Neatous\SmsManager\Client` in the constructor of your service. The same code then works in both modes.

### A dedicated HTTP client

By default SmsManager shares the HTTP client that is autowired in your container. To give SmsManager its own client with its own timeouts, set `httpClient`:

```neon
extensions:
    smsManager: Neatous\SmsManager\Bridges\NetteDI\SmsManagerExtension

services:
    - GuzzleHttp\Psr7\HttpFactory

smsManager:
    apiKey: %smsManager.apiKey%
    httpClient: GuzzleHttp\Client([timeout: 15, connect_timeout: 5])
```

`httpClient` accepts three forms:

- an inline definition, as above, or a class name (`GuzzleHttp\Client`). Such a client is created only for SmsManager and is never autowired into other services.
- a service reference `@name` (or by type, `@GuzzleHttp\Client`). The referenced service can be registered with `autowired: no`, so no other service receives it.
- a factory call such as `Class::method()`, `@name::method()` or `Class()::method()`.

The PSR-17 factories are still autowired, so `GuzzleHttp\Psr7\HttpFactory` stays in `services`.

### One API key per customer

When the API key is known only at runtime, for example one key per customer, use `ClientFactory`. It creates a client for a given key. The HTTP client, base URI and default sender come from the configuration. In this case `apiKey` can be left out of the configuration.

```php
<?php declare(strict_types = 1);

namespace App\Model;

use Neatous\SmsManager\Acceptance\MessageId;
use Neatous\SmsManager\ApiKey;
use Neatous\SmsManager\ClientFactory;
use Neatous\SmsManager\Message\Message;

final class CustomerSmsSender
{

    private ClientFactory $clientFactory;

    public function __construct(ClientFactory $clientFactory)
    {
        $this->clientFactory = $clientFactory;
    }

    public function send(string $customerApiKey, Message $message): MessageId
    {
        $client = $this->clientFactory->create(ApiKey::fromString($customerApiKey));

        return $client->send($message)->getSingleMessageId();
    }
}
```

In fake mode the factory ignores the key and returns a `FakeClient` that writes into the journal.

### Options

| Option | Type | Default | Meaning |
| --- | --- | --- | --- |
| `apiKey` | string | none | The API key of the `Client` service. Without it only the `ClientFactory` is registered in real mode. |
| `fake` | bool | `false` | Fake mode: messages go to the journal, not to the API. |
| `journalDir` | string | none | The journal directory. Required when `fake` is enabled. |
| `httpClient` | string or definition | none | A dedicated PSR-18 client, see above. Without it the client is autowired. |
| `baseUri` | string | production API | Another API address, for example the mock API `https://api-mock.smsmngr.com/v2`. |
| `defaultSender` | string | none | The sender for messages that do not set one. An invalid value fails the container compilation. |
| `panel` | bool | `true` | Set to `false` to disable the Tracy panel. |

### Validation of `httpClient`

The option is validated when the container is compiled, in every mode. In fake mode a valid value is otherwise ignored. An invalid value throws `InvalidExtensionConfigurationException`.

Refused:

- an empty value, or a string that is neither `@name` nor an existing class name,
- `@name` that refers to a service or alias that is not defined (also as the origin of `@name::method()`),
- `@name` whose known type is not a PSR-18 `ClientInterface`,
- `Class()` when the class does not exist (also as the origin of `Class::method()` and `Class()::method()`),
- `Class()` when the class is not a PSR-18 `ClientInterface`,
- a plain function such as `strlen()`,
- a call of a service: `@name()`, `@name(args)` or `@name()::method()`. Use `@name` to reference the shared service.

For a `@name` reference the declared type of the service definition is what is checked. A service declared as `type: GuzzleHttp\ClientInterface` is therefore refused even if its factory returns a PSR-18 client, because that Guzzle interface is not the PSR-18 interface.

Not checked:

- that the method in `Class::method()` or `@name::method()` exists, and what it returns,
- a referenced service whose type is not known yet; it is accepted,
- the arguments of the definition, for example the names of Guzzle options.

## Security notes

- The API key is sent in the `x-api-key` header, always over https (`BaseUri` refuses anything else).
- `ApiKey` masks itself in `var_dump()` output and never appears in exception messages. Tracy's dumper ignores `__debugInfo` unless its `debuginfo` option is enabled, so a dumped `ApiClient` shows the key in a Tracy bar or bluescreen.
- The library does not configure timeouts; the injected PSR-18 client keeps its own. Set them, see [Quick start](#quick-start).
- The library never retries a request.
- The journal of the fake client stores message bodies as plain text. Keep the directory out of the public web root.
- The sending API answers a missing API key with status 401 and an unknown API key with status 403 (the official error reference lists 400 for both). Both surface as `ApiRequestFailedException` with the respective `getStatusCode()`.

## Not covered

- the batch `/messages` and legacy `/simple/*` endpoints
- flow steps for channels other than SMS (webhook parsing understands all channels)
- the `params` field (link shortening) and the object form of `callback` (custom webhook formats)
- incoming message webhooks
