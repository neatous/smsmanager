<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Neatous\SmsManager\ApiClient;
use Neatous\SmsManager\ApiClientFactory;
use Neatous\SmsManager\ApiKey;
use Neatous\SmsManager\BaseUri;
use Neatous\SmsManager\Message\Message;
use Neatous\SmsManager\Message\MessageBody;
use Neatous\SmsManager\Message\PhoneNumber;
use Neatous\SmsManager\Message\RecipientList;
use Neatous\SmsManager\Tests\Support\RecordingHttpClient;
use PHPUnit\Framework\TestCase;

final class ApiClientFactoryTest extends TestCase
{

    public function testCreatesApiClientTargetingDefaultBaseUri(): void
    {
        $httpClient = RecordingHttpClient::withResponse(self::acceptedResponse());
        $httpFactory = new HttpFactory();
        $sender = (new ApiClientFactory($httpClient, $httpFactory, $httpFactory))->create(ApiKey::fromString('default-key'));
        self::assertInstanceOf(ApiClient::class, $sender);

        $sender->send(self::minimalMessage());
        self::assertSame('https://api.smsmngr.com/v2/message', (string) $httpClient->getLastRequest()->getUri());
    }

    public function testCreatesApiClientTargetingCustomBaseUriWithGivenApiKey(): void
    {
        $httpClient = RecordingHttpClient::withResponse(self::acceptedResponse());
        $httpFactory = new HttpFactory();
        $factory = new ApiClientFactory($httpClient, $httpFactory, $httpFactory, BaseUri::fromString('https://api-mock.smsmngr.com/v2'));
        $factory->create(ApiKey::fromString('tenant-key'))->send(self::minimalMessage());

        $request = $httpClient->getLastRequest();
        self::assertSame('https://api-mock.smsmngr.com/v2/message', (string) $request->getUri());
        self::assertSame('tenant-key', $request->getHeaderLine('x-api-key'));
    }

    private static function acceptedResponse(): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], '{"request_id":"req-1"}');
    }

    private static function minimalMessage(): Message
    {
        return Message::create(MessageBody::fromString('Hello world'), RecipientList::fromPhoneNumbers(PhoneNumber::fromString('420777123456')));
    }
}
