<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests;

use DateTimeImmutable;
use DateTimeZone;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Neatous\SmsManager\ApiClient;
use Neatous\SmsManager\ApiKey;
use Neatous\SmsManager\BaseUri;
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
use Neatous\SmsManager\Tests\Support\RecordingHttpClient;
use PHPUnit\Framework\TestCase;

final class ApiClientTest extends TestCase
{

    private const string API_KEY = 'top-secret-api-key';

    public function testSendsFullFeaturedMessage(): void
    {
        $httpClient = RecordingHttpClient::withResponse(self::acceptedResponse('{"request_id":"req-1","accepted":[{"key":"0","message_id":"m-1"}]}'));
        $acceptance = self::apiClient($httpClient)->send(self::fullFeaturedMessage());
        $request = $httpClient->getLastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://api.smsmngr.com/v2/message', (string) $request->getUri());
        self::assertSame(self::API_KEY, $request->getHeaderLine('x-api-key'));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame(
            '{"body":"Hello world","to":[{"phone_number":"420777123456"},{"phone_number":"420777654321"}],"sender":"Neatous","tag":"transactional","callback":"https:\/\/example.com\/callback","datetime":"2025-01-11T10:00:00Z","delivery_time":{"days":["monday","tuesday"],"start":"08:00","end":"21:00","tz":"Europe\/Prague"},"payload":{"orderId":42,"vip":true}}',
            (string) $request->getBody()
        );
        self::assertSame('req-1', $acceptance->getRequestId()->getValue());
    }

    public function testSendPriorityTargetsPriorityEndpoint(): void
    {
        $httpClient = RecordingHttpClient::withResponse(self::acceptedResponse('{"request_id":"req-1"}'));
        self::apiClient($httpClient)->sendPriority(self::minimalMessage());
        self::assertSame('https://api.smsmngr.com/v2/message/priority', (string) $httpClient->getLastRequest()->getUri());
    }

    public function testTargetsCustomBaseUri(): void
    {
        $httpClient = RecordingHttpClient::withResponse(self::acceptedResponse('{"request_id":"req-1"}'));
        self::apiClient($httpClient, BaseUri::fromString('https://api-mock.smsmngr.com/v2'))->send(self::minimalMessage());
        self::assertSame('https://api-mock.smsmngr.com/v2/message', (string) $httpClient->getLastRequest()->getUri());
    }

    public function testThrowsOnErrorResponse(): void
    {
        $httpClient = RecordingHttpClient::withResponse(new Response(400, [], '{"Message":"Invalid phone number"}'));

        try {
            self::apiClient($httpClient)->send(self::minimalMessage());
            self::fail('The request was expected to fail.');
        } catch (\Neatous\SmsManager\Exception\ApiRequestFailedException $exception) {
            self::assertSame(400, $exception->getStatusCode());
            self::assertSame('The SmsManager API request failed with status code 400: {"Message":"Invalid phone number"}', $exception->getMessage());
            self::assertStringNotContainsString(self::API_KEY, $exception->getMessage());
        }
    }

    public function testWrapsTransportFailure(): void
    {
        $transportException = new \GuzzleHttp\Exception\ConnectException('Connection refused', new Request('POST', 'https://api.smsmngr.com/v2/message'));
        $httpClient = RecordingHttpClient::withException($transportException);

        try {
            self::apiClient($httpClient)->send(self::minimalMessage());
            self::fail('The request was expected to fail.');
        } catch (\Neatous\SmsManager\Exception\ApiRequestFailedException $exception) {
            self::assertSame($transportException, $exception->getPrevious());
            self::assertStringNotContainsString(self::API_KEY, $exception->getMessage());
        }
    }

    private static function apiClient(RecordingHttpClient $httpClient, ?BaseUri $baseUri = null): ApiClient
    {
        $httpFactory = new HttpFactory();

        return new ApiClient(ApiKey::fromString(self::API_KEY), $httpClient, $httpFactory, $httpFactory, $baseUri);
    }

    private static function acceptedResponse(string $body): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], $body);
    }

    private static function fullFeaturedMessage(): Message
    {
        $recipients = RecipientList::fromPhoneNumbers(PhoneNumber::fromString('420777123456'), PhoneNumber::fromString('+420 777 654 321'));
        $deliveryWindow = DeliveryWindow::create(
            WeekDayList::fromWeekDays(WeekDay::MONDAY, WeekDay::TUESDAY),
            TimeOfDay::fromString('08:00'),
            TimeOfDay::fromString('21:00'),
            new DateTimeZone('Europe/Prague')
        );

        return Message::create(MessageBody::fromString('Hello world'), $recipients)
            ->withSender(Sender::fromString('Neatous'))
            ->withTags(TagList::fromTags(Tag::transactional()))
            ->withCallbackUrl(CallbackUrl::fromString('https://example.com/callback'))
            ->withScheduledAt(new DateTimeImmutable('2025-01-11 11:00:00', new DateTimeZone('Europe/Prague')))
            ->withDeliveryWindow($deliveryWindow)
            ->withPayload(Payload::fromArray(['orderId' => 42, 'vip' => true]));
    }

    private static function minimalMessage(): Message
    {
        return Message::create(MessageBody::fromString('Hello world'), RecipientList::fromPhoneNumbers(PhoneNumber::fromString('420777123456')));
    }
}
